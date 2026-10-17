<?php
require __DIR__ . '/../includes/bootstrap.php';
[$user, $inst] = require_institution();

if (is_post()) {
    verify_csrf();
    if (input('action') === 'contact') {
        $headPhone = preg_replace('/\D/', '', (string) input('head_phone'));
        $coordPhone = preg_replace('/\D/', '', (string) input('coord_phone'));
        if (!valid_phone($headPhone) || !valid_phone($coordPhone)) {
            flash('error', 'Enter valid 10-digit contact numbers.');
        } else {
            db_exec('UPDATE institutions SET head_phone = ?, coord_phone = ? WHERE id = ?', [$headPhone, $coordPhone, $inst['id']]);
            db_exec('UPDATE users SET phone = ? WHERE id = ?', [$coordPhone, $user['id']]);
            log_activity('profile_updated');
            flash('success', 'Contact numbers updated.');
        }
    } else {
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $user['password_hash'])) {
            flash('error', 'Current password is incorrect.');
        } elseif ($p = password_problem((string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirm'] ?? ''))) {
            flash('error', $p);
        } else {
            db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash((string) $_POST['password'], PASSWORD_DEFAULT), $user['id']]);
            log_activity('password_changed');
            flash('success', 'Password changed successfully.');
        }
    }
    redirect('institution/profile');
}

$pageTitle = 'Institution Profile';
require __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
  <div class="lg:col-span-7 card overflow-hidden">
    <div class="card-head">
      <div><h2 class="card-title">Institution Profile</h2><p class="text-xs text-slate-500">Registered details. Contact the secretariat to change names or email IDs.</p></div>
      <?= status_badge($inst['status']) ?>
    </div>
    <dl class="divide-y divide-slate-100 text-xs">
      <?php foreach ([
          'Reference ID' => $inst['ref_no'],
          'Affiliation Code' => $inst['affiliation_code'],
          'Institution' => $inst['name'],
          'Head of Institution' => $inst['head_name'] . ' (' . $inst['head_designation'] . ')',
          'Head Email' => $inst['head_email'],
          'Coordinator (e-Admin)' => $inst['coord_name'] . ' (' . $inst['coord_designation'] . ')',
          'Coordinator Email (Login ID)' => $inst['coord_email'],
          'Registered On' => fmt_datetime($inst['created_at']),
          'Approved On' => fmt_datetime($inst['approved_at']),
      ] as $k => $v): ?>
        <div class="grid grid-cols-3 gap-3 px-5 py-3"><dt class="text-slate-500 font-semibold uppercase tracking-wider text-[11px]"><?= e($k) ?></dt><dd class="col-span-2 font-semibold text-slate-800 break-words"><?= e($v) ?></dd></div>
      <?php endforeach; ?>
    </dl>
    <form method="post" class="border-t border-slate-200 p-5 grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
      <?= csrf_field() ?><input type="hidden" name="action" value="contact">
      <div><label class="form-label">Head Contact No.</label><input name="head_phone" value="<?= e($inst['head_phone']) ?>" maxlength="10" class="form-input-sm"></div>
      <div><label class="form-label">Coordinator Contact No.</label><input name="coord_phone" value="<?= e($inst['coord_phone']) ?>" maxlength="10" class="form-input-sm"></div>
      <button class="btn-soft">Update Contacts</button>
    </form>
  </div>

  <div class="lg:col-span-5">
    <form method="post" class="card p-6 space-y-4">
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <h2 class="card-title flex items-center"><?= icon('key-round', 'w-4 h-4 mr-2 text-cyan-700') ?>Change Password</h2>
      <div><label class="form-label">Current Password</label><input type="password" name="current_password" required class="form-input" autocomplete="current-password"></div>
      <div><label class="form-label">New Password</label><input type="password" name="password" required minlength="8" class="form-input" autocomplete="new-password"></div>
      <div><label class="form-label">Retype New Password</label><input type="password" name="password_confirm" required minlength="8" class="form-input" autocomplete="new-password"></div>
      <button class="btn-dark w-full">Update Password</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
