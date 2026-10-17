<?php
/**
 * User management. The super admin account is never listed or editable here.
 */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

$findUser = fn (int $id) => db_one('SELECT * FROM users WHERE id = ? AND role <> "superadmin"', [$id]);

if (is_post()) {
    verify_csrf();
    $action = input('action');
    $target = $findUser(input_int('id'));

    if ($action === 'save') {
        $name = (string) input('name');
        $email = mb_strtolower((string) input('email'));
        $phone = preg_replace('/\D/', '', (string) input('phone'));
        $password = (string) ($_POST['password'] ?? '');
        $errors = [];
        if ($name === '') $errors[] = 'Enter the name.';
        if (!valid_email($email)) $errors[] = 'Enter a valid email.';
        if (db_value('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $target['id'] ?? 0])) $errors[] = 'Email already in use.';
        if (!$target || $password !== '') {
            if ($p = password_problem($password, (string) ($_POST['password_confirm'] ?? ''))) $errors[] = $p;
        }
        if ($errors) {
            foreach ($errors as $err) flash('error', $err);
            redirect('admin/users', $target ? ['edit' => $target['id']] : ['add' => 1]);
        }
        if ($target) {
            db_exec('UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?', [$name, $email, $phone ?: null, $target['id']]);
            if ($password !== '') {
                db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $target['id']]);
            }
            if ($target['role'] === ROLE_INSTITUTION) {
                db_exec('UPDATE institutions SET coord_name = ?, coord_email = ?, coord_phone = COALESCE(?, coord_phone) WHERE id = ?', [$name, $email, $phone ?: null, $target['institution_id']]);
            }
            log_activity('user_updated', $email);
        } else {
            db_exec('INSERT INTO users (role, name, email, phone, password_hash) VALUES (?,?,?,?,?)', [ROLE_ADMIN, $name, $email, $phone ?: null, password_hash($password, PASSWORD_DEFAULT)]);
            log_activity('admin_created', $email);
        }
        flash('success', 'User ' . $email . ' saved.');
        redirect('admin/users', ['tab' => $target['role'] ?? ROLE_ADMIN]);
    }

    if ($action === 'toggle' && $target) {
        if ((int) $target['id'] === (int) $me['id']) {
            flash('error', 'You cannot deactivate your own account.');
        } else {
            $new = $target['status'] === 'active' ? 'inactive' : 'active';
            db_exec('UPDATE users SET status = ? WHERE id = ?', [$new, $target['id']]);
            log_activity('user_' . $new, $target['email']);
            flash('success', $target['email'] . ' is now ' . $new . '.');
        }
    }
    redirect_back('admin/users');
}

$tab = input('tab') === ROLE_INSTITUTION ? ROLE_INSTITUTION : ROLE_ADMIN;
$q = (string) input('q');
$params = [$tab];
$sql = 'SELECT u.*, i.name institution_name, i.affiliation_code FROM users u LEFT JOIN institutions i ON i.id = u.institution_id WHERE u.role = ?';
if ($q !== '') {
    $sql .= ' AND (u.name LIKE ? OR u.email LIKE ? OR i.name LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
$total = (int) db_value('SELECT COUNT(*) FROM (' . $sql . ') x', $params);
[$offset, $pager] = paginate($total);
$users = db_all($sql . " ORDER BY u.created_at DESC LIMIT 25 OFFSET $offset", $params);
$edit = input_int('edit') ? $findUser(input_int('edit')) : null;
$showForm = $edit || input('add');

$pageTitle = 'Users';
require __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
  <div class="flex gap-2">
    <a href="<?= url('admin/users') ?>" class="<?= $tab === ROLE_ADMIN ? 'btn-dark' : 'btn-light' ?> btn-sm"><?= icon('shield', 'w-3.5 h-3.5') ?>Administrators</a>
    <a href="<?= url('admin/users', ['tab' => ROLE_INSTITUTION]) ?>" class="<?= $tab === ROLE_INSTITUTION ? 'btn-dark' : 'btn-light' ?> btn-sm"><?= icon('school', 'w-3.5 h-3.5') ?>Institution Users</a>
  </div>
  <a href="<?= url('admin/users', ['add' => 1]) ?>" class="btn-primary btn-sm"><?= icon('user-plus', 'w-3.5 h-3.5') ?>Add Administrator</a>
</div>

<div class="grid grid-cols-1 <?= $showForm ? 'lg:grid-cols-12' : '' ?> gap-6">
  <div class="<?= $showForm ? 'lg:col-span-8' : '' ?> card overflow-hidden">
    <div class="card-head">
      <h2 class="card-title"><?= $tab === ROLE_ADMIN ? 'Administrators' : 'Institution Coordinator Accounts' ?></h2>
      <form class="flex gap-2"><input type="hidden" name="tab" value="<?= e($tab) ?>"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search" class="form-input-sm !w-52"><button class="btn-dark btn-sm"><?= icon('search', 'w-3.5 h-3.5') ?></button></form>
    </div>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Name</th><th>Email (Login ID)</th><?php if ($tab === ROLE_INSTITUTION): ?><th>Institution</th><?php endif; ?><th>Last Login</th><th>Status</th><th class="!text-right">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td class="font-semibold"><?= e($u['name']) ?><span class="block text-[11px] text-slate-400"><?= e($u['phone']) ?></span></td>
            <td class="font-mono text-[11px]"><?= e($u['email']) ?></td>
            <?php if ($tab === ROLE_INSTITUTION): ?><td><a class="hover:text-cyan-700" href="<?= url('admin/institution', ['id' => $u['institution_id']]) ?>"><?= e($u['institution_name']) ?></a><span class="block text-[10px] text-slate-400 font-mono"><?= e($u['affiliation_code']) ?></span></td><?php endif; ?>
            <td class="text-[11px]"><?= e(fmt_datetime($u['last_login_at'])) ?></td>
            <td><?= status_badge($u['status']) ?></td>
            <td class="text-right whitespace-nowrap">
              <a href="<?= url('admin/users', ['tab' => $tab, 'edit' => $u['id']]) ?>" class="btn-light btn-sm" title="Edit / reset password"><?= icon('pencil', 'w-3.5 h-3.5') ?></a>
              <?php if ((int) $u['id'] !== (int) $me['id']): ?>
                <form method="post" class="inline" data-confirm="<?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?> this user?">
                  <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                  <button class="btn-light btn-sm <?= $u['status'] === 'active' ? '!text-rose-600' : '!text-emerald-600' ?>" title="<?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?>"><?= icon($u['status'] === 'active' ? 'user-x' : 'user-check', 'w-3.5 h-3.5') ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$users): ?><tr><td colspan="6" class="text-center text-slate-400 py-8">No users found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?= $pager ?>
  </div>

  <?php if ($showForm): ?>
  <form method="post" class="lg:col-span-4 card p-6 space-y-4 self-start">
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
    <h3 class="card-title"><?= $edit ? 'Edit User' : 'Add Administrator' ?></h3>
    <?php if ($edit && $edit['role'] === ROLE_INSTITUTION): ?><p class="text-[11px] text-slate-500">Changes also update the institution's coordinator details.</p><?php endif; ?>
    <div><label class="form-label">Name *</label><input name="name" required value="<?= e($edit['name'] ?? '') ?>" class="form-input"></div>
    <div><label class="form-label">Email (Login ID) *</label><input type="email" name="email" required value="<?= e($edit['email'] ?? '') ?>" class="form-input"></div>
    <div><label class="form-label">Phone</label><input name="phone" maxlength="10" value="<?= e($edit['phone'] ?? '') ?>" class="form-input"></div>
    <div><label class="form-label"><?= $edit ? 'Reset Password' : 'Password *' ?></label><input type="password" name="password" <?= $edit ? '' : 'required' ?> minlength="8" autocomplete="new-password" placeholder="<?= $edit ? 'Leave blank to keep' : 'Min 8 chars, letters & numbers' ?>" class="form-input"></div>
    <div><label class="form-label">Retype Password</label><input type="password" name="password_confirm" minlength="8" autocomplete="new-password" class="form-input"></div>
    <div class="flex gap-2">
      <a href="<?= url('admin/users', ['tab' => $tab]) ?>" class="btn-light flex-1">Cancel</a>
      <button class="btn-primary flex-1"><?= icon('save') ?>Save</button>
    </div>
  </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
