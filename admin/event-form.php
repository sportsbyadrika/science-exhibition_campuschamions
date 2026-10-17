<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

$event = null;
$selectedCats = [];
if ($id = input_int('id')) {
    $event = db_one('SELECT * FROM events WHERE id = ?', [$id]);
    if (!$event) {
        abort(404);
    }
    $selectedCats = array_map('intval', array_column(db_all('SELECT category_id FROM event_categories WHERE event_id = ?', [$id]), 'category_id'));
}
$categories = db_all('SELECT * FROM categories ORDER BY sort_order, name');

if (is_post()) {
    verify_csrf();
    $d = [
        'code' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) input('code'))),
        'name' => (string) input('name'),
        'description' => (string) input('description'),
        'venue' => (string) input('venue'),
        'event_date' => (string) input('event_date'),
        'registration_fee' => max(0, (float) input('registration_fee')),
        'min_members' => max(1, input_int('min_members', 1)),
        'max_members' => max(1, input_int('max_members', 2)),
        'max_teams_per_school' => max(1, input_int('max_teams_per_school', 1)),
        'photo_required' => isset($_POST['photo_required']) ? 1 : 0,
        'registration_open' => isset($_POST['registration_open']) ? 1 : 0,
        'status' => input('status') === 'inactive' ? 'inactive' : 'active',
        'sort_order' => input_int('sort_order'),
    ];
    $cats = array_values(array_intersect(array_map('intval', (array) ($_POST['categories'] ?? [])), array_map('intval', array_column($categories, 'id'))));
    $errors = [];
    if ($d['code'] === '' || strlen($d['code']) > 20) $errors[] = 'Enter a short event code (letters/numbers).';
    if ($d['name'] === '') $errors[] = 'Enter the event name.';
    if ($d['max_members'] < $d['min_members']) $errors[] = 'Maximum students cannot be less than minimum.';
    if ($d['max_members'] > 10) $errors[] = 'Maximum 10 students per team.';
    if (!$cats) $errors[] = 'Select at least one category.';
    if ($d['event_date'] !== '' && !DateTime::createFromFormat('Y-m-d', $d['event_date'])) $errors[] = 'Invalid event date.';
    if (db_value('SELECT id FROM events WHERE code = ? AND id <> ?', [$d['code'], $event['id'] ?? 0])) $errors[] = 'Event code already exists.';

    if (!$errors) {
        $d['event_date'] = $d['event_date'] ?: null;
        $cols = array_keys($d);
        if ($event) {
            db_exec('UPDATE events SET ' . implode(', ', array_map(fn ($c) => "$c = ?", $cols)) . ' WHERE id = ?', [...array_values($d), $event['id']]);
            $eid = (int) $event['id'];
        } else {
            db_exec('INSERT INTO events (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($d));
            $eid = db_insert_id();
        }
        db_exec('DELETE FROM event_categories WHERE event_id = ?', [$eid]);
        foreach ($cats as $cid) {
            db_exec('INSERT INTO event_categories (event_id, category_id) VALUES (?, ?)', [$eid, $cid]);
        }
        log_activity($event ? 'event_updated' : 'event_created', $d['name']);
        flash('success', 'Event "' . $d['name'] . '" saved.');
        redirect('admin/events');
    }
    foreach ($errors as $err) flash('error', $err);
    $event = array_merge($event ?? [], $d);
    $selectedCats = $cats;
}

$pageTitle = $event && !empty($event['id']) ? 'Edit Event' : 'Add Event';
require __DIR__ . '/../includes/layout/app_header.php';
require __DIR__ . '/../includes/layout/masters_tabs.php';
$v = fn (string $k, mixed $def = '') => e($event[$k] ?? $def);
?>
<form method="post" class="card overflow-hidden max-w-4xl">
  <?= csrf_field() ?>
  <?php if (!empty($event['id'])): ?><input type="hidden" name="id" value="<?= (int) $event['id'] ?>"><?php endif; ?>
  <div class="bg-gradient-to-r from-slate-900 to-cyan-950 p-6 text-white">
    <span class="text-[10px] font-bold tracking-widest text-cyan-400 uppercase bg-cyan-950 px-2 py-0.5 rounded border border-cyan-800">Event Master</span>
    <h2 class="text-xl font-extrabold mt-1"><?= e($pageTitle) ?></h2>
  </div>
  <div class="p-6 space-y-6">
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
      <div><label class="form-label">Code *</label><input name="code" required maxlength="20" value="<?= $v('code') ?>" placeholder="WM" class="form-input uppercase font-mono"></div>
      <div class="sm:col-span-3"><label class="form-label">Event Name *</label><input name="name" required maxlength="150" value="<?= $v('name') ?>" placeholder="Working Model" class="form-input"></div>
      <div class="sm:col-span-4"><label class="form-label">Description / Rules</label><textarea name="description" rows="3" class="form-input"><?= $v('description') ?></textarea></div>
      <div class="sm:col-span-2"><label class="form-label">Venue</label><input name="venue" maxlength="190" value="<?= $v('venue') ?>" class="form-input"></div>
      <div><label class="form-label">Event Date</label><input type="date" name="event_date" value="<?= $v('event_date') ?>" class="form-input"></div>
      <div><label class="form-label">Display Order</label><input type="number" name="sort_order" value="<?= $v('sort_order', 0) ?>" class="form-input"></div>
    </div>

    <div class="bg-slate-50 border border-slate-200 rounded-xl p-5">
      <div class="text-xs font-bold uppercase tracking-wider text-slate-600 mb-3 flex items-center"><?= icon('settings-2', 'w-4 h-4 mr-1.5 text-cyan-700') ?>Configuration &amp; Registration Fee</div>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div><label class="form-label">Fee per Team (₹) *</label><input type="number" step="0.01" min="0" name="registration_fee" value="<?= $v('registration_fee', 0) ?>" class="form-input"></div>
        <div><label class="form-label">Min Students</label><input type="number" min="1" max="10" name="min_members" value="<?= $v('min_members', 2) ?>" class="form-input"></div>
        <div><label class="form-label">Max Students</label><input type="number" min="1" max="10" name="max_members" value="<?= $v('max_members', 2) ?>" class="form-input"></div>
        <div><label class="form-label">Max Teams / School</label><input type="number" min="1" name="max_teams_per_school" value="<?= $v('max_teams_per_school', 1) ?>" class="form-input"><p class="form-hint">per category</p></div>
      </div>
      <div class="flex flex-wrap gap-6 mt-4 text-xs font-semibold text-slate-700">
        <label class="flex items-center gap-2"><input type="checkbox" name="photo_required" value="1" <?= ($event['photo_required'] ?? 1) ? 'checked' : '' ?> class="rounded border-slate-300 text-cyan-600">Student photo required</label>
        <label class="flex items-center gap-2"><input type="checkbox" name="registration_open" value="1" <?= ($event['registration_open'] ?? 1) ? 'checked' : '' ?> class="rounded border-slate-300 text-cyan-600">Registration open</label>
        <label class="flex items-center gap-2">Status <select name="status" class="form-input-sm !w-auto"><?= select_options(['active' => 'Active', 'inactive' => 'Inactive'], $event['status'] ?? 'active') ?></select></label>
      </div>
    </div>

    <div>
      <label class="form-label">Allowed Categories *</label>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        <?php foreach ($categories as $c): ?>
          <label class="flex items-start gap-2 p-3 border border-slate-200 rounded-lg text-xs hover:border-cyan-300">
            <input type="checkbox" name="categories[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $selectedCats, true) || (!$event && $c['status'] === 'active') ? 'checked' : '' ?> class="mt-0.5 rounded border-slate-300 text-cyan-600">
            <span><span class="font-bold text-slate-800"><?= e($c['name']) ?></span><span class="block text-slate-500"><?= e($c['classes']) ?></span></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
    <a href="<?= url('admin/events') ?>" class="btn-light">Cancel</a>
    <button class="btn-primary"><?= icon('save') ?>Save Event</button>
  </div>
</form>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
