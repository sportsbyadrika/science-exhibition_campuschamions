<?php require __DIR__ . '/head.php'; ?>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col justify-between selection:bg-cyan-500 selection:text-white">

  <!-- Top Header Navigation -->
  <header class="bg-white border-b border-slate-200 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-20 gap-3">
        <a href="<?= url('') ?>" class="flex items-center space-x-3 min-w-0">
          <div class="w-12 h-12 shrink-0 rounded-xl bg-gradient-to-br from-cyan-600 via-teal-600 to-emerald-700 flex items-center justify-center text-white shadow-md shadow-cyan-900/10 ring-2 ring-cyan-100">
            <?= icon('atom', 'w-7 h-7') ?>
          </div>
          <div class="min-w-0">
            <div class="flex items-center space-x-2">
              <span class="text-xs font-bold uppercase tracking-wider text-cyan-700 bg-cyan-50 px-2 py-0.5 rounded border border-cyan-200"><?= e(setting('event_year_label', 'South Zone 2026-27')) ?></span>
              <span class="hidden sm:inline text-xs font-semibold text-slate-500">CBSE Affiliated</span>
            </div>
            <h1 class="text-sm sm:text-lg font-extrabold text-slate-900 tracking-tight leading-snug truncate"><?= e(setting('site_title', 'Sahodaya Science Fair Registration Portal')) ?></h1>
          </div>
        </a>

        <div class="flex items-center space-x-3 shrink-0">
          <div class="hidden md:flex items-center text-xs font-medium text-slate-600 space-x-4 mr-2">
            <?php if (setting('event_dates')): ?><span class="flex items-center"><?= icon('calendar', 'w-3.5 h-3.5 mr-1.5 text-cyan-600') ?>Event Date: <?= e(setting('event_dates')) ?></span><?php endif; ?>
            <?php if (setting('event_host')): ?><span class="flex items-center"><?= icon('map-pin', 'w-3.5 h-3.5 mr-1.5 text-teal-600') ?>Host: <?= e(setting('event_host')) ?></span><?php endif; ?>
          </div>
          <a href="<?= url('') ?>" class="px-3 sm:px-4 py-2 text-xs font-semibold text-cyan-700 bg-cyan-50 hover:bg-cyan-100 border border-cyan-200 rounded-lg transition duration-150 flex items-center shadow-sm">
            <?= icon('log-in', 'w-3.5 h-3.5 sm:mr-1.5') ?><span class="hidden sm:inline">Official Sign In</span>
          </a>
          <a href="<?= url('register') ?>" class="px-3 sm:px-4 py-2 text-xs font-semibold text-white bg-gradient-to-r from-cyan-600 to-teal-600 hover:from-cyan-700 hover:to-teal-700 rounded-lg transition shadow-sm flex items-center shadow-cyan-600/20">
            <?= icon('user-plus', 'w-3.5 h-3.5 sm:mr-1.5') ?><span class="hidden sm:inline">Participation Request</span>
          </a>
        </div>
      </div>
    </div>
  </header>

  <!-- Live Notice Strip -->
  <div class="bg-gradient-to-r from-cyan-900 via-teal-900 to-slate-900 text-white text-xs py-2 px-4 shadow-inner">
    <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-2">
      <div class="flex items-center space-x-2">
        <span class="bg-cyan-400 text-slate-950 font-bold px-1.5 py-0.5 rounded text-[10px] uppercase tracking-wide">Notice</span>
        <span class="text-slate-200"><?= e(setting('notice_text')) ?></span>
      </div>
      <div class="flex items-center space-x-4 text-slate-300 text-[11px]">
        <?php if (setting('support_phone')): ?><span><?= icon('phone-call', 'w-3 h-3 inline mr-1 text-cyan-400') ?>Support Desk: <?= e(setting('support_phone')) ?></span><?php endif; ?>
        <?php if (setting('support_email')): ?><span><?= icon('mail', 'w-3 h-3 inline mr-1 text-cyan-400') ?><?= e(setting('support_email')) ?></span><?php endif; ?>
      </div>
    </div>
  </div>

  <main class="flex-grow bg-grid-pattern py-10 px-4 sm:px-6 lg:px-8 flex items-center justify-center">
    <div class="w-full max-w-6xl">
      <?= render_flashes() ?>
