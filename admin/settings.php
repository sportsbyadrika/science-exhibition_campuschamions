<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

$groups = [
    'Event & Branding' => [
        'organisation_name' => ['Organisation Name', 'text'],
        'suite_title' => ['Application Title (all headers)', 'text'],
        'event_title' => ['Event Title', 'text'],
        'event_dates' => ['Event Dates (display)', 'text'],
        'event_host' => ['Host Chapter', 'text'],
        'event_venue' => ['Venue', 'text'],
    ],
    'Registration' => [
        'registration_open' => ['Registrations Open', 'bool'],
        'auto_approve_institutions' => ['Auto-approve participation requests (admins can still reject them later)', 'bool'],
        'registration_closes' => ['Registration Closes On', 'datetime-local'],
    ],
    'Notice & Support Desk' => [
        'notice_text' => ['Notice Strip Text', 'textarea'],
        'support_phone' => ['Support Phone', 'text'],
        'support_email' => ['Support Email', 'email'],
    ],
    'Bank Account (shown on Fund Transfer page)' => [
        'bank_account_name' => ['Account Name', 'text'],
        'bank_name_branch' => ['Bank & Branch', 'text'],
        'bank_account_no' => ['Account Number', 'text'],
        'bank_ifsc' => ['IFSC Code', 'text'],
        'bank_upi' => ['UPI ID', 'text'],
    ],
    'Email (PHPMailer SMTP)' => [
        'mail_enabled' => ['Send Emails', 'bool'],
        'smtp_host' => ['SMTP Host (blank = PHP mail())', 'text'],
        'smtp_port' => ['SMTP Port', 'number'],
        'smtp_secure' => ['Encryption', ['tls' => 'STARTTLS (587)', 'ssl' => 'SSL/TLS (465)', 'none' => 'None']],
        'smtp_username' => ['SMTP Username', 'text'],
        'smtp_password' => ['SMTP Password', 'password'],
        'mail_from_email' => ['From Email', 'email'],
        'mail_from_name' => ['From Name', 'text'],
    ],
];

if (is_post()) {
    verify_csrf();
    if (input('action') === 'test_mail') {
        $err = null;
        $ok = send_mail([$me['email'] => $me['name']], 'Test Email', '<p>This is a test email from the Science Fair portal. Your SMTP settings are working.</p>', [], $err);
        flash($ok ? 'success' : 'error', $ok ? 'Test email sent to ' . $me['email'] . '.' : 'Email failed: ' . $err);
        redirect('admin/settings');
    }
    foreach ($groups as $fields) {
        foreach ($fields as $key => [$label, $type]) {
            if (setting_locked($key)) {
                continue; // controlled by .env
            }
            if ($type === 'bool') {
                save_setting($key, isset($_POST[$key]) ? '1' : '0');
            } elseif ($type === 'password') {
                if ((string) ($_POST[$key] ?? '') !== '') {
                    save_setting($key, (string) $_POST[$key]);
                }
            } elseif ($type === 'datetime-local') {
                $v = (string) input($key);
                save_setting($key, $v !== '' && strtotime($v) ? date('Y-m-d H:i', strtotime($v)) : '');
            } else {
                save_setting($key, mb_substr((string) input($key), 0, 1000));
            }
        }
    }
    log_activity('settings_updated');
    flash('success', 'Settings saved.');
    redirect('admin/settings');
}

$pageTitle = 'Settings';
require __DIR__ . '/../includes/layout/app_header.php';
require __DIR__ . '/../includes/layout/settings_tabs.php';
?>
<form method="post" class="space-y-6">
  <?= csrf_field() ?>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <?php foreach ($groups as $title => $fields): ?>
      <div class="card p-6 space-y-4 self-start">
        <h3 class="card-title"><?= e($title) ?></h3>
        <?php if (array_filter(array_keys($fields), 'setting_locked')): ?>
          <p class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">Some fields are set in the server's <span class="font-mono">.env</span> file and cannot be changed here.</p>
        <?php endif; ?>
        <?php foreach ($fields as $key => [$label, $type]): $val = match ($key) { 'auto_approve_institutions' => auto_approve_institutions() ? '1' : '0', 'suite_title' => app_title(), default => setting($key) }; $lock = setting_locked($key) ? ' disabled' : ''; ?>
          <?php if ($type === 'bool'): ?>
            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700"><input type="checkbox" name="<?= e($key) ?>" value="1" <?= $val === '1' ? 'checked' : '' ?><?= $lock ?> class="rounded border-slate-300 text-cyan-600"><?= e($label) ?></label>
          <?php else: ?>
            <div>
              <label class="form-label" for="<?= e($key) ?>"><?= e($label) ?></label>
              <?php if (is_array($type)): ?>
                <select id="<?= e($key) ?>" name="<?= e($key) ?>" class="form-input"<?= $lock ?>><?= select_options($type, $val) ?></select>
              <?php elseif ($type === 'textarea'): ?>
                <textarea id="<?= e($key) ?>" name="<?= e($key) ?>" rows="2" class="form-input"><?= e($val) ?></textarea>
              <?php elseif ($type === 'password'): ?>
                <input type="password" id="<?= e($key) ?>" name="<?= e($key) ?>"<?= $lock ?> autocomplete="new-password" placeholder="<?= $val !== '' ? '•••••••• (saved – leave blank to keep)' : '' ?>" class="form-input">
              <?php elseif ($type === 'datetime-local'): ?>
                <input type="datetime-local" id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= $val ? e(date('Y-m-d\TH:i', strtotime($val))) : '' ?>" class="form-input">
              <?php else: ?>
                <input type="<?= e($type) ?>" id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($val) ?>" class="form-input"<?= $lock ?>>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="flex flex-wrap justify-end gap-2 sticky bottom-4">
    <button type="submit" name="action" value="test_mail" class="btn-light shadow" formnovalidate><?= icon('mail-check') ?>Send Test Email</button>
    <button type="submit" class="btn-primary"><?= icon('save') ?>Save Settings</button>
  </div>
</form>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
