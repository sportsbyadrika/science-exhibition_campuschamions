<?php
require __DIR__ . '/../includes/bootstrap.php';
[$user, $inst] = require_institution();

$summary = institution_summary((int) $inst['id']);
$checks = [
    ['Institution registration approved', $inst['status'] === 'approved'],
    ['At least one team entry added', $summary['teams_total'] - $summary['teams_rejected'] > 0],
    ['Fund transfer details recorded for the total payable amount', $summary['payable'] > 0 && $summary['paid_submitted'] >= $summary['payable']],
];
$ready = !in_array(false, array_column($checks, 1), true);

if (is_post()) {
    verify_csrf();
    if (institution_locked($inst)) {
        flash('info', 'Your application has already been submitted.');
        redirect('institution/submit');
    }
    if (!$ready) {
        flash('error', 'Please complete all the checklist items before final submission.');
        redirect('institution/submit');
    }
    if (empty($_POST['declaration'])) {
        flash('error', 'Please acknowledge the final declaration.');
        redirect('institution/submit');
    }
    $ref = $inst['submission_ref'] ?: 'SZ-SCI-' . date('Y') . '-' . $inst['affiliation_code'];
    db_exec('UPDATE institutions SET submission_status = "submitted", submission_ref = ?, submitted_at = NOW() WHERE id = ?', [$ref, $inst['id']]);
    log_activity('final_submission', $ref);

    // Acknowledgement email with the application PDF attached
    $inst = db_one('SELECT * FROM institutions WHERE id = ?', [$inst['id']]);
    try {
        $r = report_institution_application((int) $inst['id']);
        $pdf = report_pdf($r);
        $body = '<p>Dear ' . e($inst['coord_name']) . ',</p><p>The final application of <strong>' . e($inst['name']) . '</strong> has been submitted successfully.</p>'
            . mail_table([
                'Submission Reference' => $ref,
                'Registered Teams' => (string) ($summary['teams_total'] - $summary['teams_rejected']),
                'Food Coupons' => (string) $summary['food_qty'],
                'Total Payable' => inr($summary['payable']),
                'Fund Transfers Submitted' => inr($summary['paid_submitted']),
            ])
            . '<p>The application summary is attached for your records. The secretariat will verify the team entries and fund transfer details.</p>';
        send_mail(institution_emails($inst), 'Application Submitted - ' . $ref, $body, [['name' => $r['filename'], 'data' => $pdf]]);
    } catch (Throwable $ex) {
        error_log('[submit-mail] ' . $ex->getMessage());
    }
    flash('success', 'Application successfully submitted! Reference ID: ' . $ref . '. An acknowledgement has been emailed to the Coordinator and Principal.');
    redirect('institution/submit');
}

$teams = institution_teams((int) $inst['id']);
$payments = institution_payments((int) $inst['id']);
$pageTitle = 'Final Submission';
require __DIR__ . '/../includes/layout/app_header.php';
$locked = institution_locked($inst);
?>
<section class="bg-white border border-slate-200 rounded-2xl shadow-xl overflow-hidden p-6 sm:p-8 space-y-6">
  <div class="border-b border-slate-200 pb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
    <div>
      <?php if ($locked): ?>
        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">Submitted • <?= e($inst['submission_ref']) ?></span>
      <?php else: ?>
        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">Review Before Lock</span>
      <?php endif; ?>
      <h2 class="text-xl font-black text-slate-900 mt-1">Final Application Verification</h2>
      <p class="text-xs text-slate-500">Please review team rosters, food coupons, and payment credentials before official final submission.</p>
    </div>
    <a href="<?= url('institution/report', ['type' => 'application']) ?>" target="_blank" class="btn-light self-start"><?= icon('printer') ?><?= $locked ? 'Download Application PDF' : 'Print Application Draft' ?></a>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
      <span class="text-[11px] font-bold text-slate-500 uppercase">Institution Info</span>
      <p class="text-xs font-bold text-slate-800 mt-1"><?= e($inst['name']) ?></p>
      <p class="text-[11px] text-slate-500">Affiliation: <?= e($inst['affiliation_code']) ?></p>
      <p class="text-[11px] text-slate-500">e-Admin: <?= e($inst['coord_name']) ?></p>
    </div>
    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
      <span class="text-[11px] font-bold text-slate-500 uppercase">Entries &amp; Coupons</span>
      <p class="text-xs font-bold text-slate-800 mt-1"><?= $summary['teams_total'] - $summary['teams_rejected'] ?> Registered Teams</p>
      <p class="text-[11px] text-slate-500"><?= $summary['food_qty'] ?> Food Coupons</p>
      <p class="text-[11px] text-cyan-700 font-semibold">Total: <?= inr($summary['payable']) ?></p>
    </div>
    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
      <span class="text-[11px] font-bold text-slate-500 uppercase">Fund Transfer Status</span>
      <p class="text-xs font-mono font-bold text-slate-800 mt-1">UTR: <?= e($payments ? implode(', ', array_column($payments, 'utr_no')) : 'Pending') ?></p>
      <p class="text-[11px] text-slate-500">Submitted <?= inr($summary['paid_submitted']) ?> • Approved <?= inr($summary['paid_approved']) ?></p>
      <p class="text-[11px] font-semibold <?= tone_text($summary['pay_status']['tone']) ?>">Status: <?= e($summary['pay_status']['label']) ?></p>
    </div>
  </div>

  <div class="space-y-3">
    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700">Consolidated Teams Schedule</h4>
    <div class="border border-slate-200 rounded-xl overflow-hidden table-wrap">
      <table class="data-table">
        <thead><tr><th>Team #</th><th>Category &amp; Event</th><th>Students (Class)</th><th>Status</th><th class="!text-right">Fee</th></tr></thead>
        <tbody>
          <?php if (!$teams): ?>
            <tr><td colspan="5" class="py-4 text-center text-slate-400">No teams registered. Please use the 'Add Participants' tab first.</td></tr>
          <?php endif; ?>
          <?php foreach ($teams as $t): ?>
            <tr>
              <td class="font-mono font-bold text-cyan-800"><?= e($t['team_code']) ?></td>
              <td><span class="font-semibold block"><?= e($t['project_title']) ?></span><span class="text-[10px] text-slate-400"><?= e($t['category_name']) ?> • <?= e($t['event_name']) ?></span></td>
              <td class="font-medium"><?= e(implode(', ', array_map(fn ($m) => $m['name'] . ' (' . $m['class_name'] . ')', $t['members']))) ?></td>
              <td><?= status_badge($t['status']) ?></td>
              <td class="text-right font-mono font-bold"><?= inr($t['fee']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($locked): ?>
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-xs text-emerald-900 flex items-start gap-2">
      <?= icon('badge-check', 'w-4 h-4 mt-0.5') ?>
      <span>Application submitted on <strong><?= e(fmt_datetime($inst['submitted_at'])) ?></strong>. Team entries and food coupons are now locked. You can still record additional fund transfers if a balance is due.</span>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <?php foreach ($checks as [$label, $ok]): ?>
        <div class="flex items-center gap-2 text-xs p-3 rounded-xl border <?= $ok ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' ?>">
          <?= icon($ok ? 'check-circle-2' : 'alert-circle', 'w-4 h-4 shrink-0') ?><span><?= e($label) ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <form method="post" class="space-y-4" data-confirm="Submit the final application? Team entries and food coupons will be locked.">
      <?= csrf_field() ?>
      <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex items-start space-x-3">
        <input type="checkbox" id="declaration" name="declaration" value="1" required class="mt-0.5 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500">
        <label for="declaration" class="text-xs text-emerald-900 leading-relaxed">I certify as the official institutional e-Admin that the photos, classes, and project details submitted belong to verified regular students. Fund transfer of the exact required fee has been executed from our institution.</label>
      </div>
      <div class="flex justify-end space-x-3 pt-2">
        <a href="<?= url('institution/payments') ?>" class="btn-light">Back to Payment</a>
        <button type="submit" class="btn-success" <?= $ready ? '' : 'disabled' ?>><?= icon('check-check') ?>Submit Official Application</button>
      </div>
    </form>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
