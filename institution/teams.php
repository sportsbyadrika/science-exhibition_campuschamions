<?php
require __DIR__ . '/../includes/bootstrap.php';
[$user, $inst] = require_institution();
$locked = institution_locked($inst);

if (is_post()) {
    verify_csrf();
    $team = db_one('SELECT * FROM teams WHERE id = ? AND institution_id = ?', [input_int('team_id'), $inst['id']]);
    if (input('action') === 'delete' && $team) {
        if ($locked || $team['status'] === 'approved') {
            flash('error', 'This team entry is locked and cannot be removed.');
        } else {
            foreach (db_all('SELECT photo_path FROM team_members WHERE team_id = ?', [$team['id']]) as $m) {
                delete_upload($m['photo_path']);
            }
            db_exec('DELETE FROM teams WHERE id = ?', [$team['id']]);
            log_activity('team_deleted', $team['team_code']);
            flash('success', 'Team entry ' . $team['team_code'] . ' removed.');
        }
    }
    redirect('institution/teams');
}

$teams = institution_teams((int) $inst['id']);
$pageTitle = 'Registered Teams';
require __DIR__ . '/../includes/layout/app_header.php';
?>
<section class="space-y-6">
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
    <div>
      <h2 class="text-lg font-bold text-slate-900">Enrolled Innovation Teams</h2>
      <p class="text-xs text-slate-500 mt-0.5">Manage teams registered for each event and category.</p>
    </div>
    <?php if (!$locked): ?>
      <a href="<?= url('institution/team-form') ?>" class="btn-primary"><?= icon('plus') ?>Add New Team Entry</a>
    <?php endif; ?>
  </div>

  <?php if (!$teams): ?>
    <div class="bg-white border-2 border-dashed border-slate-200 rounded-2xl p-12 text-center">
      <div class="w-12 h-12 bg-cyan-50 text-cyan-600 rounded-xl flex items-center justify-center mx-auto mb-3"><?= icon('folder-plus', 'w-6 h-6') ?></div>
      <h3 class="text-base font-bold text-slate-800">No teams registered yet</h3>
      <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1 mb-4">Add your school's student teams, upload cropped photographs, and assign exhibit categories.</p>
      <?php if (!$locked): ?><a href="<?= url('institution/team-form') ?>" class="btn-dark">Create First Team Entry</a><?php endif; ?>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <?php foreach ($teams as $t): $editable = !$locked && $t['status'] !== 'approved'; ?>
        <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-xs flex flex-col justify-between space-y-4">
          <div>
            <div class="flex items-center justify-between border-b border-slate-100 pb-2 gap-2">
              <span class="font-mono text-xs font-black text-cyan-800 bg-cyan-50 px-2 py-0.5 rounded border border-cyan-200"><?= e($t['team_code']) ?></span>
              <span class="text-[11px] font-bold uppercase tracking-wider text-teal-700 bg-teal-50 px-2 py-0.5 rounded"><?= e($t['event_name']) ?></span>
            </div>
            <h4 class="font-extrabold text-sm text-slate-900 mt-2 line-clamp-2"><?= e($t['project_title']) ?></h4>
            <div class="flex items-center justify-between mt-1">
              <p class="text-[11px] text-slate-500"><?= e($t['category_name']) ?></p>
              <?= status_badge($t['status']) ?>
            </div>
            <?php if ($t['remarks']): ?><p class="text-[11px] text-rose-600 mt-1">Remarks: <?= e($t['remarks']) ?></p><?php endif; ?>
            <div class="grid grid-cols-2 gap-3 mt-4 pt-3 border-t border-slate-100">
              <?php foreach ($t['members'] as $m): ?>
                <div class="flex items-center space-x-2 min-w-0">
                  <?php if ($m['photo_path']): ?>
                    <img src="<?= media_url('photo', (int) $m['id']) ?>" alt="" class="w-10 h-12 object-cover rounded border border-slate-200 shadow-2xs shrink-0">
                  <?php else: ?>
                    <span class="w-10 h-12 rounded border border-dashed border-slate-300 flex items-center justify-center text-slate-300 shrink-0"><?= icon('user') ?></span>
                  <?php endif; ?>
                  <div class="overflow-hidden">
                    <span class="text-[10px] <?= $m['position'] == 1 ? 'text-cyan-600' : 'text-teal-600' ?> block font-bold uppercase">Student <?= (int) $m['position'] ?></span>
                    <p class="text-xs font-semibold text-slate-800 truncate"><?= e($m['name']) ?></p>
                    <p class="text-[10px] text-slate-400"><?= e($m['class_name']) ?></p>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="flex items-center justify-between pt-3 border-t border-slate-100 text-xs">
            <span class="font-extrabold text-slate-700"><?= inr($t['fee']) ?></span>
            <?php if ($editable): ?>
              <div class="flex items-center gap-3">
                <a href="<?= url('institution/team-form', ['id' => $t['id']]) ?>" class="text-cyan-700 hover:text-cyan-900 font-semibold flex items-center"><?= icon('pencil', 'w-3.5 h-3.5 mr-1') ?>Edit</a>
                <form method="post" data-confirm="Are you sure you want to remove this team entry?">
                  <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="team_id" value="<?= (int) $t['id'] ?>">
                  <button type="submit" class="text-rose-600 hover:text-rose-800 font-semibold flex items-center"><?= icon('trash-2', 'w-3.5 h-3.5 mr-1') ?>Remove</button>
                </form>
              </div>
            <?php else: ?>
              <span class="text-[11px] text-slate-400 flex items-center"><?= icon('lock', 'w-3 h-3 mr-1') ?>Locked</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
