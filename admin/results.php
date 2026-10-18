<?php
/**
 * Results entry: positions / grades for approved teams, per event & category,
 * and the switch that publishes them on the public /results page.
 */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

$eventId = input_int('event_id');
$categoryId = input_int('category_id');

if (is_post()) {
    verify_csrf();
    if (input('action') === 'publish') {
        $on = input('value') === '1';
        save_setting('results_published', $on ? '1' : '0');
        log_activity($on ? 'results_published' : 'results_unpublished');
        flash('success', $on ? 'Results are now visible on the public results page.' : 'Results are hidden from the public.');
        redirect('admin/results', ['event_id' => $eventId, 'category_id' => $categoryId]);
    }

    // Save the results grid for one event/category
    $rows = (array) ($_POST['result'] ?? []);
    $saved = 0;
    foreach ($rows as $teamId => $r) {
        $team = db_one('SELECT id FROM teams WHERE id = ? AND event_id = ? AND category_id = ? AND status = "approved"', [(int) $teamId, $eventId, $categoryId]);
        if (!$team) {
            continue;
        }
        $pos = (int) ($r['position'] ?? 0);
        $pos = array_key_exists($pos, result_positions()) ? $pos : null;
        $grade = in_array($r['grade'] ?? '', result_grades(), true) ? $r['grade'] : null;
        $remarks = mb_substr(trim((string) ($r['remarks'] ?? '')), 0, 255) ?: null;
        if ($pos === null && $grade === null && $remarks === null) {
            db_exec('DELETE FROM results WHERE team_id = ?', [$team['id']]);
            continue;
        }
        db_exec(
            'INSERT INTO results (team_id, position, grade, remarks, updated_by) VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE position = VALUES(position), grade = VALUES(grade), remarks = VALUES(remarks), updated_by = VALUES(updated_by)',
            [$team['id'], $pos, $grade, $remarks, $me['id']]
        );
        $saved++;
    }
    log_activity('results_saved', "event $eventId category $categoryId: $saved");
    flash('success', "Results saved ($saved team" . ($saved === 1 ? '' : 's') . ' with a position / grade).');
    redirect('admin/results', ['event_id' => $eventId, 'category_id' => $categoryId]);
}

$events = db_all('SELECT id, name FROM events ORDER BY sort_order, name');
$cats = db_all('SELECT id, name FROM categories ORDER BY sort_order, name');
$published = setting('results_published', '0') === '1';

// Overview: how many results are entered per event/category
$overview = db_all(
    'SELECT e.id event_id, e.name event_name, c.id category_id, c.name category_name,
            COUNT(t.id) teams, COUNT(r.id) results
       FROM teams t JOIN events e ON e.id = t.event_id JOIN categories c ON c.id = t.category_id
  LEFT JOIN results r ON r.team_id = t.id
      WHERE t.status = "approved"
   GROUP BY e.id, c.id ORDER BY e.sort_order, c.sort_order'
);

$teams = [];
if ($eventId && $categoryId) {
    $teams = db_all(
        'SELECT t.*, i.name institution_name, i.affiliation_code, r.position, r.grade, r.remarks result_remarks
           FROM teams t JOIN institutions i ON i.id = t.institution_id
      LEFT JOIN results r ON r.team_id = t.id
          WHERE t.event_id = ? AND t.category_id = ? AND t.status = "approved"
       ORDER BY COALESCE(r.position, 99), i.name',
        [$eventId, $categoryId]
    );
    attach_members($teams);
}

$pageTitle = 'Results';
require __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 rounded-xl border px-4 py-3 text-xs <?= $published ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-amber-50 border-amber-200 text-amber-900' ?>">
  <span class="flex items-center gap-2"><?= icon($published ? 'eye' : 'eye-off', 'w-4 h-4') ?>
    <?= $published ? '<strong>Results are PUBLISHED</strong> and visible to everyone on the public results page.' : '<strong>Results are NOT published.</strong> The public results page shows "Results will be announced soon".' ?>
  </span>
  <div class="flex gap-2">
    <a href="<?= url('results') ?>" target="_blank" class="btn-light btn-sm"><?= icon('external-link', 'w-3.5 h-3.5') ?>Public Page</a>
    <form method="post" data-confirm="<?= $published ? 'Hide results from the public?' : 'Publish results to the public results page?' ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="publish"><input type="hidden" name="value" value="<?= $published ? '0' : '1' ?>">
      <input type="hidden" name="event_id" value="<?= $eventId ?>"><input type="hidden" name="category_id" value="<?= $categoryId ?>">
      <button class="<?= $published ? 'btn-danger' : 'btn-success' ?> btn-sm"><?= icon($published ? 'eye-off' : 'megaphone', 'w-3.5 h-3.5') ?><?= $published ? 'Unpublish' : 'Publish Results' ?></button>
    </form>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
  <div class="lg:col-span-4 card overflow-hidden self-start">
    <div class="card-head"><h3 class="card-title">Event / Category</h3></div>
    <ul class="divide-y divide-slate-100 text-xs">
      <?php foreach ($overview as $o): $active = (int) $o['event_id'] === $eventId && (int) $o['category_id'] === $categoryId; ?>
        <li>
          <a href="<?= url('admin/results', ['event_id' => $o['event_id'], 'category_id' => $o['category_id']]) ?>" class="flex items-center justify-between gap-2 px-5 py-3 <?= $active ? 'bg-cyan-50' : 'hover:bg-slate-50' ?>">
            <span><span class="font-semibold text-slate-800"><?= e($o['event_name']) ?></span><span class="block text-[11px] text-slate-500"><?= e($o['category_name']) ?></span></span>
            <span class="font-mono text-[11px] <?= (int) $o['results'] > 0 ? 'text-emerald-700' : 'text-slate-400' ?>"><?= (int) $o['results'] ?>/<?= (int) $o['teams'] ?></span>
          </a>
        </li>
      <?php endforeach; ?>
      <?php if (!$overview): ?><li class="px-5 py-8 text-center text-slate-400">No approved team entries yet.</li><?php endif; ?>
    </ul>
    <form class="p-4 border-t border-slate-200 bg-slate-50 space-y-2">
      <select name="event_id" class="form-input-sm"><?= select_options(array_column($events, 'name', 'id'), $eventId, true, 'Select Event') ?></select>
      <select name="category_id" class="form-input-sm"><?= select_options(array_column($cats, 'name', 'id'), $categoryId, true, 'Select Category') ?></select>
      <button class="btn-dark btn-sm w-full">Open</button>
    </form>
  </div>

  <div class="lg:col-span-8 card overflow-hidden">
    <?php if (!$eventId || !$categoryId): ?>
      <div class="p-12 text-center text-xs text-slate-400">
        <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center mx-auto mb-3"><?= icon('trophy', 'w-6 h-6') ?></div>
        Choose an event and category to enter results. Only <strong>approved</strong> team entries are listed.
      </div>
    <?php else: ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="event_id" value="<?= $eventId ?>"><input type="hidden" name="category_id" value="<?= $categoryId ?>">
        <div class="card-head">
          <div>
            <h3 class="card-title"><?= e(array_column($events, 'name', 'id')[$eventId] ?? '') ?> • <?= e(array_column($cats, 'name', 'id')[$categoryId] ?? '') ?></h3>
            <p class="text-xs text-slate-500">Set a position and/or grade. Leave both empty to remove a result.</p>
          </div>
          <button class="btn-primary btn-sm"><?= icon('save', 'w-3.5 h-3.5') ?>Save Results</button>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Team</th><th>Institution / Team Name</th><th>Students</th><th>Position</th><th>Grade</th><th>Remarks</th></tr></thead>
            <tbody>
            <?php foreach ($teams as $t): $tid = (int) $t['id']; ?>
              <tr>
                <td class="font-mono font-bold text-cyan-800 whitespace-nowrap"><?= e($t['team_code']) ?></td>
                <td><span class="font-semibold"><?= e($t['institution_name']) ?></span><span class="block text-[11px] text-slate-500"><?= e($t['project_title']) ?></span></td>
                <td class="text-[11px]"><?= e(implode(', ', array_column($t['members'], 'name'))) ?></td>
                <td><select name="result[<?= $tid ?>][position]" class="form-input-sm !w-36"><?= select_options(result_positions(), $t['position'] ?? '', true, '—') ?></select></td>
                <td><select name="result[<?= $tid ?>][grade]" class="form-input-sm !w-20"><?= select_options(array_combine(result_grades(), result_grades()), $t['grade'] ?? '', true, '—') ?></select></td>
                <td><input name="result[<?= $tid ?>][remarks]" value="<?= e($t['result_remarks'] ?? '') ?>" maxlength="255" class="form-input-sm !w-40" placeholder="Optional"></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$teams): ?><tr><td colspan="6" class="text-center text-slate-400 py-10">No approved team entries in this event and category.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
