<?php
require __DIR__ . '/../includes/bootstrap.php';
[$user, $inst] = require_institution();

$summary = institution_summary((int) $inst['id']);
$reports = [
    ['application', 'Application Summary', 'Complete application with institution details, teams, food coupons, fund transfers and fee statement.', 'file-text', 'bg-cyan-50 text-cyan-700'],
    ['teams', 'Team Entry List', 'Event and category wise team roster with student photographs.', 'users-2', 'bg-teal-50 text-teal-700'],
    ['food', 'Food Coupon Request', 'Head-wise food coupon quantities and amounts.', 'utensils', 'bg-amber-50 text-amber-700'],
    ['payments', 'Fund Transfer Statement', 'All fund transfers with verification status and balance.', 'wallet-cards', 'bg-emerald-50 text-emerald-700'],
];
$pageTitle = 'Reports';
require __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
  <?= stat_card('Teams', (string) $summary['teams_total'], 'boxes', 'cyan', $summary['teams_approved'] . ' approved • ' . $summary['teams_pending'] . ' pending') ?>
  <?= stat_card('Students', (string) $summary['students'], 'graduation-cap', 'teal', 'Across all team entries') ?>
  <?= stat_card('Food Coupons', (string) $summary['food_qty'], 'utensils', 'amber', inr($summary['food_amount'])) ?>
  <?= stat_card('Fund Approved', inr($summary['paid_approved']), 'badge-indian-rupee', 'emerald', 'of ' . inr($summary['payable']) . ' payable', 'text-emerald-700') ?>
</div>

<div class="card overflow-hidden">
  <div class="card-head">
    <div>
      <h2 class="card-title">Download Reports</h2>
      <p class="text-xs text-slate-500">Professionally formatted PDF reports with headers, footers and page numbers.</p>
    </div>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-5">
    <?php foreach ($reports as [$type, $title, $desc, $ic, $cls]): ?>
      <div class="border border-slate-200 rounded-xl p-5 flex items-start gap-4 hover:border-cyan-300 transition">
        <span class="p-3 rounded-xl <?= $cls ?>"><?= icon($ic, 'w-5 h-5') ?></span>
        <div class="flex-1">
          <h3 class="text-sm font-bold text-slate-900"><?= e($title) ?></h3>
          <p class="text-xs text-slate-500 mt-0.5"><?= e($desc) ?></p>
          <div class="flex gap-2 mt-3">
            <a href="<?= url('institution/report', ['type' => $type]) ?>" target="_blank" class="btn-soft btn-sm"><?= icon('eye', 'w-3.5 h-3.5') ?>View</a>
            <a href="<?= url('institution/report', ['type' => $type, 'download' => 1]) ?>" class="btn-dark btn-sm"><?= icon('download', 'w-3.5 h-3.5') ?>Download PDF</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
