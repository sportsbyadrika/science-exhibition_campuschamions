<?php
declare(strict_types=1);

/**
 * Approval actions shared by several admin pages (institutions, teams, payments).
 * Call handle_admin_action() at the top of a page while handling POST.
 * Returns true when an action was processed (the caller should redirect).
 */
function handle_admin_action(): bool
{
    $action = (string) input('action');
    $remarks = mb_substr((string) input('remarks'), 0, 255);
    $admin = current_user();

    switch ($action) {
        case 'institution_status':
            $inst = db_one('SELECT * FROM institutions WHERE id = ?', [input_int('id')]);
            $status = (string) input('status');
            if (!$inst || !in_array($status, ['approved', 'rejected', 'pending'], true)) {
                flash('error', 'Invalid request.');
                return true;
            }
            db_exec(
                'UPDATE institutions SET status = ?, status_remarks = ?, approved_by = ?, approved_at = ? WHERE id = ?',
                [$status, $remarks ?: null, $admin['id'], $status === 'approved' ? date('Y-m-d H:i:s') : null, $inst['id']]
            );
            log_activity('institution_' . $status, $inst['ref_no'] . ($remarks ? ' - ' . $remarks : ''));
            if ($status !== 'pending') {
                notify_institution_status(db_one('SELECT * FROM institutions WHERE id = ?', [$inst['id']]));
            }
            flash('success', $inst['name'] . ' marked as ' . $status . '.');
            return true;

        case 'team_status':
            $ids = array_filter(array_map('intval', (array) ($_POST['ids'] ?? [input_int('id')])));
            $status = (string) input('status');
            if (!$ids || !in_array($status, ['approved', 'rejected', 'pending'], true)) {
                flash('error', 'Select at least one team entry.');
                return true;
            }
            $in = implode(',', array_fill(0, count($ids), '?'));
            db_exec(
                "UPDATE teams SET status = ?, remarks = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id IN ($in)",
                array_merge([$status, $remarks ?: null, $admin['id']], $ids)
            );
            log_activity('team_' . $status, implode(',', $ids));
            flash('success', count($ids) . ' team entr' . (count($ids) === 1 ? 'y' : 'ies') . ' marked as ' . $status . '.');
            return true;

        case 'payment_status':
            $p = db_one('SELECT p.*, i.name inst_name FROM payments p JOIN institutions i ON i.id = p.institution_id WHERE p.id = ?', [input_int('id')]);
            $status = (string) input('status');
            if (!$p || !in_array($status, ['approved', 'rejected', 'pending'], true)) {
                flash('error', 'Invalid request.');
                return true;
            }
            $approvedAmount = $status === 'approved'
                ? (input('approved_amount') !== '' ? max(0, (float) input('approved_amount')) : (float) $p['amount'])
                : null;
            db_exec(
                'UPDATE payments SET status = ?, approved_amount = ?, remarks = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?',
                [$status, $approvedAmount, $remarks ?: null, $admin['id'], $p['id']]
            );
            log_activity('payment_' . $status, $p['utr_no']);
            if ($status !== 'pending') {
                $inst = db_one('SELECT * FROM institutions WHERE id = ?', [$p['institution_id']]);
                $body = '<p>Dear ' . e($inst['coord_name']) . ',</p><p>The fund transfer submitted by <strong>' . e($inst['name']) . '</strong> has been <strong>' . e($status) . '</strong>.</p>'
                    . mail_table(['UTR / Reference' => $p['utr_no'], 'Amount' => inr($p['amount'])] + ($approvedAmount !== null ? ['Approved Amount' => inr($approvedAmount)] : []) + ($remarks ? ['Remarks' => $remarks] : []));
                send_mail(institution_emails($inst), 'Fund Transfer ' . ucfirst($status) . ' - ' . $p['utr_no'], $body);
            }
            flash('success', 'Fund transfer ' . $p['utr_no'] . ' marked as ' . $status . '.');
            return true;

        case 'reopen_submission':
            $inst = db_one('SELECT * FROM institutions WHERE id = ?', [input_int('id')]);
            if ($inst) {
                db_exec('UPDATE institutions SET submission_status = "reopened" WHERE id = ?', [$inst['id']]);
                log_activity('submission_reopened', $inst['ref_no']);
                flash('success', 'Submission reopened. The institution can edit its entries and submit again.');
            }
            return true;
    }
    return false;
}
