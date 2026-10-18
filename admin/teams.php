<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

if (is_post()) {
    verify_csrf();
    handle_admin_action();
    redirect_back('admin/teams');
}

$status = (string) input('status');
$eventId = input_int('event_id');
$categoryId = input_int('category_id');
$q = (string) input('q');
$where = ['1=1'];
$params = [];
if (in_array($status, ['pending', 'approved', 'rejected'], true)) { $where[] = 't.status = ?'; $params[] = $status; }
if ($eventId) { $where[] = 't.event_id = ?'; $params[] = $eventId; }
if ($categoryId) { $where[] = 't.category_id = ?'; $params[] = $categoryId; }
if ($q !== '') {
    $where[] = '(i.name LIKE ? OR i.affiliation_code LIKE ? OR t.team_code LIKE ? OR t.project_title LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
$w = implode(' AND ', $where);
$from = 'FROM teams t JOIN events e ON e.id = t.event_id JOIN categories c ON c.id = t.category_id JOIN institutions i ON i.id = t.institution_id';
$total = (int) db_value("SELECT COUNT(*) $from WHERE $w", $params);
[$offset, $pager] = paginate($total, 30);
$teams = db_all("SELECT t.*, e.name event_name, c.name category_name, i.name institution_name, i.affiliation_code $from WHERE $w ORDER BY t.created_at DESC LIMIT 30 OFFSET $offset", $params);
attach_members($teams);

$events = db_all('SELECT id, name FROM events ORDER BY sort_order, name');
$cats = db_all('SELECT id, name FROM categories ORDER BY sort_order, name');
$pageTitle = 'Team Entries';
require __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="card overflow-hidden">
  <div class="card-head">
    <div><h2 class="card-title">Submitted Team Entries</h2><p class="text-xs text-slate-500">Approve or reject institution-wise team entries. Select multiple rows for bulk action.</p></div>
    <a href="<?= url('admin/report', ['type' => 'teams', 'event_id' => $eventId, 'category_id' => $categoryId, 'status' => $status]) ?>" target="_blank" class="btn-light btn-sm"><?= icon('printer', 'w-3.5 h-3.5') ?>PDF of this list</a>
  </div>
  <form class="flex flex-wrap gap-2 px-5 py-3 border-b border-slate-200 bg-slate-50">
    <select name="event_id" class="form-input-sm !w-auto"><?= select_options(array_column($events, 'name', 'id'), $eventId, true, 'All Events') ?></select>
    <select name="category_id" class="form-input-sm !w-auto"><?= select_options(array_column($cats, 'name', 'id'), $categoryId, true, 'All Categories') ?></select>
    <select name="status" class="form-input-sm !w-auto"><?= select_options(['' => 'All Status', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'], $status) ?></select>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Institution / team code / team name" class="form-input-sm !w-60">
    <button class="btn-dark btn-sm"><?= icon('filter', 'w-3.5 h-3.5') ?>Filter</button>
  </form>

  <form method="post" id="bulkForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="team_status">
    <input type="hidden" name="remarks" value="">
    <div class="flex flex-wrap items-center gap-2 px-5 py-2 border-b border-slate-200 text-xs">
      <span class="text-slate-500">With selected:</span>
      <button name="status" value="approved" class="btn-success btn-sm" onclick="return confirm('Approve selected team entries?')"><?= icon('check', 'w-3.5 h-3.5') ?>Approve</button>
      <button name="status" value="rejected" class="btn-danger btn-sm" onclick="var r = prompt('Reason for rejection:'); if (r === null) return false; this.form.remarks.value = r; return true;"><?= icon('x', 'w-3.5 h-3.5') ?>Reject</button>
    </div>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.row-check').forEach(c => c.checked = this.checked)"></th><th>Team</th><th>Institution</th><th>Event / Category / Team Name</th><th>Students</th><th class="!text-right">Fee</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($teams as $t): ?>
          <tr>
            <td><input type="checkbox" class="row-check" name="ids[]" value="<?= (int) $t['id'] ?>"></td>
            <td class="font-mono font-bold text-cyan-800 whitespace-nowrap"><?= e($t['team_code']) ?><span class="block font-sans font-normal text-[10px] text-slate-400"><?= e(fmt_date($t['created_at'])) ?></span></td>
            <td><a href="<?= url('admin/institution', ['id' => $t['institution_id']]) ?>" class="font-semibold hover:text-cyan-700"><?= e($t['institution_name']) ?></a><span class="block text-[10px] text-slate-400 font-mono"><?= e($t['affiliation_code']) ?></span></td>
            <td><span class="font-semibold"><?= e($t['event_name']) ?></span> • <?= e($t['category_name']) ?><span class="block text-[11px] text-slate-500"><?= e($t['project_title']) ?></span></td>
            <td>
              <?php foreach ($t['members'] as $m): ?>
                <div class="flex items-center gap-2 mb-1">
                  <?php if ($m['photo_path']): ?><img src="<?= media_url('photo', (int) $m['id']) ?>" class="w-7 h-9 object-cover rounded border border-slate-200" alt="" loading="lazy"><?php endif; ?>
                  <span><?= e($m['name']) ?> <span class="text-[10px] text-slate-400">(<?= e($m['class_name']) ?>)</span></span>
                </div>
              <?php endforeach; ?>
            </td>
            <td class="text-right"><?= inr($t['fee']) ?></td>
            <td><?= status_badge($t['status']) ?><?php if ($t['remarks']): ?><span class="block text-[10px] text-slate-500"><?= e($t['remarks']) ?></span><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$teams): ?><tr><td colspan="7" class="text-center text-slate-400 py-10">No team entries found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </form>
  <?= $pager ?>
</div>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
