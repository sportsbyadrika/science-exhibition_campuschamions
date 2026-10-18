<?php
require __DIR__ . '/../includes/bootstrap.php';
[$user, $inst] = require_institution();

if (institution_locked($inst)) {
    flash('warning', 'Your application has been finally submitted. Team entries are locked.');
    redirect('institution/teams');
}

$events = array_values(array_filter(active_events(), fn ($e) => (int) $e['registration_open'] === 1));
$categories = active_categories();
$catById = array_column($categories, null, 'id');
$evById = array_column($events, null, 'id');
$maxSlots = max(2, ...array_map(fn ($e) => (int) $e['max_members'], $events ?: [['max_members' => 2]]));

$team = null;
$members = [];
if ($id = input_int('id')) {
    $team = db_one('SELECT * FROM teams WHERE id = ? AND institution_id = ?', [$id, $inst['id']]);
    if (!$team) {
        abort(404);
    }
    if ($team['status'] === 'approved') {
        flash('warning', 'Approved team entries cannot be edited.');
        redirect('institution/teams');
    }
    foreach (db_all('SELECT * FROM team_members WHERE team_id = ? ORDER BY position', [$team['id']]) as $m) {
        $members[(int) $m['position']] = $m;
    }
}

if (is_post()) {
    verify_csrf();
    $errors = [];
    $eventId = input_int('event_id');
    $categoryId = input_int('category_id');
    $title = (string) input('project_title');
    $guide = (string) input('guide_teacher');
    $event = $evById[$eventId] ?? null;
    $category = $catById[$categoryId] ?? null;

    if (!registration_is_open()) {
        $errors[] = 'Registrations are closed.';
    }
    if (!$event) {
        $errors[] = 'Select a valid event.';
    } elseif (!$category || !in_array($categoryId, $event['category_ids'], true)) {
        $errors[] = 'Select a valid category for the chosen event.';
    }
    if (mb_strlen($title) < 3) {
        $errors[] = 'Enter the team name.';
    }

    // Collect submitted members
    $posted = [];
    $raw = $_POST['members'] ?? [];
    for ($slot = 1; $slot <= $maxSlots; $slot++) {
        $m = is_array($raw[$slot] ?? null) ? $raw[$slot] : [];
        $name = trim((string) ($m['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $posted[$slot] = [
            'name' => mb_substr($name, 0, 150),
            'class_name' => trim((string) ($m['class_name'] ?? '')),
            'gender' => in_array($m['gender'] ?? '', ['Male', 'Female', 'Other'], true) ? $m['gender'] : null,
            'photo' => (string) ($m['photo'] ?? ''),
        ];
    }

    if ($event && $category) {
        $min = (int) $event['min_members'];
        $max = (int) $event['max_members'];
        if (count($posted) < $min || count($posted) > $max) {
            $errors[] = sprintf('%s requires %s student(s) per team.', $event['name'], $min === $max ? $min : "$min to $max");
        }
        $classes = category_classes($category);
        foreach ($posted as $slot => $m) {
            if (!in_array($m['class_name'], $classes, true)) {
                $errors[] = "Select a valid class for student $slot.";
            }
            if ((int) $event['photo_required'] === 1 && $m['photo'] === '' && empty($members[$slot]['photo_path'])) {
                $errors[] = "Upload and crop the photograph of student $slot.";
            }
        }
        $existing = (int) db_value(
            'SELECT COUNT(*) FROM teams WHERE institution_id = ? AND event_id = ? AND category_id = ? AND status <> "rejected" AND id <> ?',
            [$inst['id'], $eventId, $categoryId, $team['id'] ?? 0]
        );
        if ($existing >= (int) $event['max_teams_per_school']) {
            $errors[] = sprintf('Only %d team(s) per institution are allowed in %s – %s.', $event['max_teams_per_school'], $event['name'], $category['name']);
        }
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        $newFiles = [];
        $oldFiles = [];
        try {
            // Save photos first so a bad image aborts the whole save
            foreach ($posted as $slot => &$m) {
                if ($m['photo'] !== '') {
                    $m['photo_path'] = save_data_url_image($m['photo'], 'photos', (int) config('uploads.max_photo_kb', 1024));
                    $newFiles[] = $m['photo_path'];
                    if (!empty($members[$slot]['photo_path'])) {
                        $oldFiles[] = $members[$slot]['photo_path'];
                    }
                } else {
                    $m['photo_path'] = $members[$slot]['photo_path'] ?? null;
                }
            }
            unset($m);
            foreach ($members as $slot => $old) {
                if (!isset($posted[$slot]) && $old['photo_path']) {
                    $oldFiles[] = $old['photo_path'];
                }
            }

            if ($team) {
                $code = (int) $team['event_id'] === $eventId ? $team['team_code'] : next_team_code($inst, $event);
                db_exec(
                    'UPDATE teams SET event_id = ?, category_id = ?, team_code = ?, project_title = ?, guide_teacher = ?, fee = ?, status = "pending", remarks = NULL WHERE id = ?',
                    [$eventId, $categoryId, $code, $title, $guide ?: null, $event['registration_fee'], $team['id']]
                );
                $teamId = (int) $team['id'];
                db_exec('DELETE FROM team_members WHERE team_id = ?', [$teamId]);
            } else {
                $code = next_team_code($inst, $event);
                db_exec(
                    'INSERT INTO teams (institution_id, event_id, category_id, team_code, project_title, guide_teacher, fee) VALUES (?,?,?,?,?,?,?)',
                    [$inst['id'], $eventId, $categoryId, $code, $title, $guide ?: null, $event['registration_fee']]
                );
                $teamId = db_insert_id();
            }
            $pos = 0;
            foreach ($posted as $m) {
                db_exec(
                    'INSERT INTO team_members (team_id, position, name, class_name, gender, photo_path) VALUES (?,?,?,?,?,?)',
                    [$teamId, ++$pos, $m['name'], $m['class_name'], $m['gender'], $m['photo_path']]
                );
            }
            $pdo->commit();
            array_map('delete_upload', $oldFiles);
            log_activity($team ? 'team_updated' : 'team_created', $code);
            flash('success', 'Team ' . $code . ' saved successfully.');
            redirect('institution/teams');
        } catch (Throwable $ex) {
            $pdo->rollBack();
            array_map('delete_upload', $newFiles);
            $errors[] = $ex instanceof RuntimeException ? $ex->getMessage() : 'Unable to save the team. Please try again.';
            error_log('[team-form] ' . $ex->getMessage());
        }
    }

    foreach ($errors as $err) {
        flash('error', $err);
    }
    // Re-display with submitted values (photos need to be re-cropped only if they failed)
    $team = array_merge($team ?? [], ['event_id' => $eventId, 'category_id' => $categoryId, 'project_title' => $title, 'guide_teacher' => $guide]);
    foreach ($posted as $slot => $m) {
        $members[$slot] = array_merge($members[$slot] ?? [], ['name' => $m['name'], 'class_name' => $m['class_name'], 'gender' => $m['gender'], 'photo_data' => $m['photo']]);
    }
}

$jsConfig = [
    'events' => array_map(fn ($e) => [
        'id' => (int) $e['id'], 'name' => $e['name'], 'fee' => (float) $e['registration_fee'],
        'min' => (int) $e['min_members'], 'max' => (int) $e['max_members'], 'photo' => (int) $e['photo_required'] === 1,
        'categories' => $e['category_ids'], 'description' => (string) $e['description'],
    ], $events),
    'categories' => array_map(fn ($c) => ['id' => (int) $c['id'], 'name' => $c['name'], 'description' => (string) $c['description'], 'classes' => category_classes($c)], $categories),
    'selected' => ['event' => (int) ($team['event_id'] ?? 0), 'category' => (int) ($team['category_id'] ?? 0)],
    // For the suggested team name "<Institution> <Category> - Team <n>"
    'institution' => $inst['name'],
    'categoryTeamCounts' => array_map('intval', array_column(db_all(
        'SELECT category_id, COUNT(*) n FROM teams WHERE institution_id = ? AND id <> ? GROUP BY category_id',
        [$inst['id'], (int) ($team['id'] ?? 0)]
    ), 'n', 'category_id')),
    'classes' => array_map(fn ($m) => $m['class_name'] ?? '', $members),
];

$pageTitle = $team && !empty($team['id']) ? 'Edit Team' : 'Add Participants';
$extraHead = '<link rel="stylesheet" href="' . asset('vendor/cropper.min.css') . '">';
$extraScripts = '<script src="' . asset('vendor/cropper.min.js') . '"></script>'
    . '<script>window.TEAM_FORM = ' . json_encode($jsConfig, JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>'
    . '<script src="' . asset('js/team-form.js') . '"></script>';
require __DIR__ . '/../includes/layout/app_header.php';
?>
<section class="space-y-6">
  <?php if (!$events): ?>
    <div class="card p-10 text-center text-sm text-slate-500">No events are open for registration at the moment.</div>
  <?php else: ?>
  <div class="bg-white border border-slate-200 rounded-2xl shadow-xl overflow-hidden">
    <div class="bg-gradient-to-r from-slate-900 to-cyan-950 p-6 text-white flex flex-wrap items-center justify-between gap-3">
      <div>
        <span class="text-[10px] font-bold tracking-widest text-cyan-400 uppercase bg-cyan-950 px-2 py-0.5 rounded border border-cyan-800">Participant Enrollment</span>
        <h2 class="text-xl font-extrabold mt-1"><?= !empty($team['id']) ? 'Edit Team ' . e($team['team_code'] ?? '') : 'Register New Science Fair Team' ?></h2>
        <p class="text-xs text-slate-300" id="eventRuleText">Select an event to see team size and entry fee.</p>
      </div>
      <div class="text-right">
        <span class="text-[11px] text-slate-400 uppercase font-semibold block">Entry Fee</span>
        <span class="text-xl font-black text-cyan-300" id="eventFeeText">₹ 0</span>
      </div>
    </div>

    <form id="teamForm" method="post" class="p-6 sm:p-8 space-y-8" novalidate>
      <?= csrf_field() ?>
      <?php if (!empty($team['id'])): ?><input type="hidden" name="id" value="<?= (int) $team['id'] ?>"><?php endif; ?>

      <div class="bg-slate-50 border border-slate-200 p-5 rounded-xl">
        <div class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-3 flex items-center"><?= icon('layers', 'w-4 h-4 mr-1.5 text-cyan-700') ?>Category &amp; Event Specification</div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label" for="event_id">Event <span class="text-red-500">*</span></label>
            <select id="event_id" name="event_id" required class="form-input-sm font-medium">
              <option value="">— Select Event —</option>
              <?php foreach ($events as $ev): ?>
                <option value="<?= (int) $ev['id'] ?>" <?= (int) ($team['event_id'] ?? 0) === (int) $ev['id'] ? 'selected' : '' ?>><?= e($ev['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="form-hint" id="eventHelper">Select demonstration model type</p>
          </div>
          <div>
            <label class="form-label" for="category_id">Select Category <span class="text-red-500">*</span></label>
            <select id="category_id" name="category_id" required class="form-input-sm font-medium"></select>
            <p class="form-hint" id="categoryHelper"></p>
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
          <div class="sm:col-span-2">
            <label class="form-label" for="project_title">Team Name <span class="text-red-500">*</span></label>
            <input type="text" id="project_title" name="project_title" required maxlength="255" value="<?= e($team['project_title'] ?? '') ?>" placeholder="e.g., <?= e($inst['name']) ?> Category 1 - Team 1" class="form-input-sm font-medium">
            <p class="form-hint">Suggested automatically when you pick a category – you can edit it.</p>
          </div>
          <div>
            <label class="form-label" for="guide_teacher">Guide Teacher</label>
            <input type="text" id="guide_teacher" name="guide_teacher" maxlength="150" value="<?= e($team['guide_teacher'] ?? '') ?>" placeholder="Optional" class="form-input-sm">
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8" id="memberSlots">
        <?php for ($slot = 1; $slot <= $maxSlots; $slot++):
            $m = $members[$slot] ?? [];
            $color = $slot % 2 ? 'cyan' : 'teal';
            $photoSrc = !empty($m['photo_data']) ? $m['photo_data'] : (!empty($m['id']) && !empty($m['photo_path']) ? media_url('photo', (int) $m['id']) : '');
        ?>
        <div class="member-slot border border-slate-200 rounded-xl p-5 bg-white relative shadow-xs" data-slot="<?= $slot ?>">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
            <span class="inline-flex items-center text-xs font-extrabold uppercase tracking-wider <?= $color === 'cyan' ? 'text-cyan-800 bg-cyan-50' : 'text-teal-800 bg-teal-50' ?> px-2.5 py-1 rounded">
              <?= icon('user', 'w-3.5 h-3.5 mr-1') ?>Student <?= $slot ?><?= $slot === 1 ? ' (Lead Presenter)' : '' ?>
            </span>
            <span class="slot-required text-[11px] text-slate-400">Compulsory</span>
          </div>
          <div class="space-y-4">
            <div>
              <label class="form-label">Full Student Name</label>
              <input type="text" name="members[<?= $slot ?>][name]" maxlength="150" value="<?= e($m['name'] ?? '') ?>" placeholder="Enter student's full name" class="form-input-sm member-name">
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="form-label">Class / Grade</label>
                <select name="members[<?= $slot ?>][class_name]" class="form-input-sm member-class" data-selected="<?= e($m['class_name'] ?? '') ?>"></select>
              </div>
              <div>
                <label class="form-label">Gender</label>
                <select name="members[<?= $slot ?>][gender]" class="form-input-sm"><?= select_options(['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'], $m['gender'] ?? '', true) ?></select>
              </div>
            </div>
            <div>
              <label class="form-label">Student Photograph (With Crop) <span class="photo-star text-red-500">*</span></label>
              <div class="flex items-center space-x-4">
                <div class="w-20 h-24 border-2 border-dashed border-slate-300 rounded-lg overflow-hidden bg-slate-50 flex items-center justify-center relative shadow-inner shrink-0">
                  <img src="<?= e($photoSrc) ?>" alt="" class="crop-preview w-full h-full object-cover <?= $photoSrc ? '' : 'hidden' ?>">
                  <span class="crop-placeholder text-slate-400 text-center text-[10px] p-1 flex flex-col items-center <?= $photoSrc ? 'hidden' : '' ?>"><?= icon('camera', 'w-5 h-5 mb-1') ?>No Photo</span>
                </div>
                <div class="flex-grow space-y-2">
                  <input type="file" accept="image/*" class="hidden photo-file">
                  <button type="button" class="photo-btn px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg border border-slate-300 transition flex items-center">
                    <?= icon('upload', 'w-3.5 h-3.5 mr-1.5') ?>Upload &amp; Crop Photo
                  </button>
                  <p class="text-[11px] text-slate-400">Passport style (White/Blue background recommended)</p>
                  <input type="hidden" name="members[<?= $slot ?>][photo]" class="photo-data" value="<?= e($m['photo_data'] ?? '') ?>">
                  <input type="hidden" class="photo-existing" value="<?= !empty($m['photo_path']) ? '1' : '' ?>">
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php endfor; ?>
      </div>

      <div class="flex justify-between items-center border-t border-slate-200 pt-5">
        <a href="<?= url('institution/teams') ?>" class="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-900">Cancel</a>
        <button type="submit" class="btn-primary"><?= icon('save') ?>Save Team &amp; Add to Registration</button>
      </div>
    </form>
  </div>
  <?php endif; ?>
</section>

<!-- MODAL: IMAGE CROPPER -->
<div id="cropperModal" class="hidden fixed inset-0 z-50 bg-black/75 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl">
    <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-slate-900 text-white">
      <h3 class="text-sm font-bold flex items-center"><?= icon('crop', 'w-4 h-4 mr-2 text-cyan-400') ?>Crop Student Passport Photo</h3>
      <button type="button" data-crop-close class="text-slate-400 hover:text-white"><?= icon('x', 'w-5 h-5') ?></button>
    </div>
    <div class="p-4 bg-slate-100 max-h-[60vh] flex items-center justify-center overflow-hidden">
      <img id="cropperImageTarget" src="" alt="Crop Area" class="max-w-full max-h-[50vh]">
    </div>
    <div class="p-4 border-t border-slate-200 flex items-center justify-between bg-slate-50">
      <span class="text-[11px] text-slate-500 font-mono">Aspect: 3.5 × 4.5 cm (Passport standard)</span>
      <div class="flex space-x-2">
        <button type="button" data-crop-close class="px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200 rounded-lg">Cancel</button>
        <button type="button" id="applyCropBtn" class="px-4 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white text-xs font-bold rounded-lg shadow flex items-center"><?= icon('check', 'w-3.5 h-3.5 mr-1') ?>Apply Crop</button>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
