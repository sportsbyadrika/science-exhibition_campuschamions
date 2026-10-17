<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$events = db_all('SELECT id, name FROM events ORDER BY sort_order, name');
$cats = db_all('SELECT id, name FROM categories ORDER BY sort_order, name');
$statusOpts = ['' => 'All Status', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];

$pageTitle = 'Reports';
require __DIR__ . '/../includes/layout/app_header.php';

$card = function (string $type, string $title, string $desc, string $ic, string $cls, string $filters = '') {
    ?>
    <form action="<?= url('admin/report') ?>" target="_blank" class="card p-5 flex flex-col gap-4">
      <input type="hidden" name="type" value="<?= e($type) ?>">
      <div class="flex items-start gap-4">
        <span class="p-3 rounded-xl <?= $cls ?>"><?= icon($ic, 'w-5 h-5') ?></span>
        <div><h3 class="text-sm font-bold text-slate-900"><?= e($title) ?></h3><p class="text-xs text-slate-500 mt-0.5"><?= e($desc) ?></p></div>
      </div>
      <?php if ($filters): ?><div class="flex flex-wrap gap-2"><?= $filters ?></div><?php endif; ?>
      <div class="flex gap-2 mt-auto">
        <button class="btn-soft btn-sm"><?= icon('eye', 'w-3.5 h-3.5') ?>View PDF</button>
        <button name="download" value="1" class="btn-dark btn-sm"><?= icon('download', 'w-3.5 h-3.5') ?>Download</button>
      </div>
    </form>
    <?php
};
?>
<div class="mb-6">
  <h2 class="text-xl font-black text-slate-900">Reports</h2>
  <p class="text-xs text-slate-500">All reports are generated as PDF with the event header, footer and page numbers on every page.</p>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
  <?php
  $card('institutions', 'Participating Institutions', 'Institution list with contacts, teams, payable and approved fund.', 'school', 'bg-cyan-50 text-cyan-700',
      '<select name="status" class="form-input-sm !w-auto">' . select_options($statusOpts, '') . '</select>');
  $card('funds', 'Fund Received & Approved', 'Fund transfer register with totals received, approved, pending and rejected.', 'wallet-cards', 'bg-emerald-50 text-emerald-700',
      '<select name="status" class="form-input-sm !w-auto">' . select_options($statusOpts, '') . '</select>');
  $card('events', 'Event-wise Participation', 'Event × category matrix and team listing for every event.', 'flask-conical', 'bg-indigo-50 text-indigo-700');
  $card('teams', 'Team Entries with Photos', 'Filter by event, category and status. Includes student photographs.', 'users-2', 'bg-teal-50 text-teal-700',
      '<select name="event_id" class="form-input-sm !w-auto">' . select_options(array_column($events, 'name', 'id'), '', true, 'All Events') . '</select>'
      . '<select name="category_id" class="form-input-sm !w-auto">' . select_options(array_column($cats, 'name', 'id'), '', true, 'All Categories') . '</select>'
      . '<select name="status" class="form-input-sm !w-auto">' . select_options($statusOpts, '') . '</select>');
  $card('food', 'Food Coupon Summary', 'Head-wise totals and institution-wise coupon matrix.', 'utensils', 'bg-amber-50 text-amber-700');
  ?>
</div>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
