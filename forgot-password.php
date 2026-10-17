<?php
/**
 * Request a password reset link by email.
 */
require __DIR__ . '/includes/bootstrap.php';

if ($u = current_user()) {
    redirect(home_for($u));
}

if (is_post()) {
    verify_csrf();
    $email = mb_strtolower((string) input('email'));
    if (!captcha_verify('forgot', (string) input('captcha'))) {
        flash('error', 'Incorrect captcha code entered. Please try again.');
        redirect('forgot-password');
    }
    $user = valid_email($email) ? db_one('SELECT * FROM users WHERE email = ? AND status = "active"', [$email]) : null;
    if ($user) {
        $token = bin2hex(random_bytes(32));
        db_exec(
            'UPDATE users SET reset_token_hash = ?, reset_expires_at = DATE_ADD(NOW(), INTERVAL 60 MINUTE) WHERE id = ?',
            [hash('sha256', $token), $user['id']]
        );
        $link = absolute_url('reset-password', ['token' => $token]);
        $body = '<p>Dear ' . e($user['name']) . ',</p><p>We received a request to reset the password for your portal account <strong>' . e($user['email']) . '</strong>.</p>'
            . '<p><a href="' . e($link) . '" style="color:#0891b2;font-weight:bold">Click here to set a new password</a> (valid for 60 minutes).</p>'
            . '<p>If you did not request this, you can safely ignore this email.</p>';
        send_mail([$user['email'] => $user['name']], 'Password Reset Request', $body);
        log_activity('password_reset_requested', $email);
    }
    // Same message whether or not the account exists (prevents account enumeration)
    flash('success', 'If the email ID is registered, a password reset link has been sent to it. Please check your inbox.');
    redirect('forgot-password');
}

$pageTitle = 'Forgot Password';
require __DIR__ . '/includes/layout/public_header.php';
?>
<section class="max-w-md mx-auto">
  <div class="bg-white rounded-2xl shadow-xl shadow-slate-200/70 border border-slate-200 overflow-hidden">
    <div class="bg-gradient-to-r from-slate-900 to-cyan-950 px-6 py-5 text-white flex items-center justify-between">
      <div>
        <h3 class="text-lg font-bold">Forgot Password</h3>
        <p class="text-xs text-slate-300 mt-0.5">We will email you a secure reset link</p>
      </div>
      <div class="w-10 h-10 rounded-full bg-cyan-500/20 border border-cyan-400/30 flex items-center justify-center text-cyan-300"><?= icon('key-round', 'w-5 h-5') ?></div>
    </div>
    <form method="post" class="p-6 space-y-4">
      <?= csrf_field() ?>
      <div>
        <label class="form-label" for="email">Registered Login ID (Email) <span class="text-red-500">*</span></label>
        <div class="relative"><div class="input-icon"><?= icon('mail') ?></div>
          <input type="email" id="email" name="email" required placeholder="coordinator.science@schoolname.ac.in" class="form-input pl-9"></div>
      </div>
      <div>
        <label class="form-label" for="captcha">Security Verification <span class="text-red-500">*</span></label>
        <div class="flex items-center gap-2">
          <img id="fpCaptcha" src="<?= url('captcha', ['k' => 'forgot']) ?>" alt="Captcha" width="136" height="40" class="rounded-lg border border-slate-700 w-[136px] h-10">
          <button type="button" data-captcha-refresh="#fpCaptcha" data-captcha-input="#captcha" class="p-2 text-slate-500 hover:bg-slate-100 rounded-lg"><?= icon('refresh-cw') ?></button>
          <input type="text" id="captcha" name="captcha" required maxlength="5" autocomplete="off" placeholder="Enter code" class="form-input flex-1 min-w-0 uppercase font-mono font-bold">
        </div>
      </div>
      <button type="submit" class="btn-dark w-full">Send Reset Link <?= icon('send') ?></button>
      <a href="<?= url('') ?>" class="block text-center text-xs text-cyan-700 hover:underline">Back to Sign In</a>
    </form>
  </div>
</section>
<?php require __DIR__ . '/includes/layout/public_footer.php';
