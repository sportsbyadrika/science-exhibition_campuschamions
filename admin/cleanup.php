<?php
/**
 * Data cleanup (Settings > Data Cleanup): permanently delete REJECTED team entries
 * and REJECTED institutions. An institution can only be deleted once it has no
 * team entries left (of any status). Every rule is re-checked on the server.
 */
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $action = (string) input('action');

    if ($action === 'delete_teams') {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])))));
        if (!$ids) {
            flash('error', 'Select at least one rejected team entry to delete.');
            redirect('admin/cleanup');
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        // Only rejected entries are ever deleted, whatever was posted
        $teams = db_all("SELECT t.id, t.team_code, t.institution_id FROM teams t WHERE t.id IN ($in) AND t.status = 'rejected'", $ids);
        $skipped = count($ids) - count($teams);
        foreach ($teams as $t) {
            $photos = array_column(db_all('SELECT photo_path FROM team_members WHERE team_id = ?', [$t['id']]), 'photo_path');
            db_exec('DELETE FROM teams WHERE id = ? AND status = "rejected"', [$t['id']]); // members & results cascade
            array_map('delete_upload', $photos);
            log_activity('team_deleted_permanently', $t['team_code'] . ' (institution #' . $t['institution_id'] . ')');
        }
        if ($teams) {
            flash('success', count($teams) . ' rejected team entr' . (count($teams) === 1 ? 'y' : 'ies') . ' deleted permanently.');
        }
        if ($skipped) {
            flash('warning', $skipped . ' selected entr' . ($skipped === 1 ? 'y was' : 'ies were') . ' skipped because only rejected team entries can be deleted.');
        }
        redirect('admin/cleanup');
    }

    if ($action === 'delete_institution') {
        $inst = db_one('SELECT * FROM institutions WHERE id = ?', [input_int('id')]);
        if (!$inst) {
            flash('error', 'Institution not found.');
        } elseif ($inst['status'] !== 'rejected') {
            flash('error', 'Only rejected institutions can be deleted. Reject "' . $inst['name'] . '" first.');
        } elseif (($teamCount = (int) db_value('SELECT COUNT(*) FROM teams WHERE institution_id = ?', [$inst['id']])) > 0) {
            flash('error', '"' . $inst['name'] . '" still has ' . $teamCount . ' team entr' . ($teamCount === 1 ? 'y' : 'ies') . '. Delete (or reject and delete) its team entries first.');
        } else {
            $files = array_merge(
                [$inst['logo_path'] ?? null],
                array_column(db_all('SELECT proof_path FROM payments WHERE institution_id = ?', [$inst['id']]), 'proof_path')
            );
            db_exec('DELETE FROM institutions WHERE id = ? AND status = "rejected"', [$inst['id']]); // users, food requests, payments cascade
            array_map('delete_upload', array_filter($files));
            log_activity('institution_deleted_permanently', $inst['ref_no'] . ' - ' . $inst['name'] . ' (Aff. ' . $inst['affiliation_code'] . ', login ' . $inst['coord_email'] . ')');
            flash('success', '"' . $inst['name'] . '" and its login account were deleted permanently. The affiliation code and email ID can now be registered again.');
        }
        redirect('admin/cleanup');
    }
    redirect('admin/cleanup');
}

$teams = db_all(
    'SELECT t.*, e.name event_name, c.name category_name, i.name institution_name, i.affiliation_code
       FROM teams t JOIN events e ON e.id = t.event_id JOIN categories c ON c.id = t.category_id JOIN institutions i ON i.id = t.institution_id
      WHERE t.status = "rejected" ORDER BY t.reviewed_at DESC, t.id DESC'
);
attach_members($teams);
$institutions = db_all(
    'SELECT i.*,
            (SELECT COUNT(*) FROM teams t WHERE t.institution_id = i.id) teams,
            (SELECT COUNT(*) FROM payments p WHERE p.institution_id = i.id) payments,
            (SELECT COUNT(*) FROM food_requests f WHERE f.institution_id = i.id AND f.quantity > 0) food
       FROM institutions i WHERE i.status = "rejected" ORDER BY i.updated_at DESC, i.id DESC'
);

$pageTitle = 'Data Cleanup';
$activeTab = 'admin/settings';
require __DIR__ . '/../includes/layout/app_header.php';
require __DIR__ . '/../includes/layout/settings_tabs.php';
?>
<div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-900 flex items-start gap-2">
  <?= icon('alert-triangle', 'w-4 h-4 mt-0.5 shrink-0') ?>
  <span><strong>Permanent deletion – this cannot be undone.</strong> Only records with <strong>Rejected</strong> status are listed here.
    Team entries are deleted with their students, photos and results. An institution can be deleted only when it has <strong>no team entries</strong>;
    its login account, food coupon requests and fund transfer records are deleted with it.</span>
</div>

<!-- Rejected team entries -->
<form method="post" class="card overflow-hidden mb-6" id="teamsForm" data-confirm="Permanently delete the selected rejected team entries, their students, photos and results? This cannot be undone.">
  <?= csrf_field() ?><input type="hidden" name="action" value="delete_teams">
  <div class="card-head">
    <div>
      <h2 class="card-title flex items-center"><?= icon('users-2', 'w-4 h-4 mr-2 text-rose-600') ?>Rejected Team Entries (<?= count($teams) ?>)</h2>
      <p class="text-xs text-slate-500">Select entries and delete them permanently.</p>
    </div>
    <?php if ($teams): ?><button class="btn-danger btn-sm"><?= icon('trash-2', 'w-3.5 h-3.5') ?>Delete Selected Permanently</button><?php endif; ?>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.team-check').forEach(c => c.checked = this.checked)" aria-label="Select all"></th><th>Team</th><th>Institution</th><th>Event / Category / Team Name</th><th>Students</th><th>Rejected</th></tr></thead>
      <tbody>
      <?php foreach ($teams as $t): ?>
        <tr>
          <td><input type="checkbox" class="team-check" name="ids[]" value="<?= (int) $t['id'] ?>"></td>
          <td class="font-mono font-bold text-cyan-800 whitespace-nowrap"><?= e($t['team_code']) ?></td>
          <td><a href="<?= url('admin/institution', ['id' => $t['institution_id']]) ?>" class="font-semibold hover:text-cyan-700"><?= e($t['institution_name']) ?></a><span class="block text-[10px] text-slate-400 font-mono"><?= e($t['affiliation_code']) ?></span></td>
          <td><span class="font-semibold"><?= e($t['event_name']) ?></span> • <?= e($t['category_name']) ?><span class="block text-[11px] text-slate-500"><?= e($t['project_title']) ?></span></td>
          <td class="text-[11px]"><?= e(implode(', ', array_column($t['members'], 'name'))) ?></td>
          <td class="text-[11px] whitespace-nowrap"><?= e(fmt_date($t['reviewed_at'])) ?><?php if ($t['remarks']): ?><span class="block text-rose-600 max-w-[14rem] truncate" title="<?= e($t['remarks']) ?>"><?= e($t['remarks']) ?></span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$teams): ?><tr><td colspan="6" class="text-center text-slate-400 py-8">No rejected team entries.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</form>

<!-- Rejected institutions -->
<div class="card overflow-hidden">
  <div class="card-head">
    <div>
      <h2 class="card-title flex items-center"><?= icon('school', 'w-4 h-4 mr-2 text-rose-600') ?>Rejected Institutions (<?= count($institutions) ?>)</h2>
      <p class="text-xs text-slate-500">Delete is available only when the institution has no team entries.</p>
    </div>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr><th>Institution</th><th>Coordinator (Login ID)</th><th>Rejection Reason</th><th class="!text-right">Teams</th><th class="!text-right">Fund Transfers</th><th class="!text-right">Action</th></tr></thead>
      <tbody>
      <?php foreach ($institutions as $r): $canDelete = (int) $r['teams'] === 0; ?>
        <tr>
          <td><a href="<?= url('admin/institution', ['id' => $r['id']]) ?>" class="font-semibold text-slate-900 hover:text-cyan-700"><?= e($r['name']) ?></a><span class="block text-[10px] text-slate-400 font-mono"><?= e($r['affiliation_code']) ?> • <?= e($r['ref_no']) ?></span></td>
          <td><?= e($r['coord_name']) ?><span class="block text-[11px] text-slate-400"><?= e($r['coord_email']) ?></span></td>
          <td class="text-[11px] text-rose-600 max-w-xs"><?= e($r['status_remarks'] ?: '—') ?></td>
          <td class="text-right font-bold <?= $canDelete ? 'text-slate-400' : 'text-amber-600' ?>"><?= (int) $r['teams'] ?></td>
          <td class="text-right"><?= (int) $r['payments'] ?></td>
          <td class="text-right whitespace-nowrap">
            <?php if ($canDelete): ?>
              <form method="post" data-confirm="<?= e('Permanently delete "' . $r['name'] . '" and its login account' . ((int) $r['payments'] ? ', ' . (int) $r['payments'] . ' fund transfer record(s)' : '') . ((int) $r['food'] ? ' and food coupon requests' : '') . '? This cannot be undone.') ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete_institution"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button class="btn-danger btn-sm"><?= icon('trash-2', 'w-3.5 h-3.5') ?>Delete Permanently</button>
              </form>
            <?php else: ?>
              <span class="inline-flex items-center text-[11px] text-amber-700 font-semibold" title="Delete or reject-and-delete its team entries first"><?= icon('lock', 'w-3.5 h-3.5 mr-1') ?>Has team entries</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$institutions): ?><tr><td colspan="6" class="text-center text-slate-400 py-8">No rejected institutions.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
