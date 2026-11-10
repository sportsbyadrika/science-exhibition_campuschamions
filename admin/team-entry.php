<?php
/**
 * Admin only: view / edit one team entry inside a modal.
 *   GET  /admin/team-entry?id=..&mode=view|edit   -> modal content (HTML fragment)
 *   POST /admin/team-entry                         -> save, then back to `return`
 */
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();

$team = db_one(
    'SELECT t.*, e.name event_name, e.code event_code, c.name category_name, i.name institution_name, i.affiliation_code, i.id inst_id
       FROM teams t JOIN events e ON e.id = t.event_id JOIN categories c ON c.id = t.category_id JOIN institutions i ON i.id = t.institution_id
      WHERE t.id = ?',
    [input_int('id')]
);
if (!$team) {
    abort(404, 'Team entry not found.');
}
$members = [];
foreach (db_all('SELECT * FROM team_members WHERE team_id = ? ORDER BY position', [$team['id']]) as $m) {
    $members[(int) $m['position']] = $m;
}
$events = active_events();
foreach (db_all('SELECT * FROM events WHERE id = ?', [$team['event_id']]) as $cur) {
    if (!in_array((int) $cur['id'], array_map('intval', array_column($events, 'id')), true)) {
        $cur['category_ids'] = array_map('intval', array_column(db_all('SELECT category_id FROM event_categories WHERE event_id = ?', [$cur['id']]), 'category_id'));
        $events[] = $cur; // keep an inactive event selectable for its existing teams
    }
}
$evById = array_column($events, null, 'id');
$categories = db_all('SELECT * FROM categories ORDER BY sort_order, name');
$catById = array_column($categories, null, 'id');
$maxSlots = max(count($members), ...array_map(fn ($e) => (int) $e['max_members'], $events ?: [['max_members' => 2]]));
$return = safe_return('admin/institution?id=' . $team['inst_id']);

if (is_post()) {
    verify_csrf();
    $errors = [];
    $eventId = input_int('event_id');
    $categoryId = input_int('category_id');
    $event = $evById[$eventId] ?? null;
    $category = $catById[$categoryId] ?? null;
    $title = (string) input('project_title');
    $status = (string) input('status');
    $fee = (float) input('fee');

    if (!$event) $errors[] = 'Select a valid event.';
    elseif (!$category || !in_array($categoryId, array_map('intval', $event['category_ids']), true)) $errors[] = 'The selected category is not allowed for this event.';
    if (mb_strlen($title) < 3) $errors[] = 'Enter the team name.';
    if (!in_array($status, ['pending', 'approved', 'rejected', 'returned'], true)) $errors[] = 'Select a valid status.';
    if ($status === 'returned' && trim((string) input('remarks')) === '') $errors[] = 'Enter the correction needed in Remarks when returning a team entry.';
    if ($fee < 0) $errors[] = 'Fee cannot be negative.';

    $posted = [];
    for ($slot = 1; $slot <= $maxSlots; $slot++) {
        $m = (array) ($_POST['members'][$slot] ?? []);
        $name = trim((string) ($m['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $posted[$slot] = [
            'name' => mb_substr($name, 0, 150),
            'class_name' => trim((string) ($m['class_name'] ?? '')),
            'gender' => in_array($m['gender'] ?? '', ['Male', 'Female', 'Other'], true) ? $m['gender'] : null,
        ];
    }
    if ($event && $category) {
        $min = (int) $event['min_members']; $max = (int) $event['max_members'];
        if (count($posted) < $min || count($posted) > $max) {
            $errors[] = sprintf('%s requires %s student(s) per team.', $event['name'], $min === $max ? $min : "$min to $max");
        }
        $classes = category_classes($category);
        foreach ($posted as $slot => $m) {
            if (!in_array($m['class_name'], $classes, true)) $errors[] = "Select a valid class ($category[name]) for student $slot.";
        }
    }

    if (!$errors) {
        $newFiles = []; $oldFiles = [];
        $pdo = db();
        $pdo->beginTransaction();
        try {
            foreach ($posted as $slot => &$m) {
                $file = [
                    'error' => $_FILES['photo']['error'][$slot] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $_FILES['photo']['size'][$slot] ?? 0,
                    'tmp_name' => $_FILES['photo']['tmp_name'][$slot] ?? '',
                ];
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $m['photo_path'] = save_uploaded_photo($file, 'photos', 5120);
                    $newFiles[] = $m['photo_path'];
                    if (!empty($members[$slot]['photo_path'])) $oldFiles[] = $members[$slot]['photo_path'];
                } else {
                    $m['photo_path'] = $members[$slot]['photo_path'] ?? null;
                }
            }
            unset($m);
            foreach ($members as $slot => $old) {
                if (!isset($posted[$slot]) && $old['photo_path']) $oldFiles[] = $old['photo_path'];
            }
            $code = (int) $team['event_id'] === $eventId ? $team['team_code'] : next_team_code(db_one('SELECT * FROM institutions WHERE id = ?', [$team['inst_id']]), $event);
            $statusChanged = $status !== $team['status'];
            db_exec(
                'UPDATE teams SET event_id = ?, category_id = ?, team_code = ?, project_title = ?, guide_teacher = ?, fee = ?, status = ?, remarks = ?,
                        reviewed_by = IF(?, ?, reviewed_by), reviewed_at = IF(?, NOW(), reviewed_at) WHERE id = ?',
                [$eventId, $categoryId, $code, $title, ((string) input('guide_teacher')) ?: null, $fee, $status, mb_substr((string) input('remarks'), 0, 255) ?: null,
                 $statusChanged ? 1 : 0, $admin['id'], $statusChanged ? 1 : 0, $team['id']]
            );
            db_exec('DELETE FROM team_members WHERE team_id = ?', [$team['id']]);
            $pos = 0;
            foreach ($posted as $m) {
                db_exec('INSERT INTO team_members (team_id, position, name, class_name, gender, photo_path) VALUES (?,?,?,?,?,?)',
                    [$team['id'], ++$pos, $m['name'], $m['class_name'], $m['gender'], $m['photo_path']]);
            }
            $pdo->commit();
            array_map('delete_upload', $oldFiles);
            log_activity('team_edited_by_admin', $code . ($code !== $team['team_code'] ? ' (was ' . $team['team_code'] . ')' : ''));
            flash('success', 'Team entry ' . $code . ' updated.');
            redirect_to($return);
        } catch (Throwable $ex) {
            $pdo->rollBack();
            array_map('delete_upload', $newFiles);
            error_log('[team-entry] ' . $ex->getMessage());
            $errors[] = $ex instanceof RuntimeException ? $ex->getMessage() : 'Unable to save the team entry.';
        }
    }
    foreach ($errors as $err) flash('error', $err);
    $_SESSION['_modal_old'] = ['team' . $team['id'] => $_POST];
    redirect_to(preg_replace('/([?&])modal=[^&]*(&|$)/', '$1', $return), ['modal' => url('admin/team-entry', ['id' => $team['id'], 'mode' => 'edit', 'return' => $return])]);
}

// ---------------- Modal content ----------------
$mode = input('mode') === 'edit' ? 'edit' : 'view';
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
    // Opened directly: show the institution page with this modal open
    redirect_to($return, ['modal' => url('admin/team-entry', ['id' => $team['id'], 'mode' => $mode, 'return' => $return])]);
}
$old = $_SESSION['_modal_old']['team' . $team['id']] ?? null;
unset($_SESSION['_modal_old']);
if ($old) {
    $team = array_merge($team, array_intersect_key($old, array_flip(['event_id', 'category_id', 'project_title', 'guide_teacher', 'fee', 'status', 'remarks'])));
    foreach ((array) ($old['members'] ?? []) as $slot => $m) {
        $members[(int) $slot] = array_merge($members[(int) $slot] ?? [], array_intersect_key((array) $m, array_flip(['name', 'class_name', 'gender'])));
    }
}
$result = db_one('SELECT * FROM results WHERE team_id = ?', [$team['id']]);
header('Content-Type: text/html; charset=utf-8');
?>
<div class="bg-gradient-to-r from-slate-900 to-cyan-950 px-6 py-4 text-white flex items-start justify-between gap-4">
  <div>
    <span class="text-[10px] font-bold tracking-widest text-cyan-400 uppercase"><?= $mode === 'edit' ? 'Edit Team Entry' : 'Team Entry Details' ?></span>
    <h3 class="text-lg font-extrabold font-mono"><?= e($team['team_code']) ?></h3>
    <p class="text-xs text-slate-300"><?= e($team['institution_name']) ?> • Aff. <?= e($team['affiliation_code']) ?></p>
  </div>
  <button type="button" data-modal-close class="text-slate-400 hover:text-white"><?= icon('x', 'w-5 h-5') ?></button>
</div>

<?php if ($mode === 'view'): ?>
  <div class="p-6 space-y-5 text-xs">
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
      <div><span class="text-[11px] uppercase font-semibold text-slate-500">Event</span><p class="font-bold text-slate-900"><?= e($team['event_name']) ?></p></div>
      <div><span class="text-[11px] uppercase font-semibold text-slate-500">Category</span><p class="font-bold text-slate-900"><?= e($team['category_name']) ?></p></div>
      <div><span class="text-[11px] uppercase font-semibold text-slate-500">Status</span><p><?= status_badge($team['status']) ?></p></div>
      <div class="col-span-2"><span class="text-[11px] uppercase font-semibold text-slate-500">Team Name</span><p class="font-bold text-slate-900"><?= e($team['project_title']) ?></p></div>
      <div><span class="text-[11px] uppercase font-semibold text-slate-500">Fee</span><p class="font-bold text-slate-900"><?= inr($team['fee']) ?></p></div>
      <div><span class="text-[11px] uppercase font-semibold text-slate-500">Guide Teacher</span><p class="text-slate-800"><?= e($team['guide_teacher'] ?: '—') ?></p></div>
      <div><span class="text-[11px] uppercase font-semibold text-slate-500">Registered</span><p class="text-slate-800"><?= e(fmt_datetime($team['created_at'])) ?></p></div>
      <div><span class="text-[11px] uppercase font-semibold text-slate-500">Reviewed</span><p class="text-slate-800"><?= e(fmt_datetime($team['reviewed_at'])) ?></p></div>
      <?php if ($team['remarks']): ?><div class="col-span-full"><span class="text-[11px] uppercase font-semibold text-slate-500">Remarks</span><p class="text-rose-700"><?= e($team['remarks']) ?></p></div><?php endif; ?>
      <?php if ($result): ?><div class="col-span-full"><span class="text-[11px] uppercase font-semibold text-slate-500">Result</span><p class="font-bold text-amber-700"><?= e(trim((result_positions()[(int) $result['position']] ?? '') . ($result['grade'] ? ' • Grade ' . $result['grade'] : ''))) ?></p></div><?php endif; ?>
    </div>
    <div>
      <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-2">Students</h4>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <?php foreach ($members as $m): ?>
          <div class="flex items-center gap-3 border border-slate-200 rounded-xl p-3">
            <?php if ($m['photo_path']): ?>
              <a href="<?= media_url('photo', (int) $m['id']) ?>" target="_blank"><img src="<?= media_url('photo', (int) $m['id']) ?>" alt="" class="w-16 h-20 object-cover rounded-lg border border-slate-200"></a>
            <?php else: ?>
              <span class="w-16 h-20 rounded-lg border border-dashed border-slate-300 flex items-center justify-center text-slate-300"><?= icon('user') ?></span>
            <?php endif; ?>
            <div>
              <span class="text-[10px] font-bold uppercase text-cyan-600">Student <?= (int) $m['position'] ?></span>
              <p class="text-sm font-bold text-slate-900"><?= e($m['name']) ?></p>
              <p class="text-slate-500"><?= e($m['class_name']) ?><?= $m['gender'] ? ' • ' . e($m['gender']) : '' ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
    <button type="button" data-modal-close class="btn-light btn-sm">Close</button>
    <button type="button" data-modal-url="<?= e(url('admin/team-entry', ['id' => $team['id'], 'mode' => 'edit', 'return' => $return])) ?>" class="btn-primary btn-sm"><?= icon('pencil', 'w-3.5 h-3.5') ?>Edit</button>
  </div>

<?php else:
    $jsEvents = array_map(fn ($e) => ['id' => (int) $e['id'], 'min' => (int) $e['min_members'], 'max' => (int) $e['max_members'], 'fee' => (float) $e['registration_fee'], 'categories' => array_map('intval', $e['category_ids'])], array_values($events));
    $jsCats = array_map(fn ($c) => ['id' => (int) $c['id'], 'name' => $c['name'], 'classes' => category_classes($c)], $categories);
?>
  <form method="post" action="<?= url('admin/team-entry') ?>" enctype="multipart/form-data" class="team-edit-form" data-events='<?= e(json_encode($jsEvents)) ?>' data-categories='<?= e(json_encode($jsCats)) ?>'>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $team['id'] ?>"><input type="hidden" name="return" value="<?= e($return) ?>">
    <div class="p-6 space-y-5 max-h-[65vh] overflow-y-auto">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div><label class="form-label">Event *</label>
          <select name="event_id" class="form-input-sm js-event"><?= select_options(array_column($events, 'name', 'id'), $team['event_id']) ?></select></div>
        <div><label class="form-label">Category *</label>
          <select name="category_id" class="form-input-sm js-category" data-selected="<?= (int) $team['category_id'] ?>"><?= select_options(array_column($categories, 'name', 'id'), $team['category_id']) ?></select></div>
        <div class="sm:col-span-2"><label class="form-label">Team Name *</label><input name="project_title" required maxlength="255" value="<?= e($team['project_title']) ?>" class="form-input-sm"></div>
        <div><label class="form-label">Guide Teacher</label><input name="guide_teacher" maxlength="150" value="<?= e($team['guide_teacher'] ?? '') ?>" class="form-input-sm"></div>
        <div><label class="form-label">Fee (₹)</label><input type="number" step="0.01" min="0" name="fee" value="<?= e($team['fee']) ?>" class="form-input-sm"><p class="form-hint">Changing the event does not change the fee automatically.</p></div>
        <div><label class="form-label">Status</label><select name="status" class="form-input-sm"><?= select_options(['pending' => 'Pending', 'approved' => 'Approved', 'returned' => 'Returned to Institution', 'rejected' => 'Rejected'], $team['status']) ?></select></div>
        <div><label class="form-label">Remarks</label><input name="remarks" maxlength="255" value="<?= e($team['remarks'] ?? '') ?>" class="form-input-sm" placeholder="Optional"></div>
      </div>
      <div class="space-y-3">
        <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-600">Students <span class="font-normal normal-case text-slate-400">(leave the name empty to remove a student)</span></h4>
        <?php for ($slot = 1; $slot <= $maxSlots; $slot++): $m = $members[$slot] ?? []; ?>
          <div class="js-slot border border-slate-200 rounded-xl p-3 grid grid-cols-1 sm:grid-cols-12 gap-3 items-end" data-slot="<?= $slot ?>">
            <div class="sm:col-span-2 flex items-center gap-2">
              <?php if (!empty($m['id']) && !empty($m['photo_path'])): ?><img src="<?= media_url('photo', (int) $m['id']) ?>" alt="" class="w-12 h-14 object-cover rounded border border-slate-200"><?php else: ?><span class="w-12 h-14 rounded border border-dashed border-slate-300 flex items-center justify-center text-slate-300"><?= icon('user') ?></span><?php endif; ?>
              <span class="text-[10px] font-bold uppercase text-cyan-600">Student <?= $slot ?></span>
            </div>
            <div class="sm:col-span-4"><label class="form-label">Name</label><input name="members[<?= $slot ?>][name]" maxlength="150" value="<?= e($m['name'] ?? '') ?>" class="form-input-sm"></div>
            <div class="sm:col-span-2"><label class="form-label">Class</label><select name="members[<?= $slot ?>][class_name]" class="form-input-sm js-class" data-selected="<?= e($m['class_name'] ?? '') ?>"><option value="<?= e($m['class_name'] ?? '') ?>"><?= e($m['class_name'] ?? '—') ?></option></select></div>
            <div class="sm:col-span-2"><label class="form-label">Gender</label><select name="members[<?= $slot ?>][gender]" class="form-input-sm"><?= select_options(['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'], $m['gender'] ?? '', true, '—') ?></select></div>
            <div class="sm:col-span-2"><label class="form-label">New Photo</label><input type="file" name="photo[<?= $slot ?>]" accept="image/jpeg,image/png,image/webp" class="block w-full text-[11px] file:mr-2 file:px-2 file:py-1 file:rounded file:border-0 file:bg-slate-100 file:text-slate-700"></div>
          </div>
        <?php endfor; ?>
        <p class="text-[11px] text-slate-400">A new photo is cropped automatically to passport size (3.5 × 4.5).</p>
      </div>
    </div>
    <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-between gap-2">
      <button type="button" data-modal-url="<?= e(url('admin/team-entry', ['id' => $team['id'], 'mode' => 'view', 'return' => $return])) ?>" class="btn-light btn-sm"><?= icon('eye', 'w-3.5 h-3.5') ?>View</button>
      <div class="flex gap-2">
        <button type="button" data-modal-close class="btn-light btn-sm">Cancel</button>
        <button class="btn-primary btn-sm"><?= icon('save', 'w-3.5 h-3.5') ?>Save Changes</button>
      </div>
    </div>
  </form>
<?php endif;
