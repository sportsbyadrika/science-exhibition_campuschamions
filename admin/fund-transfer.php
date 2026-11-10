<?php
/**
 * Admin only: view / edit one fund transfer inside a modal.
 *   GET  /admin/fund-transfer?id=..&mode=view|edit   -> modal content (HTML fragment)
 *   POST /admin/fund-transfer                         -> save, then back to `return`
 */
require __DIR__ . '/../includes/bootstrap.php';
$admin = require_admin();

$p = db_one(
    'SELECT p.*, i.name institution_name, i.affiliation_code, u.name reviewer_name
       FROM payments p JOIN institutions i ON i.id = p.institution_id LEFT JOIN users u ON u.id = p.reviewed_by
      WHERE p.id = ?',
    [input_int('id')]
);
if (!$p) {
    abort(404, 'Fund transfer not found.');
}
$modes = ['NEFT' => 'NEFT', 'RTGS' => 'RTGS', 'IMPS' => 'IMPS', 'UPI' => 'UPI', 'Bank Deposit' => 'Bank Deposit', 'Cheque/DD' => 'Cheque / DD'];
$return = safe_return('admin/institution?id=' . $p['institution_id']);

if (is_post()) {
    verify_csrf();
    $d = [
        'utr_no' => strtoupper(preg_replace('/\s+/', '', (string) input('utr_no'))),
        'amount' => (float) input('amount'),
        'payment_date' => (string) input('payment_date'),
        'payment_mode' => (string) input('payment_mode'),
        'remitter_bank' => (string) input('remitter_bank'),
        'status' => (string) input('status'),
        'approved_amount' => input('approved_amount') === '' ? null : (float) input('approved_amount'),
        'remarks' => mb_substr((string) input('remarks'), 0, 255),
    ];
    $errors = [];
    if (!preg_match('/^[A-Z0-9\-\/]{6,40}$/', $d['utr_no'])) $errors[] = 'Enter a valid UTR / transaction reference number.';
    elseif (db_value('SELECT id FROM payments WHERE utr_no = ? AND id <> ?', [$d['utr_no'], $p['id']])) $errors[] = 'This UTR / reference number is already used by another fund transfer.';
    if ($d['amount'] <= 0 || $d['amount'] > 10000000) $errors[] = 'Enter a valid amount.';
    $dt = DateTime::createFromFormat('Y-m-d', $d['payment_date']);
    if (!$dt || $dt->format('Y-m-d') !== $d['payment_date']) $errors[] = 'Enter a valid transfer date.';
    if (!isset($modes[$d['payment_mode']])) $errors[] = 'Select the payment mode.';
    if (!in_array($d['status'], ['pending', 'approved', 'rejected'], true)) $errors[] = 'Select a valid status.';
    if ($d['approved_amount'] !== null && $d['approved_amount'] < 0) $errors[] = 'Approved amount cannot be negative.';

    $newProof = null;
    if (!$errors && ($_FILES['proof']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        try {
            $newProof = save_uploaded_file($_FILES['proof'], 'payments', (int) config('uploads.max_receipt_kb', 5120));
        } catch (RuntimeException $ex) {
            $errors[] = 'Receipt: ' . $ex->getMessage();
        }
    }

    if (!$errors) {
        if ($d['status'] === 'approved' && $d['approved_amount'] === null) {
            $d['approved_amount'] = $d['amount'];
        }
        if ($d['status'] !== 'approved') {
            $d['approved_amount'] = null;
        }
        $statusChanged = $d['status'] !== $p['status'];
        db_exec(
            'UPDATE payments SET utr_no = ?, amount = ?, payment_date = ?, payment_mode = ?, remitter_bank = ?, status = ?, approved_amount = ?, remarks = ?,
                    proof_path = COALESCE(?, proof_path), reviewed_by = IF(?, ?, reviewed_by), reviewed_at = IF(?, NOW(), reviewed_at) WHERE id = ?',
            [$d['utr_no'], $d['amount'], $d['payment_date'], $d['payment_mode'], $d['remitter_bank'] ?: null, $d['status'], $d['approved_amount'], $d['remarks'] ?: null,
             $newProof, $statusChanged ? 1 : 0, $admin['id'], $statusChanged ? 1 : 0, $p['id']]
        );
        if ($newProof && $p['proof_path']) {
            delete_upload($p['proof_path']);
        }
        log_activity('payment_edited_by_admin', $d['utr_no'] . ($d['utr_no'] !== $p['utr_no'] ? ' (was ' . $p['utr_no'] . ')' : '') . ' ' . $d['amount'] . ' ' . $d['status']);
        flash('success', 'Fund transfer ' . $d['utr_no'] . ' updated.');
        redirect_to($return);
    }
    foreach ($errors as $err) flash('error', $err);
    $_SESSION['_modal_old'] = ['pay' . $p['id'] => $_POST];
    redirect_to(preg_replace('/([?&])modal=[^&]*(&|$)/', '$1', $return), ['modal' => url('admin/fund-transfer', ['id' => $p['id'], 'mode' => 'edit', 'return' => $return])]);
}

// ---------------- Modal content ----------------
$mode = input('mode') === 'edit' ? 'edit' : 'view';
if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'fetch') {
    redirect_to($return, ['modal' => url('admin/fund-transfer', ['id' => $p['id'], 'mode' => $mode, 'return' => $return])]);
}
$old = $_SESSION['_modal_old']['pay' . $p['id']] ?? null;
unset($_SESSION['_modal_old']);
if ($old) {
    $p = array_merge($p, array_intersect_key($old, array_flip(['utr_no', 'amount', 'payment_date', 'payment_mode', 'remitter_bank', 'status', 'approved_amount', 'remarks'])));
}
$proofFile = upload_path($p['proof_path']);
$isPdf = $proofFile && str_ends_with($proofFile, '.pdf');
header('Content-Type: text/html; charset=utf-8');
?>
<div class="bg-gradient-to-r from-slate-900 to-cyan-950 px-6 py-4 text-white flex items-start justify-between gap-4">
  <div>
    <span class="text-[10px] font-bold tracking-widest text-cyan-400 uppercase"><?= $mode === 'edit' ? 'Edit Fund Transfer' : 'Fund Transfer Details' ?></span>
    <h3 class="text-lg font-extrabold font-mono"><?= e($p['utr_no']) ?></h3>
    <p class="text-xs text-slate-300"><?= e($p['institution_name']) ?> • Aff. <?= e($p['affiliation_code']) ?></p>
  </div>
  <button type="button" data-modal-close class="text-slate-400 hover:text-white"><?= icon('x', 'w-5 h-5') ?></button>
</div>

<?php if ($mode === 'view'): ?>
  <div class="p-6 grid grid-cols-1 sm:grid-cols-5 gap-6 text-xs">
    <dl class="sm:col-span-3 grid grid-cols-2 gap-4 content-start">
      <div><dt class="text-[11px] uppercase font-semibold text-slate-500">Amount</dt><dd class="text-base font-black text-slate-900"><?= inr($p['amount'], true) ?></dd></div>
      <div><dt class="text-[11px] uppercase font-semibold text-slate-500">Status</dt><dd><?= status_badge($p['status']) ?></dd></div>
      <div><dt class="text-[11px] uppercase font-semibold text-slate-500">Approved Amount</dt><dd class="font-bold text-emerald-700"><?= $p['status'] === 'approved' ? inr($p['approved_amount'] ?? $p['amount'], true) : '—' ?></dd></div>
      <div><dt class="text-[11px] uppercase font-semibold text-slate-500">Transfer Date</dt><dd class="font-semibold"><?= e(fmt_date($p['payment_date'])) ?></dd></div>
      <div><dt class="text-[11px] uppercase font-semibold text-slate-500">Mode</dt><dd class="font-semibold"><?= e($p['payment_mode']) ?></dd></div>
      <div><dt class="text-[11px] uppercase font-semibold text-slate-500">Remitter Bank</dt><dd class="font-semibold"><?= e($p['remitter_bank'] ?: '—') ?></dd></div>
      <div><dt class="text-[11px] uppercase font-semibold text-slate-500">Submitted</dt><dd><?= e(fmt_datetime($p['created_at'])) ?></dd></div>
      <div><dt class="text-[11px] uppercase font-semibold text-slate-500">Reviewed</dt><dd><?= e(fmt_datetime($p['reviewed_at'])) ?><?= $p['reviewer_name'] ? '<br><span class="text-slate-500">by ' . e($p['reviewer_name']) . '</span>' : '' ?></dd></div>
      <?php if ($p['remarks']): ?><div class="col-span-2"><dt class="text-[11px] uppercase font-semibold text-slate-500">Remarks</dt><dd class="text-rose-700"><?= e($p['remarks']) ?></dd></div><?php endif; ?>
    </dl>
    <div class="sm:col-span-2">
      <span class="text-[11px] uppercase font-semibold text-slate-500">Receipt</span>
      <?php if (!$proofFile): ?>
        <p class="mt-1 text-slate-400">No receipt uploaded.</p>
      <?php elseif ($isPdf): ?>
        <a href="<?= media_url('payment', (int) $p['id']) ?>" target="_blank" class="mt-1 flex items-center gap-2 border border-slate-200 rounded-xl p-4 hover:border-cyan-300"><?= icon('file-text', 'w-6 h-6 text-rose-600') ?><span class="font-semibold">Open PDF receipt</span></a>
      <?php else: ?>
        <a href="<?= media_url('payment', (int) $p['id']) ?>" target="_blank" title="Open full size"><img src="<?= media_url('payment', (int) $p['id']) ?>" alt="Receipt" class="mt-1 w-full max-h-72 object-contain rounded-xl border border-slate-200 bg-slate-50"></a>
      <?php endif; ?>
    </div>
  </div>
  <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
    <button type="button" data-modal-close class="btn-light btn-sm">Close</button>
    <button type="button" data-modal-url="<?= e(url('admin/fund-transfer', ['id' => $p['id'], 'mode' => 'edit', 'return' => $return])) ?>" class="btn-primary btn-sm"><?= icon('pencil', 'w-3.5 h-3.5') ?>Edit</button>
  </div>

<?php else: ?>
  <form method="post" action="<?= url('admin/fund-transfer') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="return" value="<?= e($return) ?>">
    <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4 max-h-[65vh] overflow-y-auto">
      <div class="sm:col-span-2"><label class="form-label">UTR / Reference No. *</label><input name="utr_no" required maxlength="40" value="<?= e($p['utr_no']) ?>" class="form-input-sm font-mono uppercase"></div>
      <div><label class="form-label">Amount (₹) *</label><input type="number" name="amount" required min="1" step="0.01" value="<?= e($p['amount']) ?>" class="form-input-sm"></div>
      <div><label class="form-label">Transfer Date *</label><input type="date" name="payment_date" required value="<?= e($p['payment_date']) ?>" class="form-input-sm"></div>
      <div><label class="form-label">Mode *</label><select name="payment_mode" class="form-input-sm"><?= select_options($modes, $p['payment_mode']) ?></select></div>
      <div><label class="form-label">Remitter Bank</label><input name="remitter_bank" maxlength="150" value="<?= e($p['remitter_bank'] ?? '') ?>" class="form-input-sm"></div>
      <div><label class="form-label">Status</label><select name="status" class="form-input-sm"><?= select_options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'], $p['status']) ?></select></div>
      <div><label class="form-label">Approved Amount (₹)</label><input type="number" name="approved_amount" min="0" step="0.01" value="<?= e($p['approved_amount'] ?? '') ?>" placeholder="Same as amount" class="form-input-sm"><p class="form-hint">Used only when the status is Approved.</p></div>
      <div class="sm:col-span-2"><label class="form-label">Remarks</label><input name="remarks" maxlength="255" value="<?= e($p['remarks'] ?? '') ?>" class="form-input-sm" placeholder="Optional"></div>
      <div class="sm:col-span-2">
        <label class="form-label">Replace Receipt</label>
        <input type="file" name="proof" accept="image/jpeg,image/png,application/pdf" class="block w-full text-[11px] file:mr-2 file:px-2 file:py-1 file:rounded file:border-0 file:bg-slate-100 file:text-slate-700">
        <p class="form-hint">Optional – JPG, PNG or PDF. <?= $proofFile ? 'Leave empty to keep the current receipt.' : '' ?></p>
      </div>
    </div>
    <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-between gap-2">
      <button type="button" data-modal-url="<?= e(url('admin/fund-transfer', ['id' => $p['id'], 'mode' => 'view', 'return' => $return])) ?>" class="btn-light btn-sm"><?= icon('eye', 'w-3.5 h-3.5') ?>View</button>
      <div class="flex gap-2">
        <button type="button" data-modal-close class="btn-light btn-sm">Cancel</button>
        <button class="btn-primary btn-sm"><?= icon('save', 'w-3.5 h-3.5') ?>Save Changes</button>
      </div>
    </div>
  </form>
<?php endif;
