<?php
require __DIR__ . '/../includes/bootstrap.php';
[$user, $inst] = require_institution();
$locked = institution_locked($inst);

if (is_post()) {
    verify_csrf();
    if ($locked) {
        flash('error', 'Your application is finally submitted. Food coupon requests are locked.');
        redirect('institution/food-coupons');
    }
    $qty = $_POST['qty'] ?? [];
    $items = db_all('SELECT * FROM food_items WHERE status = "active"');
    foreach ($items as $item) {
        $q = max(0, min(5000, (int) ($qty[$item['id']] ?? 0)));
        if ($q === 0) {
            db_exec('DELETE FROM food_requests WHERE institution_id = ? AND food_item_id = ?', [$inst['id'], $item['id']]);
            continue;
        }
        db_exec(
            'INSERT INTO food_requests (institution_id, food_item_id, quantity, rate, amount) VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), rate = VALUES(rate), amount = VALUES(amount)',
            [$inst['id'], $item['id'], $q, $item['rate'], $q * (float) $item['rate']]
        );
    }
    log_activity('food_request_saved', $inst['affiliation_code']);
    flash('success', 'Food coupon requisition saved.');
    redirect(input('next') === 'payments' ? 'institution/payments' : 'institution/food-coupons');
}

$food = institution_food((int) $inst['id']);
$summary = institution_summary((int) $inst['id']);
$pageTitle = 'Food Coupons';
require __DIR__ . '/../includes/layout/app_header.php';
?>
<form method="post" class="grid grid-cols-1 lg:grid-cols-12 gap-8" id="foodForm">
  <?= csrf_field() ?>
  <div class="lg:col-span-7 space-y-6">
    <div class="card p-6 space-y-5">
      <div class="flex items-center space-x-2 border-b border-slate-100 pb-3">
        <div class="p-2 bg-amber-50 text-amber-700 rounded-lg"><?= icon('utensils', 'w-5 h-5') ?></div>
        <div>
          <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900">Food Coupon Requisition</h3>
          <p class="text-xs text-slate-500">Head-wise meal coupons for registered participants and accompanying teachers.</p>
        </div>
      </div>

      <?php if (!$food): ?>
        <p class="text-xs text-slate-500 text-center py-6">Food coupon heads have not been configured yet.</p>
      <?php endif; ?>

      <div class="space-y-3">
        <?php foreach ($food as $f): ?>
          <div class="food-row border border-slate-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3" data-rate="<?= e($f['rate']) ?>">
            <div>
              <span class="text-sm font-bold text-slate-900"><?= e($f['name']) ?></span>
              <?php if ($f['meal_date']): ?><span class="ml-2 text-[10px] font-bold uppercase text-teal-700 bg-teal-50 px-2 py-0.5 rounded"><?= e(fmt_date($f['meal_date'])) ?></span><?php endif; ?>
              <p class="text-xs text-slate-500"><?= e($f['description']) ?></p>
              <p class="text-xs font-bold text-amber-800 mt-1"><?= inr($f['rate']) ?> <span class="font-normal text-amber-700">per coupon</span></p>
            </div>
            <div class="flex items-center gap-4">
              <div class="flex items-center space-x-2">
                <button type="button" class="qty-btn w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center font-bold text-slate-700 text-lg border border-slate-200" data-delta="-1" <?= $locked ? 'disabled' : '' ?>>-</button>
                <input type="number" name="qty[<?= (int) $f['id'] ?>]" min="0" max="5000" value="<?= (int) $f['quantity'] ?>" class="qty-input w-20 text-center py-2 text-sm font-bold border border-slate-300 rounded-lg focus:ring-2 focus:ring-amber-500 outline-none" <?= $locked ? 'disabled' : '' ?>>
                <button type="button" class="qty-btn w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center font-bold text-slate-700 text-lg border border-slate-200" data-delta="1" <?= $locked ? 'disabled' : '' ?>>+</button>
              </div>
              <div class="w-24 text-right"><span class="text-[10px] text-slate-500 uppercase block">Subtotal</span><span class="row-total text-sm font-bold text-slate-800"><?= inr($f['amount']) ?></span></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="text-[11px] text-slate-400">Recommended: (Students × meals) + accompanying teachers. Registered students: <strong><?= $summary['students'] ?></strong>.</p>
    </div>
  </div>

  <div class="lg:col-span-5 space-y-6">
    <div class="card p-6 space-y-4">
      <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 flex items-center"><?= icon('calculator', 'w-4 h-4 mr-2 text-cyan-700') ?>Consolidated Fee Invoice</h3>
      <div class="space-y-2 text-xs divide-y divide-slate-100">
        <div class="flex justify-between pt-1"><span class="text-slate-600">Team Entries (<?= $summary['teams_total'] - $summary['teams_rejected'] ?> teams)</span><span class="font-bold text-slate-900" data-team-fee="<?= e($summary['team_fee']) ?>"><?= inr($summary['team_fee']) ?></span></div>
        <div class="flex justify-between pt-2"><span class="text-slate-600">Food Coupons (<span id="couponCount"><?= $summary['food_qty'] ?></span> coupons)</span><span class="font-bold text-slate-900" id="couponTotal"><?= inr($summary['food_amount']) ?></span></div>
        <div class="flex justify-between pt-3 text-sm"><span class="font-extrabold text-slate-900">Total Remittance Amount</span><span class="font-black text-cyan-800 text-base" id="grandTotal"><?= inr($summary['payable']) ?></span></div>
      </div>
      <?php if ($locked): ?>
        <p class="text-xs text-slate-500 flex items-center"><?= icon('lock', 'w-3.5 h-3.5 mr-1') ?>Locked after final submission.</p>
      <?php else: ?>
        <div class="flex flex-col sm:flex-row gap-2 pt-2">
          <button type="submit" class="btn-dark flex-1"><?= icon('save') ?>Save Request</button>
          <button type="submit" name="next" value="payments" class="btn-primary flex-1">Save &amp; Proceed to Payment <?= icon('arrow-right') ?></button>
        </div>
      <?php endif; ?>
    </div>
  </div>
</form>
<script>
(function () {
  const fmt = (n) => '₹ ' + Number(n).toLocaleString('en-IN');
  const teamFee = Number(document.querySelector('[data-team-fee]').dataset.teamFee);
  function recalc() {
    let qty = 0, amt = 0;
    document.querySelectorAll('.food-row').forEach((row) => {
      const input = row.querySelector('.qty-input');
      const q = Math.max(0, parseInt(input.value, 10) || 0);
      const sub = q * Number(row.dataset.rate);
      row.querySelector('.row-total').textContent = fmt(sub);
      qty += q; amt += sub;
    });
    document.getElementById('couponCount').textContent = qty;
    document.getElementById('couponTotal').textContent = fmt(amt);
    document.getElementById('grandTotal').textContent = fmt(teamFee + amt);
  }
  document.querySelectorAll('.qty-btn').forEach((b) => b.addEventListener('click', () => {
    const input = b.parentElement.querySelector('.qty-input');
    input.value = Math.max(0, (parseInt(input.value, 10) || 0) + Number(b.dataset.delta));
    recalc();
  }));
  document.querySelectorAll('.qty-input').forEach((i) => i.addEventListener('input', recalc));
})();
</script>
<?php require __DIR__ . '/../includes/layout/app_footer.php';
