<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $ev = db_one('SELECT * FROM events WHERE id = ?', [input_int('id')]);
    if ($ev && input('action') === 'delete') {
        if (db_value('SELECT COUNT(*) FROM teams WHERE event_id = ?', [$ev['id']])) {
            flash('error', 'This event has team entries and cannot be deleted. Mark it inactive instead.');
        } else {
            db_exec('DELETE FROM events WHERE id = ?', [$ev['id']]);
            log_activity('event_deleted', $ev['name']);
            flash('success', 'Event deleted.');
        }
    } elseif ($ev && input('action') === 'toggle') {
        db_exec('UPDATE events SET registration_open = 1 - registration_open WHERE id = ?', [$ev['id']]);
        flash('success', 'Registration ' . ($ev['registration_open'] ? 'closed' : 'opened') . ' for ' . $ev['name'] . '.');
    }
    redirect('admin/events');
}

$events = db_all(
    'SELECT e.*, (SELECT COUNT(*) FROM teams t WHERE t.event_id = e.id AND t.status <> "rejected") teams,
            (SELECT GROUP_CONCAT(c.name ORDER BY c.sort_order SEPARATOR ", ") FROM event_categories ec JOIN categories c ON c.id = ec.category_id WHERE ec.event_id = e.id) cats
       FROM events e ORDER BY e.sort_order, e.name'
);
$pageTitle = 'Event Master';
require __DIR__ . '/../includes/layout/app_header.php';
require __DIR__ . '/../includes/layout/masters_tabs.php';
?>
<div class="card overflow-hidden">
  <div class="card-head">
    <div><h2 class="card-title">Event Master</h2><p class="text-xs text-slate-500">Events, their configuration (team size, categories, limits) and registration fees.</p></div>
    <a href="<?= url('admin/event-form') ?>" class="btn-primary btn-sm"><?= icon('plus', 'w-3.5 h-3.5') ?>Add Event</a>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Code</th><th>Event</th><th>Categories</th><th class="!text-right">Fee / Team</th><th>Team Size</th><th class="!text-right">Max / School</th><th class="!text-right">Teams</th><th>Registration</th><th>Status</th><th class="!text-right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($events as $ev): ?>
        <tr>
          <td class="font-mono font-bold text-cyan-800"><?= e($ev['code']) ?></td>
          <td><span class="font-semibold"><?= e($ev['name']) ?></span><?php if ($ev['event_date'] || $ev['venue']): ?><span class="block text-[10px] text-slate-400"><?= e(trim(($ev['event_date'] ? fmt_date($ev['event_date']) : '') . ' ' . $ev['venue'])) ?></span><?php endif; ?></td>
          <td class="text-[11px] text-slate-500 max-w-xs"><?= e($ev['cats'] ?: '—') ?></td>
          <td class="text-right font-bold"><?= inr($ev['registration_fee']) ?></td>
          <td><?= (int) $ev['min_members'] === (int) $ev['max_members'] ? (int) $ev['min_members'] : (int) $ev['min_members'] . '–' . (int) $ev['max_members'] ?> student(s)<?= $ev['photo_required'] ? '<span class="block text-[10px] text-slate-400">Photo required</span>' : '' ?></td>
          <td class="text-right"><?= (int) $ev['max_teams_per_school'] ?> / category</td>
          <td class="text-right font-bold"><?= (int) $ev['teams'] ?></td>
          <td>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
              <button class="text-[10px] font-bold uppercase px-2 py-0.5 rounded border <?= $ev['registration_open'] ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200' ?>"><?= $ev['registration_open'] ? 'Open' : 'Closed' ?></button>
            </form>
          </td>
          <td><?= status_badge($ev['status']) ?></td>
          <td class="text-right whitespace-nowrap">
            <a href="<?= url('admin/event-form', ['id' => $ev['id']]) ?>" class="btn-light btn-sm"><?= icon('pencil', 'w-3.5 h-3.5') ?></a>
            <form method="post" class="inline" data-confirm="Delete this event?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $ev['id'] ?>"><button class="btn-light btn-sm !text-rose-600"><?= icon('trash-2', 'w-3.5 h-3.5') ?></button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$events): ?><tr><td colspan="10" class="text-center text-slate-400 py-10">No events yet. Add the first event.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
