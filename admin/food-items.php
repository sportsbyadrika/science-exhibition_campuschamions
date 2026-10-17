<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $id = input_int('id');
    if (input('action') === 'delete') {
        if (db_value('SELECT COUNT(*) FROM food_requests WHERE food_item_id = ?', [$id])) {
            flash('error', 'Institutions have requested this coupon head. Mark it inactive instead.');
        } else {
            db_exec('DELETE FROM food_items WHERE id = ?', [$id]);
            flash('success', 'Food coupon head deleted.');
        }
        redirect('admin/food-items');
    }
    $date = (string) input('meal_date');
    $d = [(string) input('name'), (string) input('description'), $date !== '' && DateTime::createFromFormat('Y-m-d', $date) ? $date : null, max(0, (float) input('rate')), input_int('sort_order'), input('status') === 'inactive' ? 'inactive' : 'active'];
    if ($d[0] === '') {
        flash('error', 'Enter the coupon head name.');
        redirect('admin/food-items', $id ? ['edit' => $id] : []);
    }
    if ($id) {
        db_exec('UPDATE food_items SET name = ?, description = ?, meal_date = ?, rate = ?, sort_order = ?, status = ? WHERE id = ?', [...$d, $id]);
    } else {
        db_exec('INSERT INTO food_items (name, description, meal_date, rate, sort_order, status) VALUES (?,?,?,?,?,?)', $d);
    }
    log_activity('food_item_saved', $d[0] . ' @ ' . $d[3]);
    flash('success', 'Food coupon head "' . $d[0] . '" saved. New requests will use the updated rate.');
    redirect('admin/food-items');
}

$rows = db_all('SELECT f.*, (SELECT COALESCE(SUM(quantity),0) FROM food_requests r WHERE r.food_item_id = f.id) qty FROM food_items f ORDER BY sort_order, meal_date, id');
$edit = ($e = input_int('edit')) ? db_one('SELECT * FROM food_items WHERE id = ?', [$e]) : null;

$pageTitle = 'Food Coupon Heads';
require __DIR__ . '/../includes/layout/app_header.php';
require __DIR__ . '/../includes/layout/masters_tabs.php';
?>
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
  <div class="lg:col-span-8 card overflow-hidden">
    <div class="card-head"><div><h2 class="card-title">Food Coupon Heads &amp; Rates</h2><p class="text-xs text-slate-500">Breakfast, lunch, snacks etc. with the rate per coupon.</p></div></div>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>#</th><th>Coupon Head</th><th>Date</th><th class="!text-right">Rate</th><th class="!text-right">Requested</th><th>Status</th><th class="!text-right">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="text-slate-400"><?= (int) $r['sort_order'] ?></td>
            <td><span class="font-semibold"><?= e($r['name']) ?></span><span class="block text-[11px] text-slate-400"><?= e($r['description']) ?></span></td>
            <td><?= e(fmt_date($r['meal_date'])) ?></td>
            <td class="text-right font-bold"><?= inr($r['rate']) ?></td>
            <td class="text-right"><?= (int) $r['qty'] ?></td>
            <td><?= status_badge($r['status']) ?></td>
            <td class="text-right whitespace-nowrap">
              <a href="<?= url('admin/food-items', ['edit' => $r['id']]) ?>" class="btn-light btn-sm"><?= icon('pencil', 'w-3.5 h-3.5') ?></a>
              <form method="post" class="inline" data-confirm="Delete this coupon head?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn-light btn-sm !text-rose-600"><?= icon('trash-2', 'w-3.5 h-3.5') ?></button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-slate-400 py-8">No coupon heads yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <form method="post" class="lg:col-span-4 card p-6 space-y-4 self-start">
    <?= csrf_field() ?>
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
    <h3 class="card-title"><?= $edit ? 'Edit Coupon Head' : 'Add Coupon Head' ?></h3>
    <div><label class="form-label">Name *</label><input name="name" required maxlength="100" value="<?= e($edit['name'] ?? '') ?>" placeholder="Lunch" class="form-input"></div>
    <div><label class="form-label">Description</label><input name="description" maxlength="255" value="<?= e($edit['description'] ?? '') ?>" placeholder="Vegetarian lunch coupon" class="form-input"></div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="form-label">Rate (₹) *</label><input type="number" step="0.01" min="0" name="rate" required value="<?= e($edit['rate'] ?? '') ?>" class="form-input"></div>
      <div><label class="form-label">Meal Date</label><input type="date" name="meal_date" value="<?= e($edit['meal_date'] ?? '') ?>" class="form-input"></div>
      <div><label class="form-label">Order</label><input type="number" name="sort_order" value="<?= e($edit['sort_order'] ?? count($rows) + 1) ?>" class="form-input"></div>
      <div><label class="form-label">Status</label><select name="status" class="form-input"><?= select_options(['active' => 'Active', 'inactive' => 'Inactive'], $edit['status'] ?? 'active') ?></select></div>
    </div>
    <div class="flex gap-2">
      <?php if ($edit): ?><a href="<?= url('admin/food-items') ?>" class="btn-light flex-1">Cancel</a><?php endif; ?>
      <button class="btn-primary flex-1"><?= icon('save') ?>Save</button>
    </div>
  </form>
</div>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
