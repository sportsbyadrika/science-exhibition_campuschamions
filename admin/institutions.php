<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

if (is_post()) {
    verify_csrf();
    handle_admin_action();
    redirect_back('admin/institutions');
}

$status = (string) input('status');
$q = (string) input('q');
$where = ['1=1'];
$params = [];
if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
    $where[] = 'i.status = ?';
    $params[] = $status;
}
if ($status === 'submitted') {
    $where[] = 'i.submission_status = "submitted"';
}
if ($q !== '') {
    $where[] = '(i.name LIKE ? OR i.affiliation_code LIKE ? OR i.coord_email LIKE ? OR i.ref_no LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
$w = implode(' AND ', $where);
$total = (int) db_value("SELECT COUNT(*) FROM institutions i WHERE $w", $params);
[$offset, $pager] = paginate($total);
$rows = db_all(
    "SELECT i.*,
            (SELECT COUNT(*) FROM teams t WHERE t.institution_id = i.id AND t.status <> 'rejected') teams,
            (SELECT COALESCE(SUM(CASE WHEN status='approved' THEN COALESCE(approved_amount,amount) END),0) FROM payments p WHERE p.institution_id = i.id) paid
       FROM institutions i WHERE $w ORDER BY i.created_at DESC LIMIT 25 OFFSET $offset",
    $params
);

$pageTitle = 'Institutions';
require __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2 rounded-xl border px-4 py-3 text-xs <?= auto_approve_institutions() ? 'bg-cyan-50 border-cyan-200 text-cyan-900' : 'bg-amber-50 border-amber-200 text-amber-900' ?>">
  <span class="flex items-center gap-2"><?= icon(auto_approve_institutions() ? 'zap' : 'hand', 'w-4 h-4') ?>
    <?= auto_approve_institutions()
        ? '<strong>Auto-approval is ON.</strong> New participation requests are approved immediately. Use <strong>Reject</strong> to revoke an institution\'s access at any time (it is signed out at once and notified by email).'
        : '<strong>Auto-approval is OFF.</strong> New participation requests wait here for manual approval.' ?>
  </span>
  <a href="<?= url('admin/settings') ?>" class="font-semibold underline whitespace-nowrap">Change in Settings</a>
</div>
<div class="card overflow-hidden">
  <div class="card-head">
    <div>
      <h2 class="card-title">Participating Institutions</h2>
      <p class="text-xs text-slate-500">Approve participation requests and review each institution's registration.</p>
    </div>
    <form class="flex flex-wrap gap-2">
      <select name="status" class="form-input-sm !w-auto" onchange="this.form.submit()">
        <?= select_options(['' => 'All Status', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'submitted' => 'Finally Submitted'], $status) ?>
      </select>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name / code / email" class="form-input-sm !w-56">
      <button class="btn-dark btn-sm"><?= icon('search', 'w-3.5 h-3.5') ?></button>
    </form>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Institution</th><th>Coordinator</th><th>Head</th><th class="!text-right">Teams</th><th class="!text-right">Approved Fund</th><th>Status</th><th class="!text-right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td>
            <a href="<?= url('admin/institution', ['id' => $r['id']]) ?>" class="font-bold text-slate-900 hover:text-cyan-700"><?= e($r['name']) ?></a>
            <span class="block text-[11px] text-slate-400 font-mono"><?= e($r['affiliation_code']) ?> • <?= e($r['ref_no']) ?></span>
          </td>
          <td><?= e($r['coord_name']) ?><span class="block text-[11px] text-slate-400"><?= e($r['coord_email']) ?> • <?= e($r['coord_phone']) ?></span></td>
          <td><?= e($r['head_name']) ?><span class="block text-[11px] text-slate-400"><?= e($r['head_phone']) ?></span></td>
          <td class="text-right font-bold"><?= (int) $r['teams'] ?></td>
          <td class="text-right"><?= inr($r['paid']) ?></td>
          <td class="space-y-1"><?= status_badge($r['status']) ?><?php if ($r['status'] === 'approved' && !$r['approved_by']): ?><span class="block text-[10px] text-cyan-700 font-semibold">Auto-approved</span><?php elseif ($r['status'] === 'rejected' && $r['status_remarks']): ?><span class="block text-[10px] text-rose-600 max-w-[10rem] truncate" title="<?= e($r['status_remarks']) ?>"><?= e($r['status_remarks']) ?></span><?php endif; ?><br><?= status_badge($r['submission_status']) ?></td>
          <td class="text-right whitespace-nowrap">
            <div class="inline-flex gap-1">
              <?php if ($r['status'] !== 'approved'): ?>
                <form method="post" data-confirm="Approve this institution's participation request?">
                  <?= csrf_field() ?><input type="hidden" name="action" value="institution_status"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="status" value="approved">
                  <button class="btn-success btn-sm" title="Approve"><?= icon('check', 'w-3.5 h-3.5') ?></button>
                </form>
              <?php endif; ?>
              <?php if ($r['status'] !== 'rejected'): ?>
                <form method="post" data-prompt="Reason for rejection (sent to the institution):">
                  <?= csrf_field() ?><input type="hidden" name="action" value="institution_status"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="status" value="rejected"><input type="hidden" name="remarks">
                  <button class="btn-danger btn-sm" title="Reject"><?= icon('x', 'w-3.5 h-3.5') ?></button>
                </form>
              <?php endif; ?>
              <a href="<?= url('admin/institution', ['id' => $r['id']]) ?>" class="btn-light btn-sm" title="View"><?= icon('eye', 'w-3.5 h-3.5') ?></a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-slate-400 py-10">No institutions found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= $pager ?>
</div>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
