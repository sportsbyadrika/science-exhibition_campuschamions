<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$inst = db_one('SELECT * FROM institutions WHERE id = ?', [input_int('id')]);
if (!$inst) {
    abort(404);
}
if (is_post()) {
    verify_csrf();
    handle_admin_action();
    redirect('admin/institution', ['id' => $inst['id']]);
}

$summary = institution_summary((int) $inst['id']);
$teams = institution_teams((int) $inst['id']);
$food = institution_food((int) $inst['id']);
$payments = institution_payments((int) $inst['id']);
$account = db_one('SELECT * FROM users WHERE institution_id = ? LIMIT 1', [$inst['id']]);

$pageTitle = $inst['name'];
$activeTab = 'admin/institutions';
require __DIR__ . '/../includes/layout/app_header.php';

$hidden = fn (string $action, array $extra = []) => csrf_field() . '<input type="hidden" name="action" value="' . e($action) . '">'
    . implode('', array_map(fn ($k, $v) => '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">', array_keys($extra), $extra));
?>
<div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4 mb-6">
  <div>
    <a href="<?= url('admin/institutions') ?>" class="text-xs text-cyan-700 hover:underline flex items-center mb-1"><?= icon('chevron-left', 'w-3.5 h-3.5') ?>Back to Institutions</a>
    <h2 class="text-xl font-black text-slate-900"><?= e($inst['name']) ?></h2>
    <p class="text-xs text-slate-500 font-mono">Aff. <?= e($inst['affiliation_code']) ?> • <?= e($inst['ref_no']) ?> • Registered <?= e(fmt_datetime($inst['created_at'])) ?></p>
    <div class="flex gap-2 mt-2"><?= status_badge($inst['status']) ?><?= status_badge($inst['submission_status']) ?></div>
  </div>
  <div class="flex flex-wrap gap-2">
    <a href="<?= url('admin/report', ['type' => 'application', 'id' => $inst['id']]) ?>" target="_blank" class="btn-light"><?= icon('printer') ?>Application PDF</a>
    <?php if ($inst['status'] !== 'approved'): ?>
      <form method="post" data-confirm="Approve this institution?"><?= $hidden('institution_status', ['id' => $inst['id'], 'status' => 'approved']) ?><button class="btn-success"><?= icon('check') ?>Approve</button></form>
    <?php endif; ?>
    <?php if ($inst['status'] !== 'rejected'): ?>
      <form method="post" data-prompt="Reason for rejection:"><?= $hidden('institution_status', ['id' => $inst['id'], 'status' => 'rejected', 'remarks' => '']) ?><button class="btn-danger"><?= icon('x') ?>Reject</button></form>
    <?php endif; ?>
    <?php if ($inst['submission_status'] === 'submitted'): ?>
      <form method="post" data-confirm="Reopen the submission so the institution can edit entries?"><?= $hidden('reopen_submission', ['id' => $inst['id']]) ?><button class="btn-soft"><?= icon('unlock') ?>Reopen Submission</button></form>
    <?php endif; ?>
  </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?= stat_card('Teams', (string) $summary['teams_total'], 'boxes', 'cyan', $summary['teams_approved'] . ' approved • ' . $summary['teams_pending'] . ' pending') ?>
  <?= stat_card('Food Coupons', (string) $summary['food_qty'], 'utensils', 'amber', inr($summary['food_amount'])) ?>
  <?= stat_card('Total Payable', inr($summary['payable']), 'receipt', 'emerald', 'Submitted ' . inr($summary['paid_submitted']), 'text-emerald-700') ?>
  <?= stat_card('Fund Approved', inr($summary['paid_approved']), 'shield-check', 'purple', $summary['pay_status']['label']) ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
  <div class="lg:col-span-7 card overflow-hidden">
    <div class="card-head"><h3 class="card-title">Institution Details</h3>
      <?php if ($account): ?><span class="text-[11px] text-slate-500">Login: <span class="font-mono"><?= e($account['email']) ?></span> • <?= status_badge($account['status']) ?></span><?php endif; ?>
    </div>
    <dl class="divide-y divide-slate-100 text-xs">
      <?php foreach ([
          'Head of Institution' => $inst['head_name'] . ' (' . $inst['head_designation'] . ') • ' . $inst['head_phone'] . ' • ' . $inst['head_email'],
          'Coordinator (e-Admin)' => $inst['coord_name'] . ' (' . $inst['coord_designation'] . ') • ' . $inst['coord_phone'] . ' • ' . $inst['coord_email'],
          'Approval' => match ($inst['status']) {
              'approved' => ($inst['approved_by'] ? 'Approved' : 'Auto-approved') . ' on ' . fmt_datetime($inst['approved_at']),
              'rejected' => 'Rejected' . ($inst['status_remarks'] ? ' – ' . $inst['status_remarks'] : ''),
              default => 'Pending approval',
          },
          'Final Submission' => $inst['submitted_at'] ? fmt_datetime($inst['submitted_at']) . ' • ' . $inst['submission_ref'] : 'Not submitted',
      ] as $k => $v): ?>
        <div class="grid grid-cols-3 gap-3 px-5 py-3"><dt class="text-slate-500 font-semibold uppercase tracking-wider text-[11px]"><?= e($k) ?></dt><dd class="col-span-2 font-semibold text-slate-800 break-words"><?= e($v) ?></dd></div>
      <?php endforeach; ?>
    </dl>
  </div>
  <div class="lg:col-span-5 card overflow-hidden">
    <div class="card-head"><h3 class="card-title">Food Coupon Request</h3></div>
    <table class="data-table">
      <thead><tr><th>Head</th><th class="!text-right">Rate</th><th class="!text-right">Qty</th><th class="!text-right">Amount</th></tr></thead>
      <tbody>
        <?php foreach ($food as $f): if (!(int) $f['quantity']) continue; ?>
          <tr><td class="font-semibold"><?= e($f['name']) ?></td><td class="text-right"><?= inr($f['requested_rate'] ?? $f['rate']) ?></td><td class="text-right"><?= (int) $f['quantity'] ?></td><td class="text-right font-bold"><?= inr($f['amount']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$summary['food_qty']): ?><tr><td colspan="4" class="text-center text-slate-400 py-6">No food coupons requested.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card overflow-hidden mb-6">
  <div class="card-head"><h3 class="card-title">Team Entries</h3><span class="text-xs text-slate-500"><?= count($teams) ?> team(s)</span></div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Team</th><th>Event / Category</th><th>Students</th><th class="!text-right">Fee</th><th>Status</th><th class="!text-right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($teams as $t): ?>
        <tr>
          <td class="font-mono font-bold text-cyan-800"><?= e($t['team_code']) ?></td>
          <td><span class="font-semibold"><?= e($t['event_name']) ?></span> • <?= e($t['category_name']) ?><span class="block text-[11px] text-slate-500"><?= e($t['project_title']) ?></span></td>
          <td>
            <div class="flex flex-wrap gap-3">
              <?php foreach ($t['members'] as $m): ?>
                <div class="flex items-center gap-2">
                  <?php if ($m['photo_path']): ?><a href="<?= media_url('photo', (int) $m['id']) ?>" target="_blank"><img src="<?= media_url('photo', (int) $m['id']) ?>" class="w-8 h-10 object-cover rounded border border-slate-200" alt=""></a><?php endif; ?>
                  <span><?= e($m['name']) ?><span class="block text-[10px] text-slate-400"><?= e($m['class_name']) ?></span></span>
                </div>
              <?php endforeach; ?>
            </div>
          </td>
          <td class="text-right"><?= inr($t['fee']) ?></td>
          <td><?= status_badge($t['status']) ?><?php if ($t['remarks']): ?><span class="block text-[10px] text-slate-500"><?= e($t['remarks']) ?></span><?php endif; ?></td>
          <td class="text-right whitespace-nowrap">
            <div class="inline-flex gap-1">
              <?php if ($t['status'] !== 'approved'): ?><form method="post"><?= $hidden('team_status', ['id' => $t['id'], 'status' => 'approved']) ?><button class="btn-success btn-sm" title="Approve"><?= icon('check', 'w-3.5 h-3.5') ?></button></form><?php endif; ?>
              <?php if ($t['status'] !== 'rejected'): ?><form method="post" data-prompt="Reason for rejection:"><?= $hidden('team_status', ['id' => $t['id'], 'status' => 'rejected', 'remarks' => '']) ?><button class="btn-danger btn-sm" title="Reject"><?= icon('x', 'w-3.5 h-3.5') ?></button></form><?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$teams): ?><tr><td colspan="6" class="text-center text-slate-400 py-8">No team entries.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card overflow-hidden">
  <div class="card-head"><h3 class="card-title">Fund Transfers</h3><span class="text-xs text-slate-500">Balance: <?= inr($summary['balance']) ?></span></div>
  <?php require __DIR__ . '/../includes/partials/payments_table.php'; ?>
</div>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
