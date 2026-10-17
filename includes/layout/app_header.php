<?php
/**
 * Authenticated layout (admin + institution), based on the coordinator dashboard design.
 * Expects: $pageTitle, optional $activeTab (path of the active tab)
 */
$user = current_user();
$isAdmin = is_admin_role($user);
$path = $activeTab ?? current_path();

if ($isAdmin) {
    $nav = [
        ['admin/dashboard', 'Dashboard', 'layout-dashboard'],
        ['admin/institutions', 'Institutions', 'school'],
        ['admin/teams', 'Team Entries', 'users-2'],
        ['admin/payments', 'Fund Requests', 'wallet-cards'],
        ['admin/food-requests', 'Food Requests', 'utensils'],
        ['admin/reports', 'Reports', 'file-bar-chart'],
        ['admin/events', 'Masters', 'layers', ['admin/events', 'admin/event-form', 'admin/categories', 'admin/food-items']],
        ['admin/users', 'Users', 'user-cog'],
        ['admin/settings', 'Settings', 'sliders', ['admin/settings', 'admin/activity']],
    ];
    $badge = is_superadmin($user) ? 'Super Admin' : 'Administrator';
    $subLine = setting('organisation_name', 'CBSE South Zone Sahodaya');
    $profileUrl = url('admin/profile');
} else {
    $inst = db_one('SELECT * FROM institutions WHERE id = ?', [$user['institution_id']]);
    $sum = $summary ?? institution_summary((int) $inst['id']);
    $nav = [
        ['institution/dashboard', 'Dashboard', 'layout-dashboard'],
        ['institution/teams', 'Registered Teams (' . $sum['teams_total'] . ')', 'users-2'],
        ['institution/team-form', 'Add Team Participant', 'user-plus'],
        ['institution/food-coupons', 'Food Coupons', 'utensils'],
        ['institution/payments', 'Fund Transfer', 'wallet-cards'],
        ['institution/submit', 'Final Submission', 'file-check'],
        ['institution/reports', 'Reports', 'printer'],
    ];
    $badge = 'e-Admin Dashboard';
    $subLine = $inst['name'] . ' • Aff. #' . $inst['affiliation_code'];
    $profileUrl = url('institution/profile');
}
require __DIR__ . '/head.php';
?>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col selection:bg-cyan-500 selection:text-white">

  <!-- TOP APP NAV -->
  <nav class="bg-slate-900 border-b border-slate-800 text-white sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16 gap-3">
        <a href="<?= url(home_for($user)) ?>" class="flex items-center space-x-3 min-w-0">
          <div class="w-10 h-10 shrink-0 rounded-xl bg-gradient-to-tr from-cyan-500 to-teal-400 flex items-center justify-center text-slate-950 font-bold shadow-md shadow-cyan-500/20">
            <?= icon('atom', 'w-6 h-6') ?>
          </div>
          <div class="min-w-0">
            <div class="flex items-center space-x-2">
              <span class="text-[10px] font-bold tracking-widest text-cyan-400 uppercase bg-cyan-950/80 px-2 py-0.5 rounded border border-cyan-800/60"><?= e($badge) ?></span>
              <span class="hidden sm:inline text-[11px] text-slate-400"><?= e(setting('organisation_name', 'CBSE South Zone Sahodaya')) ?></span>
            </div>
            <h1 class="text-sm sm:text-base font-bold text-white tracking-tight leading-tight truncate"><?= e(setting('event_title', 'Science Fair')) ?> Participation Suite</h1>
          </div>
        </a>

        <div class="flex items-center space-x-3 shrink-0">
          <div class="hidden md:flex flex-col text-right">
            <span class="text-xs font-bold text-slate-200"><?= e($user['name']) ?><?= $isAdmin ? '' : ' (e-Admin)' ?></span>
            <span class="text-[11px] text-slate-400 max-w-xs truncate"><?= e($subLine) ?></span>
          </div>
          <a href="<?= $profileUrl ?>" title="My Profile & Password" class="p-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition border border-slate-700">
            <?= icon('user-round-cog') ?>
          </a>
          <a href="<?= url('logout', ['t' => csrf_token()]) ?>" class="px-3 py-1.5 text-xs font-semibold text-rose-400 bg-rose-950/40 border border-rose-800/40 rounded-lg hover:bg-rose-900/60 transition flex items-center">
            <?= icon('log-out', 'w-3.5 h-3.5 sm:mr-1.5') ?><span class="hidden sm:inline">Sign Out</span>
          </a>
        </div>
      </div>
    </div>
  </nav>

  <!-- SECONDARY TAB STRIP -->
  <div class="bg-white border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between gap-6">
      <div class="flex space-x-6 text-xs font-bold uppercase tracking-wider py-3.5 overflow-x-auto">
        <?php foreach ($nav as $item):
            $match = $item[3] ?? [$item[0]];
            $active = in_array($path, $match, true);
        ?>
          <a href="<?= url($item[0]) ?>" class="tab-link <?= $active ? 'active' : '' ?>"><?= icon($item[2], 'w-4 h-4 mr-2') ?><?= e($item[1]) ?></a>
        <?php endforeach; ?>
      </div>
      <?php if (!$isAdmin): ?>
      <div class="hidden 2xl:flex items-center space-x-2 text-xs font-mono py-2 shrink-0">
        <span class="text-slate-500">Live Fee Tally:</span>
        <span class="font-extrabold text-cyan-800 bg-cyan-50 px-2.5 py-1 rounded-md border border-cyan-200"><?= inr($sum['payable']) ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <?= render_flashes() ?>
