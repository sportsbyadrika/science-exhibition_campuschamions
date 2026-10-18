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
    'Header & Footer Branding' => [
        'brand_badge_text' => ['Header Badge Text (type "none" to hide)', 'text'],
        'brand_badge_url' => ['Header Badge Link (optional, https://…)', 'url'],
        'footer_title' => ['Footer Title', 'text'],
        'footer_tagline' => ['Footer Tagline (type "none" to hide)', 'text'],
        'footer_copyright' => ['Footer Copyright Text (after © year)', 'text'],
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
    'Bank Account & UPI QR Code (shown on Fund Transfer page)' => [
        'bank_account_name' => ['Account Name', 'text'],
        'bank_name_branch' => ['Bank & Branch', 'text'],
        'bank_account_no' => ['Account Number', 'text'],
        'bank_ifsc' => ['IFSC Code', 'text'],
        'bank_upi' => ['UPI ID', 'text'],
        'upi_qr_mode' => ['UPI QR Code on Fund Transfer page', ['auto' => 'Generate from UPI ID (amount pre-filled)', 'upload' => 'Show uploaded QR image (below)', 'off' => 'Do not show a QR code']],
        'upi_payee_name' => ['UPI Payee Name (shown in the UPI app)', 'text'],
        'upi_note' => ['UPI Payment Note (affiliation code is added)', 'text'],
    ],
    'Admin Email Notifications' => [
        'admin_notify_emails' => ['Send admin notifications to (comma-separated emails; blank = all active administrators)', 'textarea'],
        'notify_admin_registration' => ['Email admins when a new school registers', 'bool'],
        'notify_admin_payment' => ['Email admins when a fund transfer is submitted', 'bool'],
        'notify_admin_submission' => ['Email admins when a school makes its final submission (PDF attached)', 'bool'],
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
    if (input('action') === 'event_logo') {
        try {
            $path = save_data_url_image((string) ($_POST['logo_data'] ?? ''), 'logos', 3072, 'png');
            delete_upload(setting('event_logo_path') ?: null);
            save_setting('event_logo_path', $path);
            log_activity('event_logo_updated');
            flash('success', 'Event logo updated. It now appears in the headers, home page and PDF reports.');
        } catch (RuntimeException $ex) {
            flash('error', 'Event logo: ' . $ex->getMessage() . ' Please choose and crop the image again.');
        }
        redirect('admin/settings');
    }
    if (input('action') === 'upi_qr') {
        try {
            if (($_FILES['upi_qr']['type'] ?? '') === 'application/pdf') {
                throw new RuntimeException('Please upload the QR code as a JPG or PNG image.');
            }
            $path = save_uploaded_file($_FILES['upi_qr'] ?? [], 'qr', 2048);
            if (str_ends_with($path, '.pdf')) {
                delete_upload($path);
                throw new RuntimeException('Please upload the QR code as a JPG or PNG image.');
            }
            delete_upload(setting('upi_qr_path') ?: null);
            save_setting('upi_qr_path', $path);
            if (upi_qr_mode() === 'off') {
                save_setting('upi_qr_mode', 'upload');
            }
            log_activity('upi_qr_updated');
            flash('success', 'UPI QR code image uploaded.');
        } catch (RuntimeException $ex) {
            flash('error', 'UPI QR: ' . $ex->getMessage());
        }
        redirect('admin/settings');
    }
    if (input('action') === 'remove_upi_qr') {
        delete_upload(setting('upi_qr_path') ?: null);
        save_setting('upi_qr_path', '');
        flash('success', 'Uploaded UPI QR code removed.');
        redirect('admin/settings');
    }
    if (input('action') === 'remove_event_logo') {
        delete_upload(setting('event_logo_path') ?: null);
        save_setting('event_logo_path', '');
        log_activity('event_logo_removed');
        flash('success', 'Event logo removed. The default icon is shown again.');
        redirect('admin/settings');
    }
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
$extraHead = '<link rel="stylesheet" href="' . asset('vendor/cropper.min.css') . '">';
$extraScripts = '<script src="' . asset('vendor/cropper.min.js') . '"></script><script src="' . asset('js/logo-crop.js') . '"></script>'
    . '<script src="' . asset('vendor/qrcode.js') . '"></script><script src="' . asset('js/upi-qr.js') . '"></script>';
require __DIR__ . '/../includes/layout/app_header.php';
require __DIR__ . '/../includes/layout/settings_tabs.php';
$eventLogo = event_logo_url();
?>
<!-- Event logo (separate forms: upload is cropped in the browser) -->
<div class="card p-6 mb-6 flex flex-col sm:flex-row sm:items-center gap-5">
  <div class="w-28 h-28 shrink-0 rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 flex items-center justify-center overflow-hidden">
    <img id="logoPreview" src="<?= e($eventLogo) ?>" alt="" class="w-full h-full object-contain <?= $eventLogo ? '' : 'hidden' ?>">
    <span id="logoPlaceholder" class="text-slate-400 text-[10px] text-center flex flex-col items-center <?= $eventLogo ? 'hidden' : '' ?>"><?= icon('image', 'w-7 h-7 mb-1') ?>No Logo</span>
  </div>
  <div class="flex-1 space-y-2">
    <h3 class="card-title flex items-center"><?= icon('image', 'w-4 h-4 mr-2 text-cyan-700') ?>Event Logo</h3>
    <p class="text-xs text-slate-500">Shown in the header of every page, beside the main heading on the home page, in PDF report headers and as the browser tab icon. Square crop; transparent PNG backgrounds are kept.</p>
    <div class="flex flex-wrap items-center gap-3">
      <form method="post" id="logoForm">
        <?= csrf_field() ?><input type="hidden" name="action" value="event_logo"><input type="hidden" name="logo_data" id="logoData">
        <input type="file" id="logoFile" accept="image/png,image/jpeg,image/webp" class="hidden">
        <button type="button" id="logoBtn" class="btn-primary btn-sm"><?= icon('crop', 'w-3.5 h-3.5') ?><?= $eventLogo ? 'Change Logo' : 'Upload &amp; Crop Logo' ?></button>
      </form>
      <?php if ($eventLogo): ?>
        <form method="post" data-confirm="Remove the event logo?"><?= csrf_field() ?><input type="hidden" name="action" value="remove_event_logo"><button class="btn-light btn-sm !text-rose-600"><?= icon('trash-2', 'w-3.5 h-3.5') ?>Remove</button></form>
      <?php endif; ?>
    </div>
  </div>
</div>

<form method="post" class="space-y-6">
  <?= csrf_field() ?>
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <?php foreach ($groups as $title => $fields): ?>
      <div class="card p-6 space-y-4 self-start">
        <h3 class="card-title"><?= e($title) ?></h3>
        <?php if ($title === 'Admin Email Notifications'): $rcpt = admin_notification_recipients(); ?>
          <p class="text-[11px] text-slate-600 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2">
            Currently sent to: <span class="font-mono"><?= $rcpt ? e(implode(', ', $rcpt)) : 'no recipients' ?></span>
            <?php if (setting('mail_enabled', '0') !== '1'): ?><br><span class="text-amber-700 font-semibold">Email sending is switched off – enable "Send Emails" below.</span><?php endif; ?>
          </p>
        <?php endif; ?>
        <?php if (array_filter(array_keys($fields), 'setting_locked')): ?>
          <p class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">Some fields are set in the server's <span class="font-mono">.env</span> file and cannot be changed here.</p>
        <?php endif; ?>
        <?php foreach ($fields as $key => [$label, $type]): $val = match ($key) { 'auto_approve_institutions' => auto_approve_institutions() ? '1' : '0', 'suite_title' => app_title(),
            'upi_qr_mode' => upi_qr_mode(),
            'upi_payee_name' => setting($key, setting('bank_account_name')),
            'upi_note' => setting($key, 'Science Fair'),
            'brand_badge_text' => setting($key, 'Powered by SportsMIS.com'),
            'footer_title' => setting($key, setting('organisation_name', 'CBSE South Zone Sahodaya Complex') . ' Science Fair'),
            'footer_tagline' => setting($key, 'Official Secretariat'),
            'footer_copyright' => setting($key, app_title()), 'notify_admin_registration', 'notify_admin_payment', 'notify_admin_submission' => admin_notification_enabled($key) ? '1' : '0', default => setting($key) }; $lock = setting_locked($key) ? ' disabled' : ''; ?>
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
        <?php if (str_starts_with($title, 'Bank Account')): $qrImg = upi_qr_image_url(); $qrMode = upi_qr_mode(); ?>
          <!-- UPI QR preview + upload (buttons submit the small forms below the main form) -->
          <div class="border-t border-slate-200 pt-4 flex flex-col sm:flex-row sm:items-start gap-4">
            <div class="w-28 h-28 shrink-0 rounded-xl border-2 border-dashed border-slate-300 bg-white flex items-center justify-center overflow-hidden p-1">
              <?php if ($qrMode === 'auto' && setting('bank_upi') !== ''): ?>
                <div id="settingsQr" class="w-full h-full [&_svg]:w-full [&_svg]:h-full" data-uri="<?= e(upi_pay_uri(0, setting('upi_note', 'Science Fair'))) ?>"></div>
              <?php elseif ($qrImg): ?>
                <img src="<?= e($qrImg) ?>" alt="UPI QR" class="w-full h-full object-contain">
              <?php else: ?>
                <span class="text-slate-400 text-[10px] text-center flex flex-col items-center"><?= icon('qr-code', 'w-7 h-7 mb-1') ?>No QR</span>
              <?php endif; ?>
            </div>
            <div class="flex-1 space-y-2">
              <p class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center"><?= icon('qr-code', 'w-4 h-4 mr-1.5 text-cyan-700') ?>UPI QR Preview</p>
              <p class="text-[11px] text-slate-500">
                Current mode: <strong><?= e(['auto' => 'Generated from UPI ID ' . setting('bank_upi'), 'upload' => 'Uploaded QR image', 'off' => 'Not shown'][$qrMode]) ?></strong>.
                In generated mode each school's QR already contains its balance amount and affiliation code. To use the QR from your bank / UPI app, upload it and choose "Show uploaded QR image".
              </p>
              <?php if ($qrMode === 'auto' && setting('bank_upi') === ''): ?><p class="text-[11px] text-amber-700 font-semibold">Enter the UPI ID above and save to generate the QR code.</p><?php endif; ?>
              <?php if ($qrMode === 'upload' && !$qrImg): ?><p class="text-[11px] text-amber-700 font-semibold">Upload mode is selected but no QR image is uploaded yet.</p><?php endif; ?>
              <div class="flex flex-wrap items-center gap-2">
                <label class="btn-light btn-sm cursor-pointer"><?= icon('upload', 'w-3.5 h-3.5') ?><?= $qrImg ? 'Replace QR Image' : 'Upload QR Image' ?>
                  <input type="file" name="upi_qr" form="upiQrForm" accept="image/png,image/jpeg" class="hidden" onchange="this.form.submit()">
                </label>
                <?php if ($qrImg): ?>
                  <button type="submit" form="removeUpiQrForm" class="btn-light btn-sm !text-rose-600"><?= icon('trash-2', 'w-3.5 h-3.5') ?>Remove</button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="flex flex-wrap justify-end gap-2 sticky bottom-4">
    <button type="submit" name="action" value="test_mail" class="btn-light shadow" formnovalidate><?= icon('mail-check') ?>Send Test Email</button>
    <button type="submit" class="btn-primary"><?= icon('save') ?>Save Settings</button>
  </div>
</form>
<!-- Stand-alone forms used by the UPI QR buttons inside the Bank Account panel -->
<form id="upiQrForm" method="post" enctype="multipart/form-data" class="hidden"><?= csrf_field() ?><input type="hidden" name="action" value="upi_qr"></form>
<form id="removeUpiQrForm" method="post" class="hidden" data-confirm="Remove the uploaded QR image?"><?= csrf_field() ?><input type="hidden" name="action" value="remove_upi_qr"></form>
<?php
$cropTitle = 'Crop Event Logo';
$cropSize = 512;
require __DIR__ . '/../includes/partials/logo_crop_modal.php';
require __DIR__ . '/../includes/layout/app_footer.php';
