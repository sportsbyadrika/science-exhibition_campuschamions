<?php
/**
 * Public results page: /results (visible once an admin publishes results).
 */
require __DIR__ . '/includes/bootstrap.php';

$published = setting('results_published', '0') === '1';
$eventId = input_int('event');
$events = [];
$grouped = [];

if ($published) {
    $events = db_all(
        'SELECT DISTINCT e.id, e.name FROM results r JOIN teams t ON t.id = r.team_id JOIN events e ON e.id = t.event_id
          WHERE t.status = "approved" ORDER BY e.sort_order, e.name'
    );
    $params = [];
    $where = 't.status = "approved"';
    if ($eventId) {
        $where .= ' AND t.event_id = ?';
        $params[] = $eventId;
    }
    $rows = db_all(
        "SELECT r.position, r.grade, r.remarks, t.id, t.team_code, t.project_title,
                e.name event_name, c.name category_name, i.name institution_name
           FROM results r JOIN teams t ON t.id = r.team_id JOIN events e ON e.id = t.event_id
           JOIN categories c ON c.id = t.category_id JOIN institutions i ON i.id = t.institution_id
          WHERE $where
       ORDER BY e.sort_order, e.name, c.sort_order, c.name, COALESCE(r.position, 99), r.grade, i.name",
        $params
    );
    attach_members($rows);
    foreach ($rows as $r) {
        $grouped[$r['event_name']][$r['category_name']][] = $r;
    }
}

$medal = [
    1 => 'bg-amber-100 text-amber-800 border-amber-300',
    2 => 'bg-slate-100 text-slate-700 border-slate-300',
    3 => 'bg-orange-100 text-orange-800 border-orange-300',
    4 => 'bg-cyan-50 text-cyan-800 border-cyan-200',
];
$pageTitle = 'Results';
require __DIR__ . '/includes/layout/public_header.php';
?>
<section class="space-y-6">
  <div class="bg-gradient-to-r from-cyan-900 via-teal-900 to-slate-900 text-white rounded-2xl p-6 sm:p-8 shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-amber-400/20 text-amber-300 border border-amber-400/30">Official Results</span>
      <h2 class="text-2xl sm:text-3xl font-extrabold mt-2"><?= e(setting('event_title', 'Science Fair')) ?> Results</h2>
      <p class="text-sm text-slate-300 mt-1"><?= e(setting('organisation_name', 'CBSE South Zone Sahodaya Complex')) ?><?= setting('event_dates') ? ' • ' . e(setting('event_dates')) : '' ?></p>
    </div>
    <div class="w-16 h-16 rounded-2xl bg-amber-400/20 border border-amber-300/30 flex items-center justify-center text-amber-300 shrink-0"><?= icon('trophy', 'w-9 h-9') ?></div>
  </div>

  <?php if (!$published): ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-10 text-center shadow-xs space-y-3">
      <div class="w-14 h-14 bg-amber-50 text-amber-600 rounded-full flex items-center justify-center mx-auto"><?= icon('hourglass', 'w-7 h-7') ?></div>
      <h3 class="text-lg font-extrabold text-slate-900">Results will be announced soon</h3>
      <p class="text-sm text-slate-500">Please check back after the evaluation is complete.</p>
      <a href="<?= url('') ?>" class="btn-dark">Back to Home</a>
    </div>
  <?php else: ?>
    <?php if (count($events) > 1): ?>
      <div class="flex flex-wrap gap-2">
        <a href="<?= url('results') ?>" class="<?= !$eventId ? 'btn-dark' : 'btn-light' ?> btn-sm">All Events</a>
        <?php foreach ($events as $ev): ?>
          <a href="<?= url('results', ['event' => $ev['id']]) ?>" class="<?= $eventId === (int) $ev['id'] ? 'btn-dark' : 'btn-light' ?> btn-sm"><?= e($ev['name']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!$grouped): ?>
      <div class="bg-white border border-slate-200 rounded-2xl p-10 text-center text-sm text-slate-500">No results have been published yet.</div>
    <?php endif; ?>

    <?php foreach ($grouped as $eventName => $categories): ?>
      <div class="bg-white border border-slate-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center gap-2">
          <span class="p-2 bg-cyan-50 text-cyan-700 rounded-lg"><?= icon('flask-conical') ?></span>
          <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900"><?= e($eventName) ?></h3>
        </div>
        <?php foreach ($categories as $catName => $list): ?>
          <div class="px-5 pt-4 pb-2 text-xs font-bold uppercase tracking-wider text-teal-700"><?= e($catName) ?></div>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Position</th><th>Institution</th><th>Team Name</th><th>Students</th><th>Grade</th></tr></thead>
              <tbody>
                <?php foreach ($list as $r): ?>
                  <tr>
                    <td class="whitespace-nowrap">
                      <?php if ($r['position']): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded border text-[11px] font-bold <?= $medal[(int) $r['position']] ?? '' ?>"><?= (int) $r['position'] <= 3 ? icon('medal', 'w-3.5 h-3.5') : '' ?><?= e(result_positions()[(int) $r['position']] ?? '') ?></span>
                      <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="font-semibold text-slate-900"><?= e($r['institution_name']) ?><span class="block font-mono text-[10px] text-slate-400"><?= e($r['team_code']) ?></span></td>
                    <td><?= e($r['project_title']) ?><?php if ($r['remarks']): ?><span class="block text-[11px] text-slate-500"><?= e($r['remarks']) ?></span><?php endif; ?></td>
                    <td class="text-[11px]"><?= e(implode(', ', array_map(fn ($m) => $m['name'] . ' (' . $m['class_name'] . ')', $r['members']))) ?></td>
                    <td class="font-bold"><?= e($r['grade'] ?? '—') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/layout/public_footer.php';
