<?php
/**
 * Public participation request (institution self-registration).
 */
require __DIR__ . '/includes/bootstrap.php';

if ($u = current_user()) {
    redirect(home_for($u));
}

$designations = head_designations();
$errors = [];

if (is_post()) {
    verify_csrf();
    $d = [
        'affiliation_code'  => strtoupper(preg_replace('/\s+/', '', (string) input('affiliation_code'))),
        'name'              => (string) input('name'),
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

    if (!registration_is_open()) {
        $errors[] = 'Participation requests are closed.';
    }
    if (!preg_match('/^[A-Z0-9]{5,10}$/', $d['affiliation_code'])) {
        $errors[] = 'Enter a valid CBSE affiliation code.';
    }
    if (mb_strlen($d['name']) < 5) {
        $errors[] = 'Enter the full official name of the institution.';
    }
    if ($d['head_name'] === '' || !in_array($d['head_designation'], $designations, true)) {
        $errors[] = 'Enter the Head of Institution name and designation.';
    }
    if (!valid_email($d['head_email'])) {
        $errors[] = 'Enter a valid official email ID for the Head of Institution.';
    }
    if (!valid_phone($d['head_phone'])) {
        $errors[] = 'Enter a valid 10-digit contact number for the Head of Institution.';
    }
    if ($d['coord_name'] === '' || $d['coord_designation'] === '') {
        $errors[] = 'Enter the coordinator name and designation.';
    }
    if (!valid_email($d['coord_email'])) {
        $errors[] = 'Enter a valid coordinator email ID.';
    }
    if (!valid_phone($d['coord_phone'])) {
        $errors[] = 'Enter a valid 10-digit coordinator contact number.';
    }
    if ($p = password_problem((string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirm'] ?? ''))) {
        $errors[] = $p;
    }
    if (empty($_POST['declaration'])) {
        $errors[] = 'Please accept the declaration.';
    }
    if (!captcha_verify('register', (string) input('captcha'))) {
        $errors[] = 'Incorrect captcha code entered. Please try again.';
    }
    if (!$errors && db_value('SELECT id FROM institutions WHERE affiliation_code = ?', [$d['affiliation_code']])) {
        $errors[] = 'A participation request already exists for this affiliation code. Please sign in or contact the support desk.';
    }
    if (!$errors && db_value('SELECT id FROM users WHERE email = ?', [$d['coord_email']])) {
        $errors[] = 'This coordinator email ID is already registered. Please use a different email or sign in.';
    }

    if (!$errors) {
        $d['ref_no'] = 'CBSE-SZ-REQ-' . $d['affiliation_code'] . '-' . random_int(100, 999);
        $autoApprove = auto_approve_institutions();
        $pdo = db();
        $pdo->beginTransaction();
        try {
            db_exec(
                'INSERT INTO institutions (ref_no, affiliation_code, name, head_name, head_designation, head_email, head_phone,
                    coord_name, coord_designation, coord_email, coord_phone, status, status_remarks, approved_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$d['ref_no'], $d['affiliation_code'], $d['name'], $d['head_name'], $d['head_designation'], $d['head_email'], $d['head_phone'],
                 $d['coord_name'], $d['coord_designation'], $d['coord_email'], $d['coord_phone'],
                 $autoApprove ? 'approved' : 'pending', $autoApprove ? 'Auto-approved on registration' : null,
                 $autoApprove ? date('Y-m-d H:i:s') : null]
            );
            $instId = db_insert_id();
            db_exec(
                'INSERT INTO users (role, institution_id, name, email, phone, password_hash) VALUES (?,?,?,?,?,?)',
                [ROLE_INSTITUTION, $instId, $d['coord_name'], $d['coord_email'], $d['coord_phone'], password_hash((string) $_POST['password'], PASSWORD_DEFAULT)]
            );
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            error_log('[register] ' . $ex->getMessage());
            flash('error', 'Unable to save the request right now. Please try again.');
            redirect('register');
        }
        log_activity('institution_registered', $d['ref_no']);
        $inst = db_one('SELECT * FROM institutions WHERE id = ?', [$instId]);
        notify_registration($inst);
        clear_old();
        $_SESSION['reg_success'] = $instId;
        redirect('register', ['done' => 1]);
    }

    foreach ($errors as $err) {
        flash('error', $err);
    }
    redirect('register');
}

$success = null;
if (isset($_GET['done']) && !empty($_SESSION['reg_success'])) {
    $success = db_one('SELECT * FROM institutions WHERE id = ?', [$_SESSION['reg_success']]);
}

$pageTitle = 'Participation Request';
require __DIR__ . '/includes/layout/public_header.php';

if ($success): ?>
<!-- SUCCESS / ACKNOWLEDGEMENT -->
<section class="max-w-2xl mx-auto bg-white border border-slate-200 rounded-2xl p-8 shadow-2xl text-center space-y-6">
  <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto shadow-sm"><?= icon('badge-check', 'w-9 h-9') ?></div>
  <div class="space-y-2">
    <span class="text-xs uppercase tracking-wider font-bold text-emerald-600 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200">Request Received Successfully</span>
    <h2 class="text-2xl font-extrabold text-slate-900 pt-2">Participation Request Submitted</h2>
    <p class="text-sm text-slate-600">The application for <span class="font-bold text-slate-900"><?= e($success['name']) ?></span> has been registered under Application Reference:</p>
  </div>
  <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-left space-y-3 font-mono text-xs">
    <?php foreach ([
        'Ref ID:' => ['<span class="font-bold text-cyan-700">' . e($success['ref_no']) . '</span>'],
        'Institution Affiliation Code:' => [e($success['affiliation_code'])],
        'Registered Login ID (Coordinator Email):' => ['<span class="font-bold text-cyan-800">' . e($success['coord_email']) . '</span>'],
        'e-Admin / Coordinator:' => [e($success['coord_name'])],
    ] as $k => [$v]): ?>
      <div class="flex flex-col sm:flex-row sm:justify-between gap-1 border-b border-slate-200 pb-2"><span class="text-slate-500"><?= $k ?></span><span class="font-bold text-slate-800 break-all"><?= $v ?></span></div>
    <?php endforeach; ?>
    <div class="flex flex-col sm:flex-row sm:justify-between gap-1">
      <span class="text-slate-500">Verification Status:</span>
      <?php if ($success['status'] === 'approved'): ?>
        <span class="font-bold text-emerald-600 flex items-center"><?= icon('check-circle', 'w-3.5 h-3.5 inline mr-1') ?>Approved – you may sign in now</span>
      <?php else: ?>
        <span class="font-bold text-amber-600 flex items-center"><?= icon('clock', 'w-3.5 h-3.5 inline mr-1') ?>Pending Zone Approval (Est. 24 hrs)</span>
      <?php endif; ?>
    </div>
  </div>
  <p class="text-xs text-slate-500">A confirmation containing login instructions has been dispatched to both Coordinator and Principal email addresses.</p>
  <div class="flex justify-center space-x-3 pt-2 print:hidden">
    <button type="button" onclick="window.print()" class="px-4 py-2 border border-slate-200 hover:bg-slate-100 rounded-lg text-xs font-semibold text-slate-700 flex items-center"><?= icon('printer', 'w-3.5 h-3.5 mr-1.5') ?>Print Acknowledgement</button>
    <a href="<?= url('') ?>" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-bold uppercase tracking-wider flex items-center">Go to Sign In</a>
  </div>
</section>
<?php unset($_SESSION['reg_success']); ?>

<?php elseif (!registration_is_open()): ?>
<section class="max-w-xl mx-auto bg-white border border-slate-200 rounded-2xl p-8 shadow-xl text-center space-y-4">
  <div class="w-14 h-14 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center mx-auto"><?= icon('calendar-x', 'w-7 h-7') ?></div>
  <h2 class="text-xl font-extrabold text-slate-900">Participation Requests Closed</h2>
  <p class="text-sm text-slate-500">The registration window for new institutions is currently closed. Please contact the support desk for assistance.</p>
  <a href="<?= url('') ?>" class="btn-dark">Back to Sign In</a>
</section>

<?php else: ?>
<section>
  <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center space-x-2">
      <a href="<?= url('') ?>" class="p-2 bg-white border border-slate-200 rounded-lg text-slate-600 hover:text-cyan-700 hover:border-cyan-300 transition shadow-xs flex items-center">
        <?= icon('chevron-left') ?><span class="text-xs font-semibold ml-1">Back to Sign In</span>
      </a>
      <div class="h-4 w-px bg-slate-200"></div>
      <span class="text-xs text-slate-500"><?= e(setting('organisation_name', 'CBSE South Zone Sahodaya Complex')) ?> &gt; New Institution Registration</span>
    </div>
  </div>

  <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
    <div class="bg-gradient-to-r from-cyan-900 via-teal-900 to-slate-900 text-white p-6 sm:p-8">
      <div class="max-w-3xl">
        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-cyan-400/20 text-cyan-300 border border-cyan-400/30">Official Application Form</span>
        <h2 class="text-2xl sm:text-3xl font-extrabold mt-2">Participation Request for <?= e(setting('event_title', 'Science Fair')) ?></h2>
        <p class="text-sm text-slate-300 mt-2 leading-relaxed">Please register your institution's profile along with the authorized Head of Institution and the designated Coordinator (e-Admin). The Coordinator Email ID and Password set here will be your future sign-in credentials.</p>
      </div>
    </div>

    <form method="post" action="<?= url('register') ?>" class="p-6 sm:p-8 space-y-8" id="participationForm" novalidate>
      <?= csrf_field() ?>

      <!-- SECTION 1 -->
      <div>
        <div class="flex items-center space-x-2 border-b border-slate-200 pb-3 mb-5">
          <div class="section-badge bg-cyan-100 text-cyan-700">1</div>
          <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900">Institution Identification</h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
          <div class="md:col-span-4">
            <label class="form-label" for="affiliation_code">Institution Affiliation Code <span class="text-red-500">*</span></label>
            <input type="text" id="affiliation_code" name="affiliation_code" value="<?= e(old('affiliation_code')) ?>" required maxlength="10" placeholder="e.g., 930412" class="form-input uppercase font-semibold">
            <p class="form-hint">CBSE Affiliation number</p>
          </div>
          <div class="md:col-span-8">
            <label class="form-label" for="name">Name of Institution (Full Official Name) <span class="text-red-500">*</span></label>
            <input type="text" id="name" name="name" value="<?= e(old('name')) ?>" required maxlength="255" placeholder="e.g., Kendriya Vidyalaya / Bhavans Vidya Mandir, Pattom" class="form-input">
            <p class="form-hint">Must match the official CBSE affiliation certificate name exactly</p>
          </div>
        </div>
      </div>

      <!-- SECTION 2 -->
      <div>
        <div class="flex items-center space-x-2 border-b border-slate-200 pb-3 mb-5">
          <div class="section-badge bg-teal-100 text-teal-700">2</div>
          <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900">Head of Institution Details</h3>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label class="form-label" for="head_name">Name of Head of Institution <span class="text-red-500">*</span></label>
            <div class="relative"><div class="input-icon"><?= icon('user-check') ?></div>
              <input type="text" id="head_name" name="head_name" value="<?= e(old('head_name')) ?>" required maxlength="150" placeholder="e.g., Dr. Ananya Menon" class="form-input pl-9"></div>
          </div>
          <div>
            <label class="form-label" for="head_designation">Designation <span class="text-red-500">*</span></label>
            <select id="head_designation" name="head_designation" required class="form-input"><?= select_options(array_combine($designations, $designations), old('head_designation', 'Principal')) ?></select>
          </div>
          <div>
            <label class="form-label" for="head_email">Official Email ID <span class="text-red-500">*</span></label>
            <div class="relative"><div class="input-icon"><?= icon('mail') ?></div>
              <input type="email" id="head_email" name="head_email" value="<?= e(old('head_email')) ?>" required placeholder="principal@schoolname.ac.in" class="form-input pl-9"></div>
          </div>
          <div>
            <label class="form-label" for="head_phone">Contact Number <span class="text-red-500">*</span></label>
            <div class="relative"><div class="input-icon"><?= icon('phone') ?></div>
              <input type="tel" id="head_phone" name="head_phone" value="<?= e(old('head_phone')) ?>" pattern="[0-9]{10}" maxlength="10" required placeholder="10-digit mobile number" class="form-input pl-9"></div>
          </div>
        </div>
      </div>

      <!-- SECTION 3 -->
      <div>
        <div class="flex items-center space-x-2 border-b border-slate-200 pb-3 mb-5">
          <div class="section-badge bg-emerald-100 text-emerald-700">3</div>
          <div>
            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900">Coordinator (e-Admin) Profile &amp; Login Credentials</h3>
            <p class="text-xs text-slate-500">The email ID and password provided here will be used as the Login ID on the official sign-in page.</p>
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
          <div>
            <label class="form-label" for="coord_name">Name of Coordinator (e-Admin) <span class="text-red-500">*</span></label>
            <div class="relative"><div class="input-icon"><?= icon('user') ?></div>
              <input type="text" id="coord_name" name="coord_name" value="<?= e(old('coord_name')) ?>" required maxlength="150" placeholder="e.g., Rajesh Kumar V" class="form-input pl-9"></div>
          </div>
          <div>
            <label class="form-label" for="coord_designation">Designation <span class="text-red-500">*</span></label>
            <input type="text" id="coord_designation" name="coord_designation" value="<?= e(old('coord_designation')) ?>" required maxlength="100" placeholder="e.g., PGT Physics / HOD Science" class="form-input">
          </div>
          <div>
            <label class="form-label" for="coord_email">Coordinator Email ID (Login ID) <span class="text-red-500">*</span></label>
            <div class="relative"><div class="input-icon"><?= icon('mail') ?></div>
              <input type="email" id="coord_email" name="coord_email" value="<?= e(old('coord_email')) ?>" required placeholder="coordinator.science@schoolname.ac.in" class="form-input pl-9"></div>
            <p class="text-[11px] text-cyan-700 font-medium mt-1">This will be your official sign-in username</p>
          </div>
          <div>
            <label class="form-label" for="coord_phone">Coordinator Contact Number <span class="text-red-500">*</span></label>
            <div class="relative"><div class="input-icon"><?= icon('phone') ?></div>
              <input type="tel" id="coord_phone" name="coord_phone" value="<?= e(old('coord_phone')) ?>" pattern="[0-9]{10}" maxlength="10" required placeholder="10-digit mobile number" class="form-input pl-9"></div>
          </div>
          <div>
            <label class="form-label" for="password">Create Login Password <span class="text-red-500">*</span></label>
            <div class="relative"><div class="input-icon"><?= icon('lock') ?></div>
              <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password" placeholder="Minimum 8 characters, letters & numbers" class="form-input pl-9"></div>
          </div>
          <div>
            <label class="form-label" for="password_confirm">Retype Password <span class="text-red-500">*</span></label>
            <div class="relative"><div class="input-icon"><?= icon('shield-alert') ?></div>
              <input type="password" id="password_confirm" name="password_confirm" minlength="8" required autocomplete="new-password" placeholder="Re-enter password" class="form-input pl-9"></div>
            <div id="passwordMismatchMsg" class="hidden text-xs text-red-500 mt-1 font-semibold">Passwords do not match.</div>
          </div>
        </div>
      </div>

      <!-- SECTION 4: CAPTCHA -->
      <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-5">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-5">
          <div class="space-y-2">
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700" for="captcha">Security Verification (Captcha) <span class="text-red-500">*</span></label>
            <div class="flex flex-wrap items-center gap-3">
              <img id="regCaptcha" src="<?= url('captcha', ['k' => 'register']) ?>" alt="Captcha" width="170" height="50" class="rounded-lg border border-slate-700 shadow-inner select-none">
              <button type="button" data-captcha-refresh="#regCaptcha" data-captcha-input="#captcha" title="Generate new code" class="p-2 text-slate-500 hover:text-slate-800 hover:bg-slate-200 rounded-lg transition"><?= icon('refresh-cw') ?></button>
              <input type="text" id="captcha" name="captcha" required maxlength="5" autocomplete="off" placeholder="Enter captcha" class="w-36 px-3 py-2 text-sm uppercase font-mono font-bold border border-slate-200 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 outline-none bg-white">
            </div>
          </div>
          <div class="max-w-md text-xs text-slate-500">
            <div class="flex items-start space-x-2">
              <input type="checkbox" id="declaration" name="declaration" value="1" required class="mt-0.5 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
              <label for="declaration" class="text-slate-600">I declare that the details provided are authorized by the school management and conform to <?= e(setting('organisation_name', 'CBSE South Zone Sahodaya Complex')) ?> regulations.</label>
            </div>
          </div>
        </div>
      </div>

      <div class="flex flex-col sm:flex-row items-center justify-end gap-3 pt-3 border-t border-slate-200">
        <a href="<?= url('') ?>" class="btn-light w-full sm:w-auto">Cancel</a>
        <button type="submit" class="btn-primary w-full sm:w-auto px-8"><span>Submit Participation Request</span><?= icon('send') ?></button>
      </div>
    </form>
  </div>
</section>
<script>
  document.getElementById('participationForm').addEventListener('submit', function (e) {
    var p1 = document.getElementById('password').value, p2 = document.getElementById('password_confirm').value;
    var msg = document.getElementById('passwordMismatchMsg');
    if (p1 !== p2) { e.preventDefault(); msg.classList.remove('hidden'); return; }
    msg.classList.add('hidden');
    if (!this.checkValidity()) { e.preventDefault(); this.reportValidity(); }
  });
</script>
<?php endif;
clear_old();
require __DIR__ . '/includes/layout/public_footer.php';
