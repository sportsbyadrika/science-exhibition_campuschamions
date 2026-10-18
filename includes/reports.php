<?php
declare(strict_types=1);

/**
 * PDF report body builders. Each returns ['title' => ..., 'subtitle' => ..., 'body' => html, 'orientation' => ...]
 * and is rendered through pdf_render() (repeating header/footer + page numbers).
 */

function rpt_empty(string $msg = 'No records found.'): string
{
    return '<div class="empty">' . e($msg) . '</div>';
}

function rpt_institution_block(array $inst): string
{
    $logo = !empty($inst['logo_path']) && ($p = upload_path($inst['logo_path']))
        ? '<img src="' . e($p) . '" style="width:34px;height:34px;vertical-align:middle;margin-right:8px">' : '';
    $address = trim(($inst['address'] ?? '') . (($inst['district'] ?? '') ? ', ' . $inst['district'] : ''), ', ');
    return '<table class="kv">'
        . '<tr><td class="k">Institution</td><td colspan="3" class="bold">' . $logo . e($inst['name'])
        . ($address !== '' ? '<br><span class="muted small" style="font-weight:normal">' . e($address) . '</span>' : '') . '</td></tr>'
        . '<tr><td class="k">Affiliation Code</td><td class="mono">' . e($inst['affiliation_code']) . '</td><td class="k">Reference ID</td><td class="mono">' . e($inst['ref_no']) . '</td></tr>'
        . '<tr><td class="k">Head of Institution</td><td>' . e($inst['head_name']) . '<br><span class="muted small">' . e($inst['head_designation']) . ' • ' . e($inst['head_phone']) . ' • ' . e($inst['head_email']) . '</span></td>'
        . '<td class="k">Coordinator (e-Admin)</td><td>' . e($inst['coord_name']) . '<br><span class="muted small">' . e($inst['coord_designation']) . ' • ' . e($inst['coord_phone']) . ' • ' . e($inst['coord_email']) . '</span></td></tr>'
        . '<tr><td class="k">Registration Status</td><td>' . pdf_badge($inst['status']) . '</td><td class="k">Submission</td><td>' . pdf_badge($inst['submission_status'])
        . ($inst['submission_ref'] ? ' <span class="mono small">' . e($inst['submission_ref']) . '</span>' : '')
        . ($inst['submitted_at'] ? '<br><span class="muted small">' . e(fmt_datetime($inst['submitted_at'])) . '</span>' : '') . '</td></tr>'
        . '</table>';
}

function rpt_summary_cards(array $s): string
{
    return '<table class="cards"><tr>'
        . '<td><div class="card-label">Team Entries</div><div class="card-value">' . $s['teams_total'] . '</div><div class="small muted">' . $s['students'] . ' students</div></td>'
        . '<td><div class="card-label">Food Coupons</div><div class="card-value">' . $s['food_qty'] . '</div><div class="small muted">' . e(inr($s['food_amount'])) . '</div></td>'
        . '<td><div class="card-label">Total Payable</div><div class="card-value">' . e(inr($s['payable'])) . '</div><div class="small muted">Teams + Meals</div></td>'
        . '<td><div class="card-label">Fund Approved</div><div class="card-value">' . e(inr($s['paid_approved'])) . '</div><div class="small muted">Submitted ' . e(inr($s['paid_submitted'])) . '</div></td>'
        . '</tr></table>';
}

function rpt_teams_table(array $teams, bool $photos = true): string
{
    if (!$teams) {
        return rpt_empty('No team entries registered.');
    }
    $h = '<table class="grid"><thead><tr><th style="width:4%">#</th><th style="width:15%">Team Code</th><th>Event / Category / Team Name</th><th style="width:' . ($photos ? '38' : '34') . '%">Participants</th><th style="width:9%" class="text-right">Fee</th><th style="width:9%">Status</th></tr></thead><tbody>';
    $total = 0;
    foreach ($teams as $i => $t) {
        $members = '';
        foreach ($t['members'] as $m) {
            $img = $photos && ($p = upload_path($m['photo_path'])) ? '<img class="photo" src="' . e($p) . '">' : '';
            $members .= $photos
                ? '<table style="width:100%;margin-bottom:3px"><tr><td style="width:48px;border:0;padding:0 4px 0 0">' . $img . '</td><td style="border:0;padding:0;vertical-align:middle"><span class="bold">' . e($m['name']) . '</span><br><span class="muted small">' . e($m['class_name']) . ($m['gender'] ? ' • ' . e($m['gender']) : '') . '</span></td></tr></table>'
                : '<div><span class="bold">' . e($m['name']) . '</span> <span class="muted small">(' . e($m['class_name']) . ')</span></div>';
        }
        $total += $t['status'] !== 'rejected' ? (float) $t['fee'] : 0;
        $h .= '<tr><td>' . ($i + 1) . '</td><td class="mono bold">' . e($t['team_code']) . '</td>'
            . '<td><span class="bold">' . e($t['event_name']) . '</span> • ' . e($t['category_name']) . '<br>' . e($t['project_title'])
            . ($t['guide_teacher'] ? '<br><span class="muted small">Guide: ' . e($t['guide_teacher']) . '</span>' : '')
            . (!empty($t['institution_name']) ? '<br><span class="muted small">' . e($t['institution_name']) . '</span>' : '') . '</td>'
            . '<td>' . $members . '</td><td class="text-right">' . e(inr($t['fee'])) . '</td><td>' . pdf_badge($t['status']) . '</td></tr>';
    }
    $h .= '</tbody><tfoot><tr><td colspan="4" class="text-right">Total Entry Fee (excluding rejected)</td><td class="text-right">' . e(inr($total)) . '</td><td></td></tr></tfoot></table>';
    return $h;
}

function rpt_food_table(array $food): string
{
    $rows = array_filter($food, fn ($f) => (int) $f['quantity'] > 0);
    if (!$rows) {
        return rpt_empty('No food coupons requested.');
    }
    $h = '<table class="grid"><thead><tr><th>#</th><th>Coupon Head</th><th>Date</th><th class="text-right">Rate</th><th class="text-right">Quantity</th><th class="text-right">Amount</th></tr></thead><tbody>';
    $q = 0; $a = 0; $i = 0;
    foreach ($rows as $f) {
        $q += (int) $f['quantity'];
        $a += (float) $f['amount'];
        $h .= '<tr><td>' . (++$i) . '</td><td class="bold">' . e($f['name']) . '</td><td>' . e(fmt_date($f['meal_date'])) . '</td><td class="text-right">' . e(inr($f['requested_rate'] ?? $f['rate'])) . '</td><td class="text-right">' . (int) $f['quantity'] . '</td><td class="text-right">' . e(inr($f['amount'])) . '</td></tr>';
    }
    return $h . '</tbody><tfoot><tr><td colspan="4" class="text-right">Total</td><td class="text-right">' . $q . '</td><td class="text-right">' . e(inr($a)) . '</td></tr></tfoot></table>';
}

function rpt_payments_table(array $payments, bool $withInstitution = false): string
{
    if (!$payments) {
        return rpt_empty('No fund transfer details recorded.');
    }
    $h = '<table class="grid"><thead><tr><th>#</th>' . ($withInstitution ? '<th>Institution</th>' : '') . '<th>UTR / Reference</th><th>Date</th><th>Mode</th><th class="text-right">Amount</th><th class="text-right">Approved</th><th>Status</th></tr></thead><tbody>';
    $sum = 0; $app = 0;
    foreach ($payments as $i => $p) {
        $sum += (float) $p['amount'];
        $approved = $p['status'] === 'approved' ? (float) ($p['approved_amount'] ?? $p['amount']) : 0;
        $app += $approved;
        $h .= '<tr><td>' . ($i + 1) . '</td>'
            . ($withInstitution ? '<td>' . e($p['institution_name']) . '<br><span class="muted small mono">' . e($p['affiliation_code']) . '</span></td>' : '')
            . '<td class="mono">' . e($p['utr_no']) . ($p['remitter_bank'] ? '<br><span class="muted small">' . e($p['remitter_bank']) . '</span>' : '') . '</td>'
            . '<td>' . e(fmt_date($p['payment_date'])) . '</td><td>' . e($p['payment_mode']) . '</td>'
            . '<td class="text-right">' . e(inr($p['amount'])) . '</td><td class="text-right">' . ($approved ? e(inr($approved)) : '—') . '</td>'
            . '<td>' . pdf_badge($p['status']) . ($p['remarks'] ? '<br><span class="muted small">' . e($p['remarks']) . '</span>' : '') . '</td></tr>';
    }
    $cols = $withInstitution ? 5 : 4;
    return $h . '</tbody><tfoot><tr><td colspan="' . $cols . '" class="text-right">Total</td><td class="text-right">' . e(inr($sum)) . '</td><td class="text-right">' . e(inr($app)) . '</td><td></td></tr></tfoot></table>';
}

function rpt_fee_statement(array $s): string
{
    return '<table class="kv fee" style="width:55%;margin-left:45%">'
        . '<tr><td class="k">Team Entry Fees</td><td class="text-right">' . e(inr($s['team_fee'])) . '</td></tr>'
        . '<tr><td class="k">Food Coupons</td><td class="text-right">' . e(inr($s['food_amount'])) . '</td></tr>'
        . '<tr><td class="k">Total Payable</td><td class="text-right bold">' . e(inr($s['payable'])) . '</td></tr>'
        . '<tr><td class="k">Fund Transfers Submitted</td><td class="text-right">' . e(inr($s['paid_submitted'])) . '</td></tr>'
        . '<tr><td class="k">Fund Approved</td><td class="text-right">' . e(inr($s['paid_approved'])) . '</td></tr>'
        . '<tr><td class="k">Balance to Remit</td><td class="text-right bold">' . e(inr($s['balance'])) . '</td></tr>'
        . '</table>';
}

/** Full institutional application (used as acknowledgement & admin record). */
function report_institution_application(int $instId): array
{
    $inst = db_one('SELECT * FROM institutions WHERE id = ?', [$instId]);
    $s = institution_summary($instId);
    $body = '<h2 class="section">Institution Details</h2>' . rpt_institution_block($inst)
        . rpt_summary_cards($s)
        . '<h2 class="section">Team Entries</h2>' . rpt_teams_table(institution_teams($instId))
        . '<h2 class="section">Food Coupon Requisition</h2>' . rpt_food_table(institution_food($instId))
        . '<h2 class="section">Fund Transfer Details</h2>' . rpt_payments_table(institution_payments($instId))
        . '<h2 class="section">Fee Statement</h2>' . rpt_fee_statement($s)
        . '<div class="declaration">I certify as the official institutional e-Admin that the photos, classes, and project details submitted belong to verified regular students of the institution and that the fund transfer details furnished are correct.</div>'
        . '<table class="sign"><tr><td>Coordinator (e-Admin)<br><br><br>' . e($inst['coord_name']) . '</td><td class="text-right">Head of Institution (Seal &amp; Signature)<br><br><br>' . e($inst['head_name']) . '</td></tr></table>';
    return [
        'title' => 'Application Summary',
        'subtitle' => $inst['name'] . ' • Aff. ' . $inst['affiliation_code'],
        'body' => $body,
        'filename' => 'Application_' . $inst['affiliation_code'] . '.pdf',
    ];
}

function report_institution_teams(int $instId): array
{
    $inst = db_one('SELECT * FROM institutions WHERE id = ?', [$instId]);
    return [
        'title' => 'Team Entry List',
        'subtitle' => $inst['name'] . ' • Aff. ' . $inst['affiliation_code'],
        'body' => rpt_institution_block($inst) . '<h2 class="section">Team Entries</h2>' . rpt_teams_table(institution_teams($instId)),
        'filename' => 'Teams_' . $inst['affiliation_code'] . '.pdf',
    ];
}

function report_institution_food(int $instId): array
{
    $inst = db_one('SELECT * FROM institutions WHERE id = ?', [$instId]);
    return [
        'title' => 'Food Coupon Request',
        'subtitle' => $inst['name'] . ' • Aff. ' . $inst['affiliation_code'],
        'body' => rpt_institution_block($inst) . '<h2 class="section">Food Coupon Requisition (Head wise)</h2>' . rpt_food_table(institution_food($instId)),
        'filename' => 'FoodCoupons_' . $inst['affiliation_code'] . '.pdf',
    ];
}

function report_institution_payments(int $instId): array
{
    $inst = db_one('SELECT * FROM institutions WHERE id = ?', [$instId]);
    $s = institution_summary($instId);
    return [
        'title' => 'Fund Transfer Statement',
        'subtitle' => $inst['name'] . ' • Aff. ' . $inst['affiliation_code'],
        'body' => rpt_institution_block($inst) . '<h2 class="section">Fund Transfers</h2>' . rpt_payments_table(institution_payments($instId))
            . '<h2 class="section">Fee Statement</h2>' . rpt_fee_statement($s),
        'filename' => 'FundStatement_' . $inst['affiliation_code'] . '.pdf',
    ];
}

/* ---------------------------------------------------------------------
 * Admin (consolidated) reports
 * ------------------------------------------------------------------- */

function report_institutions(string $status = ''): array
{
    $where = $status !== '' ? 'WHERE i.status = ?' : '';
    $rows = db_all(
        "SELECT i.*,
                (SELECT COUNT(*) FROM teams t WHERE t.institution_id = i.id AND t.status <> 'rejected') teams,
                (SELECT COALESCE(SUM(fee),0) FROM teams t WHERE t.institution_id = i.id AND t.status <> 'rejected') team_fee,
                (SELECT COALESCE(SUM(amount),0) FROM food_requests f WHERE f.institution_id = i.id) food_amount,
                (SELECT COALESCE(SUM(CASE WHEN status='approved' THEN COALESCE(approved_amount,amount) ELSE 0 END),0) FROM payments p WHERE p.institution_id = i.id) paid_approved
           FROM institutions i $where ORDER BY i.name",
        $status !== '' ? [$status] : []
    );
    if (!$rows) {
        $body = rpt_empty();
    } else {
        $body = '<table class="grid"><thead><tr><th>#</th><th>Aff. Code</th><th>Institution</th><th>Head of Institution</th><th>Coordinator</th><th class="text-right">Teams</th><th class="text-right">Payable</th><th class="text-right">Approved</th><th>Status</th></tr></thead><tbody>';
        $tot = ['teams' => 0, 'payable' => 0, 'approved' => 0];
        foreach ($rows as $i => $r) {
            $payable = (float) $r['team_fee'] + (float) $r['food_amount'];
            $tot['teams'] += (int) $r['teams'];
            $tot['payable'] += $payable;
            $tot['approved'] += (float) $r['paid_approved'];
            $body .= '<tr><td>' . ($i + 1) . '</td><td class="mono">' . e($r['affiliation_code']) . '</td><td class="bold">' . e($r['name']) . '<br><span class="muted small mono">' . e($r['ref_no']) . '</span></td>'
                . '<td>' . e($r['head_name']) . '<br><span class="muted small">' . e($r['head_phone']) . '</span></td>'
                . '<td>' . e($r['coord_name']) . '<br><span class="muted small">' . e($r['coord_phone']) . ' • ' . e($r['coord_email']) . '</span></td>'
                . '<td class="text-right">' . (int) $r['teams'] . '</td><td class="text-right">' . e(inr($payable)) . '</td><td class="text-right">' . e(inr($r['paid_approved'])) . '</td>'
                . '<td>' . pdf_badge($r['status']) . '<br>' . pdf_badge($r['submission_status']) . '</td></tr>';
        }
        $body .= '</tbody><tfoot><tr><td colspan="5" class="text-right">Total (' . count($rows) . ' institutions)</td><td class="text-right">' . $tot['teams'] . '</td><td class="text-right">' . e(inr($tot['payable'])) . '</td><td class="text-right">' . e(inr($tot['approved'])) . '</td><td></td></tr></tfoot></table>';
    }
    return [
        'title' => 'Participating Institutions',
        'subtitle' => $status !== '' ? 'Status: ' . ucfirst($status) : 'All institutions',
        'body' => $body,
        'orientation' => 'landscape',
        'filename' => 'Institutions_Report.pdf',
    ];
}

function report_funds(string $status = ''): array
{
    $where = $status !== '' ? 'WHERE p.status = ?' : '';
    $payments = db_all(
        "SELECT p.*, i.name institution_name, i.affiliation_code FROM payments p JOIN institutions i ON i.id = p.institution_id $where ORDER BY p.payment_date, p.id",
        $status !== '' ? [$status] : []
    );
    $tot = db_one(
        'SELECT COALESCE(SUM(amount),0) received,
                COALESCE(SUM(CASE WHEN status="approved" THEN COALESCE(approved_amount,amount) ELSE 0 END),0) approved,
                COALESCE(SUM(CASE WHEN status="pending" THEN amount ELSE 0 END),0) pending,
                COALESCE(SUM(CASE WHEN status="rejected" THEN amount ELSE 0 END),0) rejected
           FROM payments'
    );
    $payable = (float) db_value('SELECT COALESCE(SUM(fee),0) FROM teams WHERE status <> "rejected"') + (float) db_value('SELECT COALESCE(SUM(amount),0) FROM food_requests');
    $body = '<table class="cards"><tr>'
        . '<td><div class="card-label">Total Payable</div><div class="card-value">' . e(inr($payable)) . '</div><div class="small muted">All institutions</div></td>'
        . '<td><div class="card-label">Fund Received</div><div class="card-value">' . e(inr($tot['received'])) . '</div><div class="small muted">All submissions</div></td>'
        . '<td><div class="card-label">Fund Approved</div><div class="card-value">' . e(inr($tot['approved'])) . '</div><div class="small muted">Verified</div></td>'
        . '<td><div class="card-label">Pending Verification</div><div class="card-value">' . e(inr($tot['pending'])) . '</div><div class="small muted">Rejected ' . e(inr($tot['rejected'])) . '</div></td>'
        . '</tr></table>'
        . '<h2 class="section">Fund Transfer Register</h2>' . rpt_payments_table($payments, true);
    return [
        'title' => 'Fund Received & Approved',
        'subtitle' => $status !== '' ? 'Status: ' . ucfirst($status) : 'All fund transfers',
        'body' => $body,
        'orientation' => 'landscape',
        'filename' => 'Fund_Report.pdf',
    ];
}

function report_event_participation(): array
{
    $events = db_all('SELECT * FROM events ORDER BY sort_order, name');
    $categories = db_all('SELECT * FROM categories ORDER BY sort_order, name');
    $counts = [];
    foreach (db_all('SELECT event_id, category_id, COUNT(*) n, SUM(status="approved") a, COUNT(DISTINCT institution_id) s FROM teams WHERE status <> "rejected" GROUP BY event_id, category_id') as $r) {
        $counts[$r['event_id']][$r['category_id']] = $r;
    }
    $body = '<h2 class="section">Event × Category Matrix (Teams, excluding rejected)</h2><table class="grid"><thead><tr><th>Event</th>';
    foreach ($categories as $c) {
        $body .= '<th class="text-center">' . e($c['name']) . '</th>';
    }
    $body .= '<th class="text-center">Total Teams</th><th class="text-center">Approved</th></tr></thead><tbody>';
    $colTotals = [];
    foreach ($events as $ev) {
        $body .= '<tr><td class="bold">' . e($ev['name']) . ' <span class="muted mono small">' . e($ev['code']) . '</span></td>';
        $rt = 0; $ra = 0;
        foreach ($categories as $c) {
            $n = (int) ($counts[$ev['id']][$c['id']]['n'] ?? 0);
            $rt += $n;
            $ra += (int) ($counts[$ev['id']][$c['id']]['a'] ?? 0);
            $colTotals[$c['id']] = ($colTotals[$c['id']] ?? 0) + $n;
            $body .= '<td class="text-center">' . ($n ?: '–') . '</td>';
        }
        $body .= '<td class="text-center bold">' . $rt . '</td><td class="text-center">' . $ra . '</td></tr>';
    }
    $body .= '</tbody><tfoot><tr><td>Total</td>';
    foreach ($categories as $c) {
        $body .= '<td class="text-center">' . ($colTotals[$c['id']] ?? 0) . '</td>';
    }
    $body .= '<td class="text-center">' . array_sum($colTotals) . '</td><td></td></tr></tfoot></table>';

    // Detailed listing per event
    $teams = db_all(
        'SELECT t.*, e.name event_name, c.name category_name, i.name institution_name
           FROM teams t JOIN events e ON e.id = t.event_id JOIN categories c ON c.id = t.category_id JOIN institutions i ON i.id = t.institution_id
          WHERE t.status <> "rejected" ORDER BY e.sort_order, c.sort_order, i.name'
    );
    attach_members($teams);
    $byEvent = [];
    foreach ($teams as $t) {
        $byEvent[$t['event_name']][] = $t;
    }
    foreach ($byEvent as $name => $list) {
        $body .= '<h2 class="section">' . e($name) . ' — ' . count($list) . ' team(s)</h2>' . rpt_teams_table($list, false);
    }
    return [
        'title' => 'Event-wise Participation',
        'subtitle' => count($teams) . ' team entries',
        'body' => $body,
        'orientation' => 'landscape',
        'filename' => 'Event_Participation.pdf',
    ];
}

function report_food_summary(): array
{
    $items = db_all(
        'SELECT f.*, COALESCE(SUM(r.quantity),0) qty, COALESCE(SUM(r.amount),0) amt, COUNT(CASE WHEN r.quantity > 0 THEN 1 END) schools
           FROM food_items f LEFT JOIN food_requests r ON r.food_item_id = f.id GROUP BY f.id ORDER BY f.sort_order, f.id'
    );
    $body = '<h2 class="section">Coupon Head Summary</h2><table class="grid"><thead><tr><th>#</th><th>Coupon Head</th><th>Date</th><th class="text-right">Rate</th><th class="text-right">Institutions</th><th class="text-right">Coupons</th><th class="text-right">Amount</th></tr></thead><tbody>';
    $q = 0; $a = 0;
    foreach ($items as $i => $f) {
        $q += (int) $f['qty'];
        $a += (float) $f['amt'];
        $body .= '<tr><td>' . ($i + 1) . '</td><td class="bold">' . e($f['name']) . '</td><td>' . e(fmt_date($f['meal_date'])) . '</td><td class="text-right">' . e(inr($f['rate'])) . '</td><td class="text-right">' . (int) $f['schools'] . '</td><td class="text-right">' . (int) $f['qty'] . '</td><td class="text-right">' . e(inr($f['amt'])) . '</td></tr>';
    }
    $body .= '</tbody><tfoot><tr><td colspan="5" class="text-right">Total</td><td class="text-right">' . $q . '</td><td class="text-right">' . e(inr($a)) . '</td></tr></tfoot></table>';

    $rows = db_all(
        'SELECT i.name, i.affiliation_code, r.food_item_id, r.quantity FROM food_requests r JOIN institutions i ON i.id = r.institution_id WHERE r.quantity > 0 ORDER BY i.name'
    );
    $matrix = [];
    foreach ($rows as $r) {
        $matrix[$r['affiliation_code'] . '|' . $r['name']][$r['food_item_id']] = (int) $r['quantity'];
    }
    $body .= '<h2 class="section">Institution-wise Coupons</h2>';
    if (!$matrix) {
        $body .= rpt_empty('No food coupon requests yet.');
    } else {
        $body .= '<table class="grid"><thead><tr><th>#</th><th>Institution</th>';
        foreach ($items as $f) {
            $body .= '<th class="text-right">' . e($f['name']) . '</th>';
        }
        $body .= '<th class="text-right">Total</th></tr></thead><tbody>';
        $n = 0;
        foreach ($matrix as $key => $vals) {
            [$code, $name] = explode('|', $key, 2);
            $body .= '<tr><td>' . (++$n) . '</td><td>' . e($name) . ' <span class="muted mono small">' . e($code) . '</span></td>';
            foreach ($items as $f) {
                $body .= '<td class="text-right">' . ($vals[$f['id']] ?? '–') . '</td>';
            }
            $body .= '<td class="text-right bold">' . array_sum($vals) . '</td></tr>';
        }
        $body .= '</tbody></table>';
    }
    return [
        'title' => 'Food Coupon Summary',
        'subtitle' => 'Head wise & institution wise',
        'body' => $body,
        'orientation' => 'landscape',
        'filename' => 'Food_Coupon_Summary.pdf',
    ];
}

function report_all_teams(int $eventId = 0, int $categoryId = 0, string $status = ''): array
{
    $where = ['1=1'];
    $params = [];
    if ($eventId) { $where[] = 't.event_id = ?'; $params[] = $eventId; }
    if ($categoryId) { $where[] = 't.category_id = ?'; $params[] = $categoryId; }
    if ($status !== '') { $where[] = 't.status = ?'; $params[] = $status; }
    $teams = db_all(
        'SELECT t.*, e.name event_name, c.name category_name, i.name institution_name
           FROM teams t JOIN events e ON e.id = t.event_id JOIN categories c ON c.id = t.category_id JOIN institutions i ON i.id = t.institution_id
          WHERE ' . implode(' AND ', $where) . ' ORDER BY e.sort_order, c.sort_order, i.name',
        $params
    );
    attach_members($teams);
    return [
        'title' => 'Team Entries',
        'subtitle' => count($teams) . ' team(s)' . ($status !== '' ? ' • ' . ucfirst($status) : ''),
        'body' => rpt_teams_table($teams, true),
        'filename' => 'Team_Entries.pdf',
    ];
}

/** Render a report array to PDF bytes. */
function report_pdf(array $r): string
{
    return pdf_render($r['title'], $r['body'], ['subtitle' => $r['subtitle'] ?? '', 'orientation' => $r['orientation'] ?? 'portrait']);
}
