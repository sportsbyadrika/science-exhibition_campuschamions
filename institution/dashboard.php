<?php
require __DIR__ . '/../includes/bootstrap.php';
[$user, $inst] = require_institution();

$summary = institution_summary((int) $inst['id']);
$teams = institution_teams((int) $inst['id']);
$eventCounts = [];
foreach ($teams as $t) {
    $eventCounts[$t['event_name']][$t['category_name']] = ($eventCounts[$t['event_name']][$t['category_name']] ?? 0) + 1;
}
$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/layout/app_header.php';
$ps = $summary['pay_status'];
?>
<!-- METRIC SUMMARY CARDS -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
  <?= stat_card('Registered Teams', (string) $summary['teams_total'], 'boxes', 'cyan', $summary['students'] . ' students • ' . $summary['teams_approved'] . ' approved') ?>
  <?= stat_card('Food Coupons', (string) $summary['food_qty'], 'utensils', 'amber', 'Amount ' . inr($summary['food_amount'])) ?>
  <?= stat_card('Total Payable', inr($summary['payable']), 'receipt', 'emerald', 'Includes Teams + Meals', 'text-emerald-700') ?>
  <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-xs">
    <div class="flex items-center justify-between">
      <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Payment Status</span>
      <span class="p-2 bg-purple-50 text-purple-600 rounded-lg"><?= icon('shield-check') ?></span>
    </div>
    <div class="mt-2"><span class="text-sm font-extrabold uppercase tracking-wide <?= tone_text($ps['tone']) ?>"><?= e($ps['label']) ?></span></div>
    <p class="text-[11px] text-slate-400 mt-1">Approved <?= inr($summary['paid_approved']) ?> • Balance <?= inr($summary['balance']) ?></p>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
  <!-- Registration progress -->
  <div class="lg:col-span-7 card p-6 space-y-5">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-bold text-slate-900">Registration Progress</h2>
        <p class="text-xs text-slate-500 mt-0.5">Complete each step to finalise your institution's participation.</p>
      </div>
      <span class="text-2xl font-black text-cyan-700"><?= $summary['progress'] ?>%</span>
    </div>
    <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
      <div class="h-full rounded-full bg-gradient-to-r from-cyan-500 to-teal-500" style="width: <?= $summary['progress'] ?>%"></div>
    </div>
    <?php
    $links = ['registration' => 'institution/profile', 'teams' => 'institution/teams', 'food' => 'institution/food-coupons', 'payment' => 'institution/payments', 'submitted' => 'institution/submit'];
    ?>
    <ol class="space-y-3">
      <?php foreach ($summary['steps'] as $i => $step): ?>
        <li>
          <a href="<?= url($links[$step['key']]) ?>" class="flex items-center gap-3 p-3 rounded-xl border <?= $step['done'] ? 'border-emerald-200 bg-emerald-50/50' : 'border-slate-200 hover:border-cyan-300' ?> transition">
            <span class="w-8 h-8 shrink-0 rounded-full flex items-center justify-center text-xs font-bold <?= $step['done'] ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-500' ?>">
              <?= $step['done'] ? icon('check') : $i + 1 ?>
            </span>
            <span class="flex-1 text-sm font-semibold <?= $step['done'] ? 'text-emerald-800' : 'text-slate-700' ?>"><?= e($step['label']) ?></span>
            <span class="text-[11px] font-bold uppercase tracking-wider <?= $step['done'] ? 'text-emerald-600' : 'text-slate-400' ?>"><?= $step['done'] ? 'Completed' : 'Pending' ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ol>
    <?php if (institution_locked($inst)): ?>
      <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-xs text-emerald-900 flex items-start gap-2">
        <?= icon('badge-check', 'w-4 h-4 mt-0.5') ?>
        <span>Application finally submitted on <strong><?= e(fmt_datetime($inst['submitted_at'])) ?></strong> (Ref: <span class="font-mono font-bold"><?= e($inst['submission_ref']) ?></span>). Entries are locked; contact the secretariat for changes.</span>
      </div>
    <?php endif; ?>
  </div>

  <!-- Fee invoice & quick actions -->
  <div class="lg:col-span-5 space-y-6">
    <div class="card p-6 space-y-4">
      <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 flex items-center"><?= icon('calculator', 'w-4 h-4 mr-2 text-cyan-700') ?>Consolidated Fee Invoice</h3>
      <div class="space-y-2 text-xs divide-y divide-slate-100">
        <div class="flex justify-between pt-1"><span class="text-slate-600">Team Entries (<?= $summary['teams_total'] - $summary['teams_rejected'] ?> teams)</span><span class="font-bold text-slate-900"><?= inr($summary['team_fee']) ?></span></div>
        <div class="flex justify-between pt-2"><span class="text-slate-600">Food Coupons (<?= $summary['food_qty'] ?> coupons)</span><span class="font-bold text-slate-900"><?= inr($summary['food_amount']) ?></span></div>
        <div class="flex justify-between pt-2"><span class="text-slate-600">Fund Transfers Submitted</span><span class="font-bold text-slate-900"><?= inr($summary['paid_submitted']) ?></span></div>
        <div class="flex justify-between pt-3 text-sm"><span class="font-extrabold text-slate-900">Total Remittance Amount</span><span class="font-black text-cyan-800 text-base"><?= inr($summary['payable']) ?></span></div>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <?php foreach ([
          ['institution/team-form', 'Add Team', 'user-plus', 'bg-cyan-50 text-cyan-700'],
          ['institution/food-coupons', 'Food Coupons', 'utensils', 'bg-amber-50 text-amber-700'],
          ['institution/payments', 'Fund Transfer', 'wallet-cards', 'bg-emerald-50 text-emerald-700'],
          ['institution/reports', 'Reports', 'printer', 'bg-indigo-50 text-indigo-700'],
      ] as [$p, $l, $i, $c]): ?>
        <a href="<?= url($p) ?>" class="bg-white border border-slate-200 rounded-xl p-4 hover:border-cyan-300 transition flex items-center gap-3">
          <span class="p-2 rounded-lg <?= $c ?>"><?= icon($i) ?></span>
          <span class="text-xs font-bold uppercase tracking-wider text-slate-700"><?= $l ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Event wise team count -->
<div class="card mt-6 overflow-hidden">
  <div class="card-head">
    <div>
      <h3 class="card-title">Event-wise Entries</h3>
      <p class="text-xs text-slate-500">Count of teams registered by event and category.</p>
    </div>
    <a href="<?= url('institution/teams') ?>" class="btn-soft btn-sm">View All Teams <?= icon('arrow-right', 'w-3.5 h-3.5') ?></a>
  </div>
  <?php if (!$eventCounts): ?>
    <div class="p-10 text-center text-xs text-slate-400">No teams registered yet.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Event</th><th>Category</th><th class="!text-right">Teams</th></tr></thead>
        <tbody>
          <?php foreach ($eventCounts as $ev => $cats): foreach ($cats as $cat => $n): ?>
            <tr><td class="font-semibold"><?= e($ev) ?></td><td><?= e($cat) ?></td><td class="text-right font-mono font-bold"><?= $n ?></td></tr>
          <?php endforeach; endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
