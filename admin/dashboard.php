<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$inst = db_one('SELECT COUNT(*) total, SUM(status="pending") pending, SUM(status="approved") approved, SUM(submission_status="submitted") submitted FROM institutions');
$teams = db_one('SELECT COUNT(*) total, SUM(status="pending") pending, SUM(status="approved") approved, COALESCE(SUM(CASE WHEN status<>"rejected" THEN fee END),0) fee FROM teams');
$students = (int) db_value('SELECT COUNT(*) FROM team_members m JOIN teams t ON t.id = m.team_id WHERE t.status <> "rejected"');
$pay = db_one('SELECT COALESCE(SUM(amount),0) received, COALESCE(SUM(CASE WHEN status="approved" THEN COALESCE(approved_amount,amount) END),0) approved, SUM(status="pending") pending FROM payments');
$food = db_one('SELECT COALESCE(SUM(quantity),0) qty, COALESCE(SUM(amount),0) amount FROM food_requests');
$payable = (float) $teams['fee'] + (float) $food['amount'];

$eventRows = db_all(
    'SELECT e.name, e.code, e.registration_fee, COUNT(t.id) teams, SUM(t.status="approved") approved, SUM(t.status="pending") pending, COUNT(DISTINCT t.institution_id) schools
       FROM events e LEFT JOIN teams t ON t.event_id = e.id AND t.status <> "rejected"
   GROUP BY e.id ORDER BY e.sort_order, e.name'
);
$recent = db_all('SELECT * FROM institutions ORDER BY created_at DESC LIMIT 8');

$pageTitle = 'Admin Dashboard';
require __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
  <div>
    <h2 class="text-xl font-black text-slate-900">Event Control Room</h2>
    <p class="text-xs text-slate-500"><?= e(setting('event_title')) ?> • <?= e(setting('event_dates')) ?> • Registrations <?= registration_is_open() ? '<span class="text-emerald-600 font-bold">OPEN</span>' : '<span class="text-rose-600 font-bold">CLOSED</span>' ?></p>
  </div>
  <a href="<?= url('admin/reports') ?>" class="btn-primary self-start"><?= icon('file-bar-chart') ?>Generate Reports</a>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
  <?= stat_card('Institutions', (string) $inst['total'], 'school', 'cyan', (int) $inst['pending'] . ' pending approval • ' . (int) $inst['submitted'] . ' submitted') ?>
  <?= stat_card('Team Entries', (string) $teams['total'], 'users-2', 'teal', (int) $teams['pending'] . ' pending • ' . (int) $teams['approved'] . ' approved • ' . $students . ' students') ?>
  <?= stat_card('Fund Received', inr($pay['received']), 'wallet-cards', 'purple', (int) $pay['pending'] . ' transfer(s) awaiting verification') ?>
  <?= stat_card('Fund Approved', inr($pay['approved']), 'badge-indian-rupee', 'emerald', 'Total payable ' . inr($payable), 'text-emerald-700') ?>
</div>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
  <a href="<?= url('admin/institutions', ['status' => 'pending']) ?>" class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-center justify-between hover:border-amber-400 transition">
    <span class="text-xs font-bold uppercase tracking-wider text-amber-800">Institution Requests Pending</span><span class="text-2xl font-black text-amber-700"><?= (int) $inst['pending'] ?></span>
  </a>
  <a href="<?= url('admin/teams', ['status' => 'pending']) ?>" class="bg-cyan-50 border border-cyan-200 rounded-xl p-4 flex items-center justify-between hover:border-cyan-400 transition">
    <span class="text-xs font-bold uppercase tracking-wider text-cyan-800">Team Entries to Approve</span><span class="text-2xl font-black text-cyan-700"><?= (int) $teams['pending'] ?></span>
  </a>
  <a href="<?= url('admin/payments', ['status' => 'pending']) ?>" class="bg-purple-50 border border-purple-200 rounded-xl p-4 flex items-center justify-between hover:border-purple-400 transition">
    <span class="text-xs font-bold uppercase tracking-wider text-purple-800">Fund Requests to Verify</span><span class="text-2xl font-black text-purple-700"><?= (int) $pay['pending'] ?></span>
  </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
  <div class="lg:col-span-7 card overflow-hidden">
    <div class="card-head"><h3 class="card-title">Event-wise Participation</h3><span class="text-xs text-slate-500">Food coupons: <?= (int) $food['qty'] ?> (<?= inr($food['amount']) ?>)</span></div>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Event</th><th class="!text-right">Fee</th><th class="!text-right">Schools</th><th class="!text-right">Teams</th><th class="!text-right">Pending</th><th class="!text-right">Approved</th></tr></thead>
        <tbody>
          <?php foreach ($eventRows as $r): ?>
            <tr>
              <td class="font-semibold"><?= e($r['name']) ?> <span class="font-mono text-[10px] text-slate-400"><?= e($r['code']) ?></span></td>
              <td class="text-right"><?= inr($r['registration_fee']) ?></td>
              <td class="text-right"><?= (int) $r['schools'] ?></td>
              <td class="text-right font-bold"><?= (int) $r['teams'] ?></td>
              <td class="text-right text-amber-600"><?= (int) $r['pending'] ?></td>
              <td class="text-right text-emerald-600"><?= (int) $r['approved'] ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$eventRows): ?><tr><td colspan="6" class="text-center text-slate-400 py-6">No events configured.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="lg:col-span-5 card overflow-hidden">
    <div class="card-head"><h3 class="card-title">Recent Registrations</h3><a href="<?= url('admin/institutions') ?>" class="text-xs font-semibold text-cyan-700 hover:underline">View all</a></div>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($recent as $r): ?>
        <li>
          <a href="<?= url('admin/institution', ['id' => $r['id']]) ?>" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-slate-50">
            <div class="min-w-0">
              <p class="text-xs font-bold text-slate-800 truncate"><?= e($r['name']) ?></p>
              <p class="text-[11px] text-slate-400 font-mono"><?= e($r['affiliation_code']) ?> • <?= e(fmt_date($r['created_at'])) ?></p>
            </div>
            <?= status_badge($r['status']) ?>
          </a>
        </li>
      <?php endforeach; ?>
      <?php if (!$recent): ?><li class="px-5 py-8 text-center text-xs text-slate-400">No institutions registered yet.</li><?php endif; ?>
    </ul>
  </div>
</div>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
