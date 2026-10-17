<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

if (is_post()) {
    verify_csrf();
    if (!password_verify((string) ($_POST['current_password'] ?? ''), $me['password_hash'])) {
        flash('error', 'Current password is incorrect.');
    } elseif ($p = password_problem((string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirm'] ?? ''))) {
        flash('error', $p);
    } else {
        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash((string) $_POST['password'], PASSWORD_DEFAULT), $me['id']]);
        log_activity('password_changed');
        flash('success', 'Password changed successfully.');
    }
    redirect('admin/profile');
}

$pageTitle = 'My Profile';
$activeTab = '';
require __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 max-w-4xl">
  <div class="card p-6 space-y-3">
    <h2 class="card-title">My Account</h2>
    <dl class="text-xs space-y-2">
      <div><dt class="text-slate-500 uppercase font-semibold text-[11px]">Name</dt><dd class="font-bold text-slate-800"><?= e($me['name']) ?></dd></div>
      <div><dt class="text-slate-500 uppercase font-semibold text-[11px]">Login ID</dt><dd class="font-mono"><?= e($me['email']) ?></dd></div>
      <div><dt class="text-slate-500 uppercase font-semibold text-[11px]">Role</dt><dd><?= e(is_superadmin($me) ? 'Super Admin' : 'Administrator') ?></dd></div>
      <div><dt class="text-slate-500 uppercase font-semibold text-[11px]">Last Login</dt><dd><?= e(fmt_datetime($me['last_login_at'])) ?></dd></div>
    </dl>
  </div>
  <form method="post" class="card p-6 space-y-4">
    <?= csrf_field() ?>
    <h2 class="card-title flex items-center"><?= icon('key-round', 'w-4 h-4 mr-2 text-cyan-700') ?>Change Password</h2>
    <div><label class="form-label">Current Password</label><input type="password" name="current_password" required class="form-input" autocomplete="current-password"></div>
    <div><label class="form-label">New Password</label><input type="password" name="password" required minlength="8" class="form-input" autocomplete="new-password"></div>
    <div><label class="form-label">Retype New Password</label><input type="password" name="password_confirm" required minlength="8" class="form-input" autocomplete="new-password"></div>
    <button class="btn-dark w-full">Update Password</button>
  </form>
</div>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
