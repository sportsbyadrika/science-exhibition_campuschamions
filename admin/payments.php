<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

if (is_post()) {
    verify_csrf();
    handle_admin_action();
    redirect_back('admin/payments');
}

$status = (string) input('status');
$q = (string) input('q');
$where = ['1=1'];
$params = [];
if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
    $where[] = 'p.status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[] = '(i.name LIKE ? OR i.affiliation_code LIKE ? OR p.utr_no LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
$w = implode(' AND ', $where);
$total = (int) db_value("SELECT COUNT(*) FROM payments p JOIN institutions i ON i.id = p.institution_id WHERE $w", $params);
[$offset, $pager] = paginate($total);
$payments = db_all(
    "SELECT p.*, i.name institution_name, i.affiliation_code FROM payments p JOIN institutions i ON i.id = p.institution_id WHERE $w ORDER BY p.status = 'pending' DESC, p.created_at DESC LIMIT 25 OFFSET $offset",
    $params
);
$tot = db_one('SELECT COALESCE(SUM(amount),0) received, COALESCE(SUM(CASE WHEN status="approved" THEN COALESCE(approved_amount,amount) END),0) approved, COALESCE(SUM(CASE WHEN status="pending" THEN amount END),0) pending, COALESCE(SUM(CASE WHEN status="rejected" THEN amount END),0) rejected FROM payments');
$showInstitution = true;

$pageTitle = 'Fund Requests';
require __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <?= stat_card('Fund Received', inr($tot['received']), 'wallet-cards', 'cyan', 'All fund transfer submissions') ?>
  <?= stat_card('Approved', inr($tot['approved']), 'badge-check', 'emerald', 'Verified by secretariat', 'text-emerald-700') ?>
  <?= stat_card('Pending', inr($tot['pending']), 'hourglass', 'amber', 'Awaiting verification') ?>
  <?= stat_card('Rejected', inr($tot['rejected']), 'ban', 'rose', 'Returned to institution') ?>
</div>
<div class="card overflow-hidden">
  <div class="card-head">
    <div><h2 class="card-title">Fund Transfer Verification</h2><p class="text-xs text-slate-500">Match UTR numbers with the bank statement and approve.</p></div>
    <form class="flex flex-wrap gap-2">
      <select name="status" class="form-input-sm !w-auto" onchange="this.form.submit()"><?= select_options(['' => 'All Status', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'], $status) ?></select>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search institution / UTR" class="form-input-sm !w-56">
      <button class="btn-dark btn-sm"><?= icon('search', 'w-3.5 h-3.5') ?></button>
    </form>
  </div>
  <?php require __DIR__ . '/../includes/partials/payments_table.php'; ?>
  <?= $pager ?>
</div>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
