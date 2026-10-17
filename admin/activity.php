<?php
/**
 * Activity log – visible to the super admin only.
 */
require __DIR__ . '/../includes/bootstrap.php';
require_superadmin();

$q = (string) input('q');
$params = [];
$where = '1=1';
if ($q !== '') {
    $where = '(l.action LIKE ? OR l.details LIKE ? OR u.email LIKE ?)';
    $params = ["%$q%", "%$q%", "%$q%"];
}
$total = (int) db_value("SELECT COUNT(*) FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id WHERE $where", $params);
[$offset, $pager] = paginate($total, 50);
$logs = db_all("SELECT l.*, u.name, u.email, u.role FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id WHERE $where ORDER BY l.id DESC LIMIT 50 OFFSET $offset", $params);

$pageTitle = 'Activity Log';
$activeTab = 'admin/settings';
require __DIR__ . '/../includes/layout/app_header.php';
require __DIR__ . '/../includes/layout/settings_tabs.php';
?>
<div class="card overflow-hidden">
  <div class="card-head">
    <div><h2 class="card-title">Activity Log</h2><p class="text-xs text-slate-500">Audit trail of sign-ins, approvals and changes.</p></div>
    <form class="flex gap-2"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search action / user" class="form-input-sm !w-56"><button class="btn-dark btn-sm"><?= icon('search', 'w-3.5 h-3.5') ?></button></form>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td class="whitespace-nowrap text-[11px]"><?= e(fmt_datetime($l['created_at'])) ?></td>
          <td><?= e($l['name'] ?? 'Guest') ?><span class="block text-[10px] text-slate-400"><?= e($l['email'] ?? '') ?> <?= $l['role'] ? '• ' . e($l['role']) : '' ?></span></td>
          <td class="font-mono text-[11px] font-bold text-cyan-800"><?= e($l['action']) ?></td>
          <td class="text-[11px] text-slate-600"><?= e($l['details']) ?></td>
          <td class="font-mono text-[10px] text-slate-400"><?= e($l['ip_address']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$logs): ?><tr><td colspan="5" class="text-center text-slate-400 py-8">No activity recorded.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= $pager ?>
</div>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
