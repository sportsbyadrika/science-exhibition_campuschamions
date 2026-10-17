<?php
require __DIR__ . '/../includes/bootstrap.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $id = input_int('id');
    if (input('action') === 'delete') {
        if (db_value('SELECT COUNT(*) FROM teams WHERE category_id = ?', [$id])) {
            flash('error', 'This category has team entries and cannot be deleted. Mark it inactive instead.');
        } else {
            db_exec('DELETE FROM categories WHERE id = ?', [$id]);
            flash('success', 'Category deleted.');
        }
        redirect('admin/categories');
    }
    $classes = implode(', ', array_filter(array_map('trim', explode(',', (string) input('classes')))));
    $d = [(string) input('name'), (string) input('description'), $classes, input_int('sort_order'), input('status') === 'inactive' ? 'inactive' : 'active'];
    if ($d[0] === '' || $classes === '') {
        flash('error', 'Category name and classes are required.');
        redirect('admin/categories', $id ? ['edit' => $id] : []);
    }
    if ($id) {
        db_exec('UPDATE categories SET name = ?, description = ?, classes = ?, sort_order = ?, status = ? WHERE id = ?', [...$d, $id]);
    } else {
        db_exec('INSERT INTO categories (name, description, classes, sort_order, status) VALUES (?,?,?,?,?)', $d);
    }
    log_activity('category_saved', $d[0]);
    flash('success', 'Category "' . $d[0] . '" saved.');
    redirect('admin/categories');
}

$rows = db_all('SELECT c.*, (SELECT COUNT(*) FROM teams t WHERE t.category_id = c.id) teams FROM categories c ORDER BY sort_order, name');
$edit = ($e = input_int('edit')) ? db_one('SELECT * FROM categories WHERE id = ?', [$e]) : null;

$pageTitle = 'Categories';
require __DIR__ . '/../includes/layout/app_header.php';
require __DIR__ . '/../includes/layout/masters_tabs.php';
?>
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
  <div class="lg:col-span-8 card overflow-hidden">
    <div class="card-head"><div><h2 class="card-title">Categories &amp; Class Brackets</h2><p class="text-xs text-slate-500">Students' classes allowed in each category.</p></div></div>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>#</th><th>Category</th><th>Classes</th><th class="!text-right">Teams</th><th>Status</th><th class="!text-right">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="text-slate-400"><?= (int) $r['sort_order'] ?></td>
            <td><span class="font-semibold"><?= e($r['name']) ?></span><span class="block text-[11px] text-slate-400"><?= e($r['description']) ?></span></td>
            <td><?php foreach (category_classes($r) as $c): ?><span class="inline-block text-[10px] font-semibold bg-cyan-50 text-cyan-800 border border-cyan-200 rounded px-1.5 py-0.5 mr-1 mb-1"><?= e($c) ?></span><?php endforeach; ?></td>
            <td class="text-right"><?= (int) $r['teams'] ?></td>
            <td><?= status_badge($r['status']) ?></td>
            <td class="text-right whitespace-nowrap">
              <a href="<?= url('admin/categories', ['edit' => $r['id']]) ?>" class="btn-light btn-sm"><?= icon('pencil', 'w-3.5 h-3.5') ?></a>
              <form method="post" class="inline" data-confirm="Delete this category?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn-light btn-sm !text-rose-600"><?= icon('trash-2', 'w-3.5 h-3.5') ?></button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <form method="post" class="lg:col-span-4 card p-6 space-y-4 self-start">
    <?= csrf_field() ?>
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
    <h3 class="card-title"><?= $edit ? 'Edit Category' : 'Add Category' ?></h3>
    <div><label class="form-label">Name *</label><input name="name" required maxlength="100" value="<?= e($edit['name'] ?? '') ?>" placeholder="Category 1" class="form-input"></div>
    <div><label class="form-label">Description</label><input name="description" maxlength="255" value="<?= e($edit['description'] ?? '') ?>" placeholder="Students from Class 2 and 3" class="form-input"></div>
    <div><label class="form-label">Classes *</label><input name="classes" required maxlength="255" value="<?= e($edit['classes'] ?? '') ?>" placeholder="Class 2, Class 3" class="form-input"><p class="form-hint">Comma-separated class names</p></div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="form-label">Order</label><input type="number" name="sort_order" value="<?= e($edit['sort_order'] ?? count($rows) + 1) ?>" class="form-input"></div>
      <div><label class="form-label">Status</label><select name="status" class="form-input"><?= select_options(['active' => 'Active', 'inactive' => 'Inactive'], $edit['status'] ?? 'active') ?></select></div>
    </div>
    <div class="flex gap-2">
      <?php if ($edit): ?><a href="<?= url('admin/categories') ?>" class="btn-light flex-1">Cancel</a><?php endif; ?>
      <button class="btn-primary flex-1"><?= icon('save') ?>Save</button>
    </div>
  </form>
</div>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
