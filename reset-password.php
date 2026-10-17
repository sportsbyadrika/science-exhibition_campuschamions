<?php
/**
 * Set a new password using an emailed reset token.
 */
require __DIR__ . '/includes/bootstrap.php';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$user = preg_match('/^[a-f0-9]{64}$/', $token)
    ? db_one('SELECT * FROM users WHERE reset_token_hash = ? AND reset_expires_at > NOW() AND status = "active"', [hash('sha256', $token)])
    : null;

if (!$user) {
    flash('error', 'This password reset link is invalid or has expired. Please request a new one.');
    redirect('forgot-password');
}

if (is_post()) {
    verify_csrf();
    if ($p = password_problem((string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirm'] ?? ''))) {
        flash('error', $p);
        redirect('reset-password', ['token' => $token]);
    }
    db_exec(
        'UPDATE users SET password_hash = ?, reset_token_hash = NULL, reset_expires_at = NULL WHERE id = ?',
        [password_hash((string) $_POST['password'], PASSWORD_DEFAULT), $user['id']]
    );
    log_activity('password_reset', $user['email']);
    flash('success', 'Your password has been updated. Please sign in with the new password.');
    redirect('');
}

$pageTitle = 'Reset Password';
require __DIR__ . '/includes/layout/public_header.php';
?>
<section class="max-w-md mx-auto">
  <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
    <div class="bg-gradient-to-r from-slate-900 to-cyan-950 px-6 py-5 text-white">
      <h3 class="text-lg font-bold">Set New Password</h3>
      <p class="text-xs text-slate-300 mt-0.5"><?= e($user['email']) ?></p>
    </div>
    <form method="post" class="p-6 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <div>
        <label class="form-label" for="password">New Password <span class="text-red-500">*</span></label>
        <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password" class="form-input" placeholder="Minimum 8 characters, letters & numbers">
      </div>
      <div>
        <label class="form-label" for="password_confirm">Retype Password <span class="text-red-500">*</span></label>
        <input type="password" id="password_confirm" name="password_confirm" minlength="8" required autocomplete="new-password" class="form-input">
      </div>
      <button type="submit" class="btn-primary w-full">Update Password</button>
    </form>
  </div>
</section>
<?php require __DIR__ . '/includes/layout/public_footer.php';
