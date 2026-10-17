<?php
/**
 * Public landing page: Official (coordinator / administrator) sign in.
 */
require __DIR__ . '/includes/bootstrap.php';

if ($u = current_user()) {
    redirect(home_for($u));
}

if (is_post()) {
    verify_csrf();
    $email = mb_strtolower((string) input('email'));
    remember_input(['email' => $email]);

    if (!captcha_verify('login', (string) input('captcha'))) {
        flash('error', 'Incorrect captcha code entered. Please try again.');
        redirect('');
    }
    if (!valid_email($email) || input('password') === '') {
        flash('error', 'Please enter a valid login ID (email) and password.');
        redirect('');
    }
    if ($error = attempt_login($email, (string) $_POST['password'])) {
        flash('error', $error);
        redirect('');
    }
    clear_old();
    $intended = $_SESSION['intended'] ?? '';
    unset($_SESSION['intended']);
    $user = current_user();
    $prefix = $user['role'] === ROLE_INSTITUTION ? 'institution/' : 'admin/';
    redirect(is_string($intended) && str_starts_with($intended, $prefix) ? $intended : home_for($user));
}

$categories = active_categories();
$events = db_all('SELECT name FROM events WHERE status = "active" ORDER BY sort_order');
$closes = setting('registration_closes');
$allClasses = array_merge(...array_map('category_classes', $categories ?: [['classes' => '']]));
$classRange = $allClasses ? count($categories) . ' categories • ' . reset($allClasses) . ' – ' . end($allClasses) : 'To be announced';
$pageTitle = 'Official Sign In';
require __DIR__ . '/includes/layout/public_header.php';
?>
<section class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">

  <!-- Left Column: Event Context & Highlights -->
  <div class="lg:col-span-7 space-y-6">
    <div class="inline-flex items-center space-x-2 px-3 py-1 bg-white border border-slate-200 rounded-full shadow-sm">
      <span class="w-2 h-2 rounded-full <?= registration_is_open() ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500' ?>"></span>
      <span class="text-xs font-semibold text-slate-700 tracking-wide uppercase"><?= registration_is_open() ? 'Annual Inter-School Innovation Summit • Registrations Open' : 'Registrations Closed' ?></span>
    </div>

    <div class="space-y-3">
      <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight leading-tight">
        CBSE South Zone <span class="bg-gradient-to-r from-cyan-600 to-teal-600 bg-clip-text text-transparent">Sahodaya Complex</span> Science Fair
      </h2>
      <p class="text-slate-600 text-sm sm:text-base leading-relaxed">
        A unified platform fostering scientific enquiry, sustainable tech inventions, and robotic prototypes across South Zone CBSE member schools.
      </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-2">
      <div class="bg-white border border-slate-200/80 p-4 rounded-xl shadow-xs hover:border-cyan-300 transition-colors">
        <div class="flex items-center space-x-3">
          <div class="p-2 bg-cyan-50 rounded-lg text-cyan-700"><?= icon('award', 'w-5 h-5') ?></div>
          <div>
            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Events</h4>
            <p class="text-xs text-slate-500"><?= e(implode(', ', array_column($events, 'name')) ?: 'To be announced') ?></p>
          </div>
        </div>
      </div>
      <div class="bg-white border border-slate-200/80 p-4 rounded-xl shadow-xs hover:border-cyan-300 transition-colors">
        <div class="flex items-center space-x-3">
          <div class="p-2 bg-teal-50 rounded-lg text-teal-700"><?= icon('users', 'w-5 h-5') ?></div>
          <div>
            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Categories</h4>
            <p class="text-xs text-slate-500"><?= e($classRange) ?></p>
          </div>
        </div>
      </div>
      <div class="bg-white border border-slate-200/80 p-4 rounded-xl shadow-xs hover:border-cyan-300 transition-colors">
        <div class="flex items-center space-x-3">
          <div class="p-2 bg-amber-50 rounded-lg text-amber-700"><?= icon('clock', 'w-5 h-5') ?></div>
          <div>
            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Registration Closes</h4>
            <p class="text-xs text-slate-500"><?= $closes ? e(date('F j, Y • h:i A', strtotime($closes))) . ' IST' : 'To be announced' ?></p>
          </div>
        </div>
      </div>
      <div class="bg-white border border-slate-200/80 p-4 rounded-xl shadow-xs hover:border-cyan-300 transition-colors">
        <div class="flex items-center space-x-3">
          <div class="p-2 bg-indigo-50 rounded-lg text-indigo-700"><?= icon('file-check-2', 'w-5 h-5') ?></div>
          <div>
            <h4 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Venue</h4>
            <p class="text-xs text-slate-500"><?= e(setting('event_venue', 'To be announced')) ?></p>
          </div>
        </div>
      </div>
    </div>

    <div class="bg-gradient-to-r from-cyan-900 to-slate-900 rounded-2xl p-5 text-white flex flex-col sm:flex-row items-center justify-between gap-4 shadow-lg shadow-cyan-950/10">
      <div class="space-y-1 text-center sm:text-left">
        <p class="text-xs uppercase tracking-wider font-semibold text-cyan-300">New Institution?</p>
        <h3 class="text-base font-bold">Has your institution not received login credentials?</h3>
        <p class="text-xs text-slate-300">Submit a one-time participation request to register your CBSE campus &amp; coordinator.</p>
      </div>
      <a href="<?= url('register') ?>" class="whitespace-nowrap px-5 py-2.5 bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs uppercase tracking-wider rounded-xl transition duration-150 flex items-center shadow-md">
        <?= icon('sparkles', 'w-4 h-4 mr-2') ?>Participation Request
      </a>
    </div>
  </div>

  <!-- Right Column: Official Login Card (shown first on mobile) -->
  <div class="lg:col-span-5 order-first lg:order-none">
    <div class="bg-white rounded-2xl shadow-xl shadow-slate-200/70 border border-slate-200 overflow-hidden">
      <div class="bg-gradient-to-r from-slate-900 to-cyan-950 px-6 py-5 text-white">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-lg font-bold">Official Sign In</h3>
            <p class="text-xs text-slate-300 mt-0.5">Institution coordinators &amp; event administrators</p>
          </div>
          <div class="w-10 h-10 rounded-full bg-cyan-500/20 border border-cyan-400/30 flex items-center justify-center text-cyan-300">
            <?= icon('shield-check', 'w-5 h-5') ?>
          </div>
        </div>
      </div>

      <form method="post" action="<?= url('') ?>" class="p-6 space-y-4" autocomplete="on">
        <?= csrf_field() ?>
        <div>
          <label class="form-label" for="email">Login ID (Email ID of Coordinator) <span class="text-red-500">*</span></label>
          <div class="relative">
            <div class="input-icon"><?= icon('mail') ?></div>
            <input type="email" id="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="username" placeholder="coordinator.science@schoolname.ac.in" class="form-input pl-9">
          </div>
          <p class="form-hint">Official email address registered during participation request</p>
        </div>

        <div>
          <div class="flex justify-between items-center mb-1.5">
            <label class="form-label !mb-0" for="password">Password <span class="text-red-500">*</span></label>
            <a href="<?= url('forgot-password') ?>" class="text-xs text-cyan-600 hover:underline">Forgot password?</a>
          </div>
          <div class="relative">
            <div class="input-icon"><?= icon('lock') ?></div>
            <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••••••" class="form-input pl-9">
          </div>
        </div>

        <div>
          <label class="form-label" for="captcha">Security Verification <span class="text-red-500">*</span></label>
          <div class="flex items-center gap-2">
            <img id="loginCaptcha" src="<?= url('captcha', ['k' => 'login']) ?>" alt="Captcha" width="136" height="40" class="rounded-lg border border-slate-700 shadow-inner w-[136px] h-10 select-none">
            <button type="button" data-captcha-refresh="#loginCaptcha" data-captcha-input="#captcha" title="Generate new code" class="p-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition"><?= icon('refresh-cw') ?></button>
            <input type="text" id="captcha" name="captcha" required maxlength="5" autocomplete="off" placeholder="Enter code" class="form-input flex-1 min-w-0 uppercase font-mono font-bold">
          </div>
        </div>

        <div class="pt-2">
          <button type="submit" class="w-full py-2.5 px-4 bg-slate-900 hover:bg-slate-800 text-white font-semibold text-sm rounded-lg transition duration-150 flex items-center justify-center space-x-2 shadow-md">
            <span>Sign In to Portal</span><?= icon('arrow-right') ?>
          </button>
        </div>

        <div class="relative my-4">
          <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-slate-200"></div></div>
          <div class="relative flex justify-center text-xs uppercase"><span class="bg-white px-2 text-slate-400 font-medium">New institution wanting to enroll?</span></div>
        </div>

        <a href="<?= url('register') ?>" class="w-full py-2.5 px-4 bg-cyan-50 hover:bg-cyan-100 text-cyan-800 border border-cyan-200 font-bold text-xs uppercase tracking-wider rounded-lg transition flex items-center justify-center space-x-2">
          <?= icon('file-plus-2') ?><span>Submit Participation Request</span>
        </a>
      </form>

      <div class="bg-slate-50 px-6 py-3 border-t border-slate-200 text-center">
        <p class="text-[11px] text-slate-500"><?= icon('shield', 'w-3 h-3 inline mr-1 text-slate-400') ?>Authorized school coordinators only. Unauthorized access is prohibited.</p>
      </div>
    </div>
  </div>
</section>
<?php clear_old(); require __DIR__ . '/includes/layout/public_footer.php'; ?>
