<?php
/**
 * Institution profile: edit institution / head / coordinator details, logo (cropped) and password.
 */
require __DIR__ . '/../includes/bootstrap.php';
[$user, $inst] = require_institution();

if (is_post()) {
    verify_csrf();
    $action = (string) input('action');

    if ($action === 'profile') {
        $d = [
            'name'              => (string) input('name'),
            'address'           => (string) input('address'),
            'district'          => (string) input('district'),
            'head_name'         => (string) input('head_name'),
            'head_designation'  => (string) input('head_designation'),
            'head_email'        => mb_strtolower((string) input('head_email')),
            'head_phone'        => preg_replace('/\D/', '', (string) input('head_phone')),
            'coord_name'        => (string) input('coord_name'),
            'coord_designation' => (string) input('coord_designation'),
            'coord_email'       => mb_strtolower((string) input('coord_email')),
            'coord_phone'       => preg_replace('/\D/', '', (string) input('coord_phone')),
        ];
        remember_input($d);
        $errors = [];
        if (mb_strlen($d['name']) < 5) $errors[] = 'Enter the full official name of the institution.';
        if ($d['head_name'] === '' || !in_array($d['head_designation'], head_designations(), true)) $errors[] = 'Enter the Head of Institution name and designation.';
        if (!valid_email($d['head_email'])) $errors[] = 'Enter a valid email ID for the Head of Institution.';
        if (!valid_phone($d['head_phone'])) $errors[] = 'Enter a valid 10-digit contact number for the Head of Institution.';
        if ($d['coord_name'] === '' || $d['coord_designation'] === '') $errors[] = 'Enter the coordinator name and designation.';
        if (!valid_email($d['coord_email'])) $errors[] = 'Enter a valid coordinator email ID.';
        if (!valid_phone($d['coord_phone'])) $errors[] = 'Enter a valid 10-digit coordinator contact number.';

        $emailChanged = $d['coord_email'] !== mb_strtolower($user['email']);
        if ($emailChanged) {
            // The coordinator email is the login ID: confirm with the current password and keep it unique
            if (!password_verify((string) ($_POST['confirm_password'] ?? ''), $user['password_hash'])) {
                $errors[] = 'Enter your current password to change the coordinator email (login ID).';
            } elseif (db_value('SELECT id FROM users WHERE email = ? AND id <> ?', [$d['coord_email'], $user['id']])) {
                $errors[] = 'This email ID is already used by another account.';
            }
        }

        if ($errors) {
            foreach ($errors as $err) flash('error', $err);
            redirect('institution/profile', ['edit' => 1]);
        }
        foreach (['address', 'district'] as $k) {
            $d[$k] = mb_substr($d[$k], 0, $k === 'address' ? 255 : 100) ?: null;
        }
        db_exec(
            'UPDATE institutions SET name = ?, address = ?, district = ?, head_name = ?, head_designation = ?, head_email = ?, head_phone = ?,
                    coord_name = ?, coord_designation = ?, coord_email = ?, coord_phone = ? WHERE id = ?',
            [...array_values($d), $inst['id']]
        );
        db_exec('UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?', [$d['coord_name'], $d['coord_email'], $d['coord_phone'], $user['id']]);
        clear_old();
        log_activity('profile_updated', $emailChanged ? 'login email changed to ' . $d['coord_email'] : '');
        flash('success', 'Institution profile updated.' . ($emailChanged ? ' Use ' . $d['coord_email'] . ' as your Login ID from now on.' : ''));
        redirect('institution/profile');
    }

    if ($action === 'logo') {
        try {
            $path = save_data_url_image((string) ($_POST['logo_data'] ?? ''), 'logos', 2048, 'png');
            delete_upload($inst['logo_path'] ?? null);
            db_exec('UPDATE institutions SET logo_path = ? WHERE id = ?', [$path, $inst['id']]);
            log_activity('logo_updated');
            flash('success', 'Institution logo updated.');
        } catch (RuntimeException $ex) {
            flash('error', 'Logo: ' . $ex->getMessage() . ' Please choose and crop the image again.');
        }
        redirect('institution/profile');
    }

    if ($action === 'remove_logo') {
        delete_upload($inst['logo_path'] ?? null);
        db_exec('UPDATE institutions SET logo_path = NULL WHERE id = ?', [$inst['id']]);
        log_activity('logo_removed');
        flash('success', 'Institution logo removed.');
        redirect('institution/profile');
    }

    if ($action === 'password') {
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $user['password_hash'])) {
            flash('error', 'Current password is incorrect.');
        } elseif ($p = password_problem((string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirm'] ?? ''))) {
            flash('error', $p);
        } else {
            db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash((string) $_POST['password'], PASSWORD_DEFAULT), $user['id']]);
            log_activity('password_changed');
            flash('success', 'Password changed successfully.');
        }
        redirect('institution/profile');
    }
    redirect('institution/profile');
}

$editing = isset($_GET['edit']);
$v = fn (string $k) => e(old($k, (string) ($inst[$k] ?? '')));
$logoUrl = !empty($inst['logo_path']) ? media_url('logo', (int) $inst['id'], ['v' => substr(md5($inst['logo_path']), 0, 8)]) : '';

$pageTitle = 'Institution Profile';
$extraHead = '<link rel="stylesheet" href="' . asset('vendor/cropper.min.css') . '">';
$extraScripts = '<script src="' . asset('vendor/cropper.min.js') . '"></script><script src="' . asset('js/logo-crop.js') . '"></script>';
require __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
  <!-- Profile -->
  <div class="lg:col-span-8 card overflow-hidden">
    <div class="card-head">
      <div class="flex items-center gap-3">
        <?php if ($logoUrl): ?><img src="<?= $logoUrl ?>" alt="" class="w-12 h-12 rounded-xl border border-slate-200 object-contain bg-white"><?php endif; ?>
        <div><h2 class="card-title">Institution Profile</h2><p class="text-xs text-slate-500">Ref. <span class="font-mono"><?= e($inst['ref_no']) ?></span> • Aff. Code <span class="font-mono"><?= e($inst['affiliation_code']) ?></span></p></div>
      </div>
      <div class="flex items-center gap-2">
        <?= status_badge($inst['status']) ?>
        <?php if (!$editing): ?><a href="<?= url('institution/profile', ['edit' => 1]) ?>" class="btn-primary btn-sm"><?= icon('pencil', 'w-3.5 h-3.5') ?>Edit Profile</a><?php endif; ?>
      </div>
    </div>

    <?php if (!$editing): ?>
      <dl class="divide-y divide-slate-100 text-xs">
        <?php foreach ([
            'Institution' => $inst['name'],
            'Address' => trim(($inst['address'] ?? '') . (($inst['district'] ?? '') ? ', ' . $inst['district'] : ''), ', ') ?: '—',
            'Head of Institution' => $inst['head_name'] . ' (' . $inst['head_designation'] . ')',
            'Head Email / Phone' => $inst['head_email'] . ' • ' . $inst['head_phone'],
            'Coordinator (e-Admin)' => $inst['coord_name'] . ' (' . $inst['coord_designation'] . ')',
            'Coordinator Email (Login ID)' => $inst['coord_email'],
            'Coordinator Phone' => $inst['coord_phone'],
            'Registered On' => fmt_datetime($inst['created_at']),
            'Approved On' => fmt_datetime($inst['approved_at']),
        ] as $k => $val): ?>
          <div class="grid grid-cols-3 gap-3 px-5 py-3"><dt class="text-slate-500 font-semibold uppercase tracking-wider text-[11px]"><?= e($k) ?></dt><dd class="col-span-2 font-semibold text-slate-800 break-words"><?= e($val) ?></dd></div>
        <?php endforeach; ?>
      </dl>
    <?php else: ?>
      <form method="post" class="p-5 space-y-6">
        <?= csrf_field() ?><input type="hidden" name="action" value="profile">
        <div>
          <h3 class="text-xs font-bold uppercase tracking-wider text-cyan-700 mb-3">Institution</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2"><label class="form-label">Name of Institution *</label><input name="name" required maxlength="255" value="<?= $v('name') ?>" class="form-input"></div>
            <div><label class="form-label">Affiliation Code</label><input value="<?= e($inst['affiliation_code']) ?>" disabled class="form-input font-mono"><p class="form-hint">Contact the Event Admin to change the affiliation code.</p></div>
            <div><label class="form-label">District</label><input name="district" maxlength="100" value="<?= $v('district') ?>" class="form-input"></div>
            <div class="sm:col-span-2"><label class="form-label">Address</label><input name="address" maxlength="255" value="<?= $v('address') ?>" class="form-input"></div>
          </div>
        </div>
        <div>
          <h3 class="text-xs font-bold uppercase tracking-wider text-teal-700 mb-3">Head of Institution</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="form-label">Name *</label><input name="head_name" required maxlength="150" value="<?= $v('head_name') ?>" class="form-input"></div>
            <div><label class="form-label">Designation *</label><select name="head_designation" class="form-input"><?= select_options(array_combine(head_designations(), head_designations()), old('head_designation', $inst['head_designation'])) ?></select></div>
            <div><label class="form-label">Official Email *</label><input type="email" name="head_email" required value="<?= $v('head_email') ?>" class="form-input"></div>
            <div><label class="form-label">Contact Number *</label><input name="head_phone" required maxlength="10" pattern="[0-9]{10}" value="<?= $v('head_phone') ?>" class="form-input"></div>
          </div>
        </div>
        <div>
          <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-700 mb-3">Coordinator (e-Admin)</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div><label class="form-label">Name *</label><input name="coord_name" required maxlength="150" value="<?= $v('coord_name') ?>" class="form-input"></div>
            <div><label class="form-label">Designation *</label><input name="coord_designation" required maxlength="100" value="<?= $v('coord_designation') ?>" class="form-input"></div>
            <div><label class="form-label">Email (Login ID) *</label><input type="email" id="coord_email" name="coord_email" required value="<?= $v('coord_email') ?>" data-original="<?= e($inst['coord_email']) ?>" class="form-input"></div>
            <div><label class="form-label">Contact Number *</label><input name="coord_phone" required maxlength="10" pattern="[0-9]{10}" value="<?= $v('coord_phone') ?>" class="form-input"></div>
            <div id="emailConfirm" class="sm:col-span-2 hidden bg-amber-50 border border-amber-200 rounded-xl p-3">
              <label class="form-label">Current Password (required to change the Login ID)</label>
              <input type="password" name="confirm_password" autocomplete="current-password" class="form-input">
            </div>
          </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 pt-4">
          <a href="<?= url('institution/profile') ?>" class="btn-light">Cancel</a>
          <button class="btn-primary"><?= icon('save') ?>Save Profile</button>
        </div>
      </form>
      <script>
        (function () {
          var email = document.getElementById('coord_email'), box = document.getElementById('emailConfirm');
          function toggle() { box.classList.toggle('hidden', email.value.trim().toLowerCase() === email.dataset.original.toLowerCase()); }
          email.addEventListener('input', toggle); toggle();
        })();
      </script>
    <?php endif; ?>
  </div>

  <div class="lg:col-span-4 space-y-6">
    <!-- Logo -->
    <div class="card p-6 space-y-4">
      <h2 class="card-title flex items-center"><?= icon('image', 'w-4 h-4 mr-2 text-cyan-700') ?>Institution Logo</h2>
      <div class="flex items-center gap-4">
        <div class="w-24 h-24 shrink-0 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 flex items-center justify-center overflow-hidden">
          <img id="logoPreview" src="<?= $logoUrl ?>" alt="" class="w-full h-full object-contain <?= $logoUrl ? '' : 'hidden' ?>">
          <span id="logoPlaceholder" class="text-slate-400 text-[10px] text-center flex flex-col items-center <?= $logoUrl ? 'hidden' : '' ?>"><?= icon('school', 'w-6 h-6 mb-1') ?>No Logo</span>
        </div>
        <div class="space-y-2">
          <form method="post" id="logoForm">
            <?= csrf_field() ?><input type="hidden" name="action" value="logo"><input type="hidden" name="logo_data" id="logoData">
            <input type="file" id="logoFile" accept="image/png,image/jpeg,image/webp" class="hidden">
            <button type="button" id="logoBtn" class="btn-soft btn-sm"><?= icon('crop', 'w-3.5 h-3.5') ?>Upload &amp; Crop</button>
          </form>
          <?php if ($logoUrl): ?>
            <form method="post" data-confirm="Remove the institution logo?"><?= csrf_field() ?><input type="hidden" name="action" value="remove_logo"><button class="text-[11px] text-rose-600 hover:underline">Remove logo</button></form>
          <?php endif; ?>
          <p class="text-[11px] text-slate-400">Square crop, PNG/JPG. Transparent PNG backgrounds are kept.</p>
        </div>
      </div>
    </div>

    <!-- Password -->
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

<!-- MODAL: LOGO CROPPER -->
<div id="logoCropModal" class="hidden fixed inset-0 z-50 bg-black/75 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl">
    <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-slate-900 text-white">
      <h3 class="text-sm font-bold flex items-center"><?= icon('crop', 'w-4 h-4 mr-2 text-cyan-400') ?>Crop Institution Logo</h3>
      <button type="button" data-logo-close class="text-slate-400 hover:text-white"><?= icon('x', 'w-5 h-5') ?></button>
    </div>
    <div class="p-4 bg-[repeating-conic-gradient(#e2e8f0_0_25%,#f8fafc_0_50%)] bg-[length:20px_20px] max-h-[60vh] flex items-center justify-center overflow-hidden">
      <img id="logoCropTarget" src="" alt="Crop area" class="max-w-full max-h-[50vh]">
    </div>
    <div class="p-4 border-t border-slate-200 flex items-center justify-between bg-slate-50">
      <span class="text-[11px] text-slate-500 font-mono">Square • saved at 400 × 400 px</span>
      <div class="flex space-x-2">
        <button type="button" data-logo-close class="px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200 rounded-lg">Cancel</button>
        <button type="button" id="logoApply" class="px-4 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white text-xs font-bold rounded-lg shadow flex items-center"><?= icon('check', 'w-3.5 h-3.5 mr-1') ?>Crop &amp; Save</button>
      </div>
    </div>
  </div>
</div>
<?php clear_old(); require __DIR__ . '/../includes/layout/app_footer.php';
