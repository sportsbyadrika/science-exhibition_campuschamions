<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$items = db_all(
    'SELECT f.*, COALESCE(SUM(r.quantity),0) qty, COALESCE(SUM(r.amount),0) amt, COUNT(CASE WHEN r.quantity > 0 THEN 1 END) schools
       FROM food_items f LEFT JOIN food_requests r ON r.food_item_id = f.id GROUP BY f.id ORDER BY f.sort_order, f.id'
);
$rows = db_all('SELECT r.*, i.name, i.affiliation_code, i.submission_status FROM food_requests r JOIN institutions i ON i.id = r.institution_id WHERE r.quantity > 0 ORDER BY i.name');
$matrix = [];
foreach ($rows as $r) {
    $matrix[$r['institution_id']]['info'] = $r;
    $matrix[$r['institution_id']]['q'][$r['food_item_id']] = (int) $r['quantity'];
    $matrix[$r['institution_id']]['amt'] = ($matrix[$r['institution_id']]['amt'] ?? 0) + (float) $r['amount'];
}

$pageTitle = 'Food Requests';
require __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?php foreach ($items as $f): ?>
    <?= stat_card($f['name'], (string) $f['qty'], 'utensils', 'amber', inr($f['amt']) . ' • ' . $f['schools'] . ' institutions • @ ' . inr($f['rate'])) ?>
  <?php endforeach; ?>
</div>
<div class="card overflow-hidden">
  <div class="card-head">
    <div><h2 class="card-title">Institution-wise Food Coupon Requests</h2><p class="text-xs text-slate-500">Head-wise coupon counts requested by each institution.</p></div>
    <a href="<?= url('admin/report', ['type' => 'food']) ?>" target="_blank" class="btn-light btn-sm"><?= icon('printer', 'w-3.5 h-3.5') ?>PDF Summary</a>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Institution</th><?php foreach ($items as $f): ?><th class="!text-right"><?= e($f['name']) ?></th><?php endforeach; ?><th class="!text-right">Amount</th><th>Submission</th></tr></thead>
      <tbody>
        <?php foreach ($matrix as $instId => $m): ?>
          <tr>
            <td><a href="<?= url('admin/institution', ['id' => $instId]) ?>" class="font-semibold hover:text-cyan-700"><?= e($m['info']['name']) ?></a><span class="block text-[10px] text-slate-400 font-mono"><?= e($m['info']['affiliation_code']) ?></span></td>
            <?php foreach ($items as $f): ?><td class="text-right font-mono"><?= $m['q'][$f['id']] ?? '–' ?></td><?php endforeach; ?>
            <td class="text-right font-bold"><?= inr($m['amt']) ?></td>
            <td><?= status_badge($m['info']['submission_status']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$matrix): ?><tr><td colspan="<?= count($items) + 3 ?>" class="text-center text-slate-400 py-10">No food coupon requests yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
