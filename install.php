<?php
/**
 * Browser installer: /install
 * Works only when INSTALL_KEY is set in .env and no super admin exists yet.
 * Creates the tables, seed data, the hidden super admin and (optionally) the first administrator.
 */
require __DIR__ . '/includes/bootstrap.php';

$key = (string) env('INSTALL_KEY', '');
$installed = install_has_superadmin();
$errors = [];
$done = false;

if (!$installed && $key !== '' && strlen($key) >= 12 && is_post()) {
    verify_csrf();
    if (!hash_equals($key, (string) ($_POST['install_key'] ?? ''))) {
        $errors[] = 'The install key does not match INSTALL_KEY in .env.';
    } else {
        install_schema();
        $users = [[ROLE_SUPERADMIN, 'super']];
        if (trim((string) ($_POST['admin_email'] ?? '')) !== '') {
            $users[] = [ROLE_ADMIN, 'admin'];
        }
        foreach ($users as [$role, $prefix]) {
            $pass = (string) ($_POST["{$prefix}_pass"] ?? '');
            if ($pass !== (string) ($_POST["{$prefix}_pass_confirm"] ?? '')) {
                $errors[] = ucfirst($role) . ': passwords do not match.';
                continue;
            }
            if ($err = install_user($role, (string) ($_POST["{$prefix}_email"] ?? ''), $pass, (string) ($_POST["{$prefix}_name"] ?? ''))) {
                $errors[] = $err;
            }
        }
        $done = install_has_superadmin();
        if ($done) {
            log_activity('installed', 'Web installer');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Install | Science Fair Portal</title>
  <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="bg-slate-50 bg-grid-pattern min-h-screen flex items-center justify-center p-6 text-slate-800 font-sans">
  <div class="w-full max-w-xl bg-white border border-slate-200 rounded-2xl shadow-xl overflow-hidden">
    <div class="bg-gradient-to-r from-slate-900 to-cyan-950 px-6 py-5 text-white">
      <span class="text-[10px] font-bold tracking-widest text-cyan-400 uppercase">One-time setup</span>
      <h1 class="text-lg font-bold">Science Fair Portal Installer</h1>
    </div>
    <div class="p-6 space-y-4 text-sm">
      <?php if ($done): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-4">Installation complete. Remove <span class="font-mono">INSTALL_KEY</span> from <span class="font-mono">.env</span>, then sign in.</div>
        <a href="<?= url('') ?>" class="btn-dark w-full">Go to Sign In</a>
      <?php elseif ($installed): ?>
        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">The portal is already installed. This page is disabled.</div>
        <a href="<?= url('') ?>" class="btn-dark w-full">Go to Sign In</a>
      <?php elseif ($key === '' || strlen($key) < 12): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-4 space-y-2">
          <p>To use the browser installer, add a secret of at least 12 characters to <span class="font-mono">.env</span>:</p>
          <pre class="font-mono text-xs bg-white border border-amber-200 rounded p-2">INSTALL_KEY=choose-a-long-random-secret</pre>
          <p>Then reload this page. (The database connection already works.)</p>
        </div>
      <?php else: ?>
        <?php foreach ($errors as $err): ?><div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-lg px-3 py-2 text-xs"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post" class="space-y-4">
          <?= csrf_field() ?>
          <div><label class="form-label">Install Key (INSTALL_KEY from .env) *</label><input type="password" name="install_key" required class="form-input"></div>
          <fieldset class="border border-slate-200 rounded-xl p-4 space-y-3">
            <legend class="px-1 text-xs font-bold uppercase tracking-wider text-cyan-700">Super Admin (hidden) *</legend>
            <input name="super_name" placeholder="Name" value="Super Admin" class="form-input">
            <input type="email" name="super_email" required placeholder="Email (login ID)" class="form-input">
            <div class="grid grid-cols-2 gap-3"><input type="password" name="super_pass" required minlength="8" placeholder="Password" class="form-input"><input type="password" name="super_pass_confirm" required minlength="8" placeholder="Retype" class="form-input"></div>
          </fieldset>
          <fieldset class="border border-slate-200 rounded-xl p-4 space-y-3">
            <legend class="px-1 text-xs font-bold uppercase tracking-wider text-slate-600">First Administrator (optional)</legend>
            <input name="admin_name" placeholder="Name" value="Event Administrator" class="form-input">
            <input type="email" name="admin_email" placeholder="Email (login ID)" class="form-input">
            <div class="grid grid-cols-2 gap-3"><input type="password" name="admin_pass" minlength="8" placeholder="Password" class="form-input"><input type="password" name="admin_pass_confirm" minlength="8" placeholder="Retype" class="form-input"></div>
          </fieldset>
          <p class="text-[11px] text-slate-500">Passwords: at least 8 characters with letters and numbers.</p>
          <button class="btn-primary w-full">Install</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
