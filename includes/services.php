<?php
declare(strict_types=1);

/* ---------------------------------------------------------------------
 * Domain helpers shared by the admin and institution modules
 * ------------------------------------------------------------------- */

/** Product name shown in every header, footer and browser title. */
function app_title(): string
{
    return setting('suite_title', 'Science Fair Management Suite');
}

/** Allowed Head of Institution designations. */
function head_designations(): array
{
    return ['Principal', 'Senior Principal', 'Vice Principal', 'Headmistress / Headmaster', 'Director'];
}

/** Result positions (value => label). */
function result_positions(): array
{
    return [1 => 'First', 2 => 'Second', 3 => 'Third', 4 => 'Special Mention'];
}

function result_grades(): array
{
    return ['A+', 'A', 'B', 'C'];
}

/** New participation requests are approved automatically (admins can still reject them later). */
function auto_approve_institutions(): bool
{
    return setting('auto_approve_institutions', '1') === '1';
}

function registration_is_open(): bool
{
    if (setting('registration_open', '1') !== '1') {
        return false;
    }
    $closes = setting('registration_closes');
    return $closes === '' || strtotime($closes) === false || time() <= strtotime($closes);
}

function active_categories(): array
{
    return db_all('SELECT * FROM categories WHERE status = "active" ORDER BY sort_order, name');
}

function category_classes(array $category): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string) $category['classes']))));
}

/** Active events with their allowed category ids. */
function active_events(): array
{
    $events = db_all('SELECT * FROM events WHERE status = "active" ORDER BY sort_order, name');
    $map = [];
    foreach (db_all('SELECT event_id, category_id FROM event_categories') as $r) {
        $map[$r['event_id']][] = (int) $r['category_id'];
    }
    foreach ($events as &$ev) {
        $ev['category_ids'] = $map[$ev['id']] ?? [];
    }
    return $events;
}

function institution_emails(array $inst): array
{
    $to = [$inst['coord_email'] => $inst['coord_name']];
    if ($inst['head_email'] && strcasecmp($inst['head_email'], $inst['coord_email']) !== 0) {
        $to[$inst['head_email']] = $inst['head_name'];
    }
    return $to;
}

function institution_locked(array $inst): bool
{
    return $inst['submission_status'] === 'submitted';
}

/**
 * Consolidated numbers for an institution's registration (teams, food, payments, progress).
 */
function institution_summary(int $instId): array
{
    $teams = db_one(
        'SELECT COUNT(*) total,
                SUM(status = "approved") approved,
                SUM(status = "pending") pending,
                SUM(status = "rejected") rejected,
                COALESCE(SUM(CASE WHEN status <> "rejected" THEN fee ELSE 0 END), 0) fee
           FROM teams WHERE institution_id = ?',
        [$instId]
    );
    $students = (int) db_value(
        'SELECT COUNT(*) FROM team_members m JOIN teams t ON t.id = m.team_id WHERE t.institution_id = ? AND t.status <> "rejected"',
        [$instId]
    );
    $food = db_one(
        'SELECT COALESCE(SUM(quantity),0) qty, COALESCE(SUM(amount),0) amount FROM food_requests WHERE institution_id = ?',
        [$instId]
    );
    $pay = db_one(
        'SELECT COUNT(*) cnt,
                COALESCE(SUM(CASE WHEN status <> "rejected" THEN amount ELSE 0 END),0) submitted,
                COALESCE(SUM(CASE WHEN status = "approved" THEN COALESCE(approved_amount, amount) ELSE 0 END),0) approved,
                SUM(status = "pending") pending
           FROM payments WHERE institution_id = ?',
        [$instId]
    );
    $inst = db_one('SELECT status, submission_status, submitted_at FROM institutions WHERE id = ?', [$instId]);

    $teamFee = (float) $teams['fee'];
    $foodAmt = (float) $food['amount'];
    $payable = $teamFee + $foodAmt;
    $submitted = (float) $pay['submitted'];
    $approved = (float) $pay['approved'];

    if ($payable <= 0) {
        $payStatus = ['label' => 'Nothing Due', 'tone' => 'slate'];
    } elseif ($approved >= $payable) {
        $payStatus = ['label' => 'Fully Verified', 'tone' => 'emerald'];
    } elseif ($submitted >= $payable) {
        $payStatus = ['label' => 'Verification Pending', 'tone' => 'cyan'];
    } elseif ($submitted > 0) {
        $payStatus = ['label' => 'Partially Paid', 'tone' => 'amber'];
    } else {
        $payStatus = ['label' => 'Draft / Pending', 'tone' => 'amber'];
    }

    $steps = [
        ['key' => 'registration', 'label' => 'Institution Registered', 'done' => ($inst['status'] ?? '') === 'approved'],
        ['key' => 'teams', 'label' => 'Team Entries Added', 'done' => (int) $teams['total'] > 0],
        ['key' => 'food', 'label' => 'Food Coupons Requested', 'done' => (int) $food['qty'] > 0],
        ['key' => 'payment', 'label' => 'Fund Transfer Recorded', 'done' => $payable > 0 && $submitted >= $payable],
        ['key' => 'submitted', 'label' => 'Final Submission', 'done' => ($inst['submission_status'] ?? '') === 'submitted'],
    ];
    $done = count(array_filter($steps, fn ($s) => $s['done']));

    return [
        'teams_total'    => (int) $teams['total'],
        'teams_approved' => (int) $teams['approved'],
        'teams_pending'  => (int) $teams['pending'],
        'teams_rejected' => (int) $teams['rejected'],
        'students'       => $students,
        'team_fee'       => $teamFee,
        'food_qty'       => (int) $food['qty'],
        'food_amount'    => $foodAmt,
        'payable'        => $payable,
        'paid_submitted' => $submitted,
        'paid_approved'  => $approved,
        'payments_count' => (int) $pay['cnt'],
        'payments_pending' => (int) $pay['pending'],
        'balance'        => max(0, $payable - $submitted),
        'pay_status'     => $payStatus,
        'steps'          => $steps,
        'progress'       => (int) round($done / count($steps) * 100),
    ];
}

/** Teams of an institution with event/category names and members. */
function institution_teams(int $instId): array
{
    $teams = db_all(
        'SELECT t.*, e.name event_name, e.code event_code, c.name category_name
           FROM teams t JOIN events e ON e.id = t.event_id JOIN categories c ON c.id = t.category_id
          WHERE t.institution_id = ? ORDER BY e.sort_order, c.sort_order, t.id',
        [$instId]
    );
    attach_members($teams);
    return $teams;
}

function attach_members(array &$teams): void
{
    if (!$teams) {
        return;
    }
    $ids = array_column($teams, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $members = [];
    foreach (db_all("SELECT * FROM team_members WHERE team_id IN ($in) ORDER BY position", $ids) as $m) {
        $members[$m['team_id']][] = $m;
    }
    foreach ($teams as &$t) {
        $t['members'] = $members[$t['id']] ?? [];
    }
}

/** Food coupon heads with the institution's requested quantity. */
function institution_food(int $instId): array
{
    return db_all(
        'SELECT f.*, COALESCE(r.quantity,0) quantity, COALESCE(r.amount,0) amount, r.rate requested_rate
           FROM food_items f
      LEFT JOIN food_requests r ON r.food_item_id = f.id AND r.institution_id = ?
          WHERE f.status = "active" OR r.quantity > 0
       ORDER BY f.sort_order, f.meal_date, f.id',
        [$instId]
    );
}

function institution_payments(int $instId): array
{
    return db_all('SELECT * FROM payments WHERE institution_id = ? ORDER BY created_at DESC', [$instId]);
}

function next_team_code(array $inst, array $event): string
{
    $n = (int) db_value('SELECT COUNT(*) FROM teams WHERE institution_id = ? AND event_id = ?', [$inst['id'], $event['id']]);
    do {
        $n++;
        $code = sprintf('%s-%s-%02d', $inst['affiliation_code'], strtoupper($event['code']), $n);
    } while (db_value('SELECT id FROM teams WHERE team_code = ?', [$code]));
    return $code;
}

function generate_reference(string $prefix): string
{
    return sprintf('%s-%s-%s', $prefix, date('Y'), strtoupper(bin2hex(random_bytes(3))));
}

/* ---------------------------------------------------------------------
 * Email notifications
 * ------------------------------------------------------------------- */

function notify_registration(array $inst): void
{
    $body = '<p>Dear ' . e($inst['coord_name']) . ',</p>'
        . '<p>Thank you for submitting the participation request for <strong>' . e($inst['name']) . '</strong>. Your request has been registered with the following details:</p>'
        . mail_table([
            'Reference ID' => $inst['ref_no'],
            'Affiliation Code' => $inst['affiliation_code'],
            'Head of Institution' => $inst['head_name'] . ' (' . $inst['head_designation'] . ')',
            'Coordinator (e-Admin)' => $inst['coord_name'],
            'Login ID' => $inst['coord_email'],
            'Status' => $inst['status'] === 'approved' ? 'Approved' : 'Pending Event Admin Approval',
        ])
        . '<p>' . ($inst['status'] === 'approved'
            ? 'You can now sign in using the coordinator email ID and the password you created.'
            : 'You will be able to sign in once the Event Admin verifies and approves your request.') . '</p>';
    send_mail(institution_emails($inst), 'Participation Request Received - ' . $inst['ref_no'], $body);
}

function notify_institution_status(array $inst): void
{
    $approved = $inst['status'] === 'approved';
    $body = '<p>Dear ' . e($inst['coord_name']) . ',</p>'
        . ($approved
            ? '<p>We are pleased to inform you that the participation request of <strong>' . e($inst['name']) . '</strong> has been <strong style="color:#047857">approved</strong>. You can now sign in to the portal using your coordinator email ID (<strong>' . e($inst['coord_email']) . '</strong>) and add team entries, food coupon requests and fund transfer details.</p>'
            : '<p>The participation request of <strong>' . e($inst['name']) . '</strong> could not be approved.</p>'
              . ($inst['status_remarks'] ? '<p>Remarks: ' . e($inst['status_remarks']) . '</p>' : ''));
    send_mail(institution_emails($inst), 'Participation Request ' . ($approved ? 'Approved' : 'Not Approved') . ' - ' . $inst['ref_no'], $body);
}
