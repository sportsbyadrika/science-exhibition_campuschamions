<?php
require __DIR__ . '/../includes/bootstrap.php';
[$user, $inst] = require_institution();
$modes = ['NEFT' => 'NEFT', 'RTGS' => 'RTGS', 'IMPS' => 'IMPS', 'UPI' => 'UPI', 'Bank Deposit' => 'Bank Deposit', 'Cheque/DD' => 'Cheque / DD'];

if (is_post()) {
    verify_csrf();
    $action = input('action');

    if ($action === 'delete') {
        $p = db_one('SELECT * FROM payments WHERE id = ? AND institution_id = ? AND status = "pending"', [input_int('payment_id'), $inst['id']]);
        if ($p && !institution_locked($inst)) {
            delete_upload($p['proof_path']);
            db_exec('DELETE FROM payments WHERE id = ?', [$p['id']]);
            log_activity('payment_deleted', $p['utr_no']);
            flash('success', 'Fund transfer entry removed.');
        } else {
            flash('error', 'This entry cannot be removed.');
        }
        redirect('institution/payments');
    }

    $d = [
        'utr_no' => strtoupper(preg_replace('/\s+/', '', (string) input('utr_no'))),
        'amount' => (float) input('amount'),
        'payment_date' => (string) input('payment_date'),
        'payment_mode' => (string) input('payment_mode'),
        'remitter_bank' => (string) input('remitter_bank'),
    ];
    remember_input($d);
    $errors = [];
    if (!preg_match('/^[A-Z0-9\-\/]{6,40}$/', $d['utr_no'])) {
        $errors[] = 'Enter a valid UTR / transaction reference number.';
    } elseif (db_value('SELECT id FROM payments WHERE utr_no = ?', [$d['utr_no']])) {
        $errors[] = 'This UTR / reference number has already been submitted.';
    }
    if ($d['amount'] <= 0 || $d['amount'] > 10000000) {
        $errors[] = 'Enter the amount transferred.';
    }
    $dt = DateTime::createFromFormat('Y-m-d', $d['payment_date']);
    if (!$dt || $dt > new DateTime('tomorrow')) {
        $errors[] = 'Enter a valid transfer date.';
    }
    if (!isset($modes[$d['payment_mode']])) {
        $errors[] = 'Select the payment mode.';
    }
    $proof = null;
    if (!$errors) {
        try {
            $proof = save_uploaded_file($_FILES['proof'] ?? [], 'payments', (int) config('uploads.max_receipt_kb', 5120));
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }
    if ($errors) {
        foreach ($errors as $err) {
            flash('error', $err);
        }
        redirect('institution/payments');
    }
    db_exec(
        'INSERT INTO payments (institution_id, amount, utr_no, payment_date, payment_mode, remitter_bank, proof_path) VALUES (?,?,?,?,?,?,?)',
        [$inst['id'], $d['amount'], $d['utr_no'], $d['payment_date'], $d['payment_mode'], $d['remitter_bank'] ?: null, $proof]
    );
    clear_old();
    log_activity('payment_submitted', $d['utr_no'] . ' ' . $d['amount']);
    notify_admins_payment($inst, $d);
    flash('success', 'Fund transfer details recorded. The Event Admin will verify the remittance.');
    redirect(institution_locked($inst) ? 'institution/payments' : 'institution/submit');
}

$summary = institution_summary((int) $inst['id']);
$payments = institution_payments((int) $inst['id']);
$pageTitle = 'Fund Transfer';
$extraScripts = '<script src="' . asset('vendor/qrcode.js') . '"></script><script src="' . asset('js/upi-qr.js') . '"></script>';
require __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
  <!-- Left: Invoice + submitted transfers -->
  <div class="lg:col-span-7 space-y-6">
    <div class="card p-6 space-y-4">
      <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 flex items-center"><?= icon('calculator', 'w-4 h-4 mr-2 text-cyan-700') ?>Consolidated Fee Invoice</h3>
      <div class="space-y-2 text-xs divide-y divide-slate-100">
        <div class="flex justify-between pt-1"><span class="text-slate-600">Team Entries (<?= $summary['teams_total'] - $summary['teams_rejected'] ?> teams)</span><span class="font-bold text-slate-900"><?= inr($summary['team_fee']) ?></span></div>
        <div class="flex justify-between pt-2"><span class="text-slate-600">Food Coupons (<?= $summary['food_qty'] ?> coupons)</span><span class="font-bold text-slate-900"><?= inr($summary['food_amount']) ?></span></div>
        <div class="flex justify-between pt-3 text-sm"><span class="font-extrabold text-slate-900">Total Remittance Amount</span><span class="font-black text-cyan-800 text-base"><?= inr($summary['payable']) ?></span></div>
        <div class="flex justify-between pt-2"><span class="text-slate-600">Transfers Submitted</span><span class="font-bold text-slate-900"><?= inr($summary['paid_submitted']) ?></span></div>
        <div class="flex justify-between pt-2"><span class="text-slate-600">Verified &amp; Approved</span><span class="font-bold text-emerald-700"><?= inr($summary['paid_approved']) ?></span></div>
        <div class="flex justify-between pt-2 text-sm"><span class="font-extrabold text-slate-900">Balance to Remit</span><span class="font-black text-rose-600"><?= inr($summary['balance']) ?></span></div>
      </div>
    </div>

    <div class="card overflow-hidden">
      <div class="card-head"><h3 class="card-title">Submitted Fund Transfers</h3><span class="text-xs font-bold uppercase <?= tone_text($summary['pay_status']['tone']) ?>"><?= e($summary['pay_status']['label']) ?></span></div>
      <?php if (!$payments): ?>
        <p class="p-8 text-center text-xs text-slate-400">No fund transfer details submitted yet.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>UTR / Ref.</th><th>Date</th><th>Mode</th><th class="!text-right">Amount</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($payments as $p): ?>
              <tr>
                <td class="font-mono font-bold text-cyan-800"><?= e($p['utr_no']) ?><?php if ($p['remitter_bank']): ?><span class="block font-sans font-normal text-[10px] text-slate-400"><?= e($p['remitter_bank']) ?></span><?php endif; ?></td>
                <td><?= e(fmt_date($p['payment_date'])) ?></td>
                <td><?= e($p['payment_mode']) ?></td>
                <td class="text-right font-bold"><?= inr($p['amount']) ?><?php if ($p['status'] === 'approved' && $p['approved_amount'] !== null && (float) $p['approved_amount'] !== (float) $p['amount']): ?><span class="block text-[10px] text-emerald-700">Approved <?= inr($p['approved_amount']) ?></span><?php endif; ?></td>
                <td><?= status_badge($p['status']) ?><?php if ($p['remarks']): ?><span class="block text-[10px] text-slate-500 mt-0.5"><?= e($p['remarks']) ?></span><?php endif; ?></td>
                <td class="text-right whitespace-nowrap">
                  <?php if ($p['proof_path']): ?><a href="<?= media_url('payment', (int) $p['id']) ?>" target="_blank" class="text-cyan-700 hover:underline font-semibold">Receipt</a><?php endif; ?>
                  <?php if ($p['status'] === 'pending' && !institution_locked($inst)): ?>
                    <form method="post" class="inline" data-confirm="Remove this fund transfer entry?">
                      <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
                      <button class="ml-2 text-rose-600 hover:text-rose-800" title="Remove"><?= icon('trash-2', 'w-3.5 h-3.5') ?></button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Right: Bank details + transfer form -->
  <div class="lg:col-span-5 space-y-6">
    <form method="post" enctype="multipart/form-data" class="bg-gradient-to-br from-slate-900 to-cyan-950 text-white rounded-2xl p-6 shadow-xl space-y-5">
      <?= csrf_field() ?>
      <div>
        <span class="text-[10px] font-bold uppercase tracking-wider text-cyan-400 bg-cyan-900/60 px-2 py-0.5 rounded border border-cyan-700/50">Official Bank Account</span>
        <h3 class="text-base font-bold mt-1">NEFT / RTGS / IMPS / UPI Remittance</h3>
      </div>
      <div class="bg-white/10 rounded-xl p-4 space-y-2 text-xs border border-white/10 font-mono">
        <?php foreach ([
            'Account Name:' => [setting('bank_account_name'), 'font-bold text-right'],
            'Bank & Branch:' => [setting('bank_name_branch'), 'font-bold text-right'],
            'Account No:' => [setting('bank_account_no'), 'font-bold text-cyan-300'],
            'IFSC Code:' => [setting('bank_ifsc'), 'font-bold text-cyan-300'],
            'UPI ID:' => [setting('bank_upi'), 'font-bold text-cyan-300'],
        ] as $k => [$v, $c]): if ($v === '') continue; ?>
          <div class="flex justify-between gap-3"><span class="text-slate-300 shrink-0"><?= e($k) ?></span><span class="<?= $c ?> break-all"><?= e($v) ?></span></div>
        <?php endforeach; ?>
      </div>

      <?php $qrMode = upi_qr_mode(); $qrImg = upi_qr_image_url(); $upiNote = trim(setting('upi_note', 'Science Fair') . ' ' . $inst['affiliation_code']); ?>
      <?php if (($qrMode === 'auto' && setting('bank_upi') !== '') || ($qrMode === 'upload' && $qrImg)): ?>
        <div class="bg-white text-slate-900 rounded-xl p-4 flex flex-col sm:flex-row items-center gap-4">
          <div class="w-40 h-40 shrink-0 rounded-lg border border-slate-200 p-1.5 bg-white">
            <?php if ($qrMode === 'auto'): ?>
              <div id="upiQr" class="w-full h-full [&_svg]:w-full [&_svg]:h-full" data-uri="<?= e(upi_pay_uri($summary['balance'], $upiNote)) ?>"></div>
            <?php else: ?>
              <img src="<?= e($qrImg) ?>" alt="UPI QR code" class="w-full h-full object-contain">
            <?php endif; ?>
          </div>
          <div class="text-center sm:text-left space-y-1.5">
            <span class="inline-flex items-center text-[10px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded"><?= icon('qr-code', 'w-3 h-3 mr-1') ?>Scan &amp; Pay by UPI</span>
            <p class="text-xs text-slate-600">Scan with any UPI app (Google Pay, PhonePe, Paytm, BHIM).</p>
            <?php if ($qrMode === 'auto'): ?>
              <p class="text-sm font-black text-slate-900">Amount: <span id="upiAmountText"><?= inr($summary['balance'] > 0 ? $summary['balance'] : 0, true) ?></span></p>
              <p class="text-[11px] text-slate-500">Note: <span class="font-mono"><?= e($upiNote) ?></span></p>
              <a id="upiPayLink" href="<?= e(upi_pay_uri($summary['balance'], $upiNote)) ?>" class="sm:hidden inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-emerald-600 text-white text-xs font-bold rounded-lg"><?= icon('smartphone', 'w-3.5 h-3.5') ?>Pay with UPI app</a>
            <?php else: ?>
              <p class="text-[11px] text-slate-500">Enter the amount in your UPI app and add <span class="font-mono font-bold"><?= e($upiNote) ?></span> as the note.</p>
            <?php endif; ?>
            <p class="text-[11px] text-amber-700 font-semibold">After paying, enter the UPI transaction ID (UTR) below.</p>
          </div>
        </div>
      <?php endif; ?>

      <div class="space-y-3 pt-1">
        <div>
          <label class="block text-xs font-semibold uppercase tracking-wider text-slate-200 mb-1">Transaction Reference / UTR Number <span class="text-red-400">*</span></label>
          <input type="text" name="utr_no" required maxlength="40" value="<?= e(old('utr_no')) ?>" placeholder="e.g., 202610170093847" class="w-full px-3 py-2 text-xs rounded-lg bg-slate-800 border border-slate-700 text-white uppercase font-mono tracking-wider focus:ring-2 focus:ring-cyan-400 outline-none">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-200 mb-1">Amount (₹) <span class="text-red-400">*</span></label>
            <input type="number" id="payAmount" name="amount" required min="1" step="0.01" value="<?= e(old('amount', $summary['balance'] > 0 ? (string) $summary['balance'] : '')) ?>" class="w-full px-3 py-2 text-xs rounded-lg bg-slate-800 border border-slate-700 text-white font-mono focus:ring-2 focus:ring-cyan-400 outline-none">
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-200 mb-1">Transfer Date <span class="text-red-400">*</span></label>
            <input type="date" name="payment_date" required max="<?= date('Y-m-d') ?>" value="<?= e(old('payment_date', date('Y-m-d'))) ?>" class="w-full px-3 py-2 text-xs rounded-lg bg-slate-800 border border-slate-700 text-white focus:ring-2 focus:ring-cyan-400 outline-none [color-scheme:dark]">
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-200 mb-1">Mode <span class="text-red-400">*</span></label>
            <select name="payment_mode" required class="w-full px-3 py-2 text-xs rounded-lg bg-slate-800 border border-slate-700 text-white focus:ring-2 focus:ring-cyan-400 outline-none"><?= select_options($modes, old('payment_mode', 'NEFT')) ?></select>
          </div>
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-200 mb-1">Remitter Bank</label>
            <input type="text" name="remitter_bank" maxlength="150" value="<?= e(old('remitter_bank')) ?>" placeholder="Optional" class="w-full px-3 py-2 text-xs rounded-lg bg-slate-800 border border-slate-700 text-white focus:ring-2 focus:ring-cyan-400 outline-none">
          </div>
        </div>

        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-200 pt-1">Fund Transfer Screenshot / Receipt <span class="text-red-400">*</span></label>
        <label class="block border-2 border-dashed border-slate-700 hover:border-cyan-400 rounded-xl p-4 text-center cursor-pointer bg-slate-800/50">
          <input type="file" name="proof" required accept="image/jpeg,image/png,application/pdf" class="hidden" onchange="document.getElementById('receiptName').textContent = this.files[0] ? this.files[0].name : 'Click to upload payment screenshot'">
          <?= icon('cloud-upload', 'w-6 h-6 text-cyan-400 mx-auto') ?>
          <span class="text-xs font-semibold text-slate-300 block mt-1" id="receiptName">Click to upload payment screenshot</span>
          <span class="text-[10px] text-slate-400 block">JPG, PNG, or PDF up to <?= round(config('uploads.max_receipt_kb', 5120) / 1024) ?>MB</span>
        </label>
      </div>

      <button type="submit" class="w-full py-2.5 bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs uppercase tracking-wider rounded-xl transition shadow flex items-center justify-center">
        <?= icon('check', 'w-4 h-4 mr-2') ?>Record Payment &amp; Proceed
      </button>
    </form>
  </div>
</div>
<?php clear_old(); require __DIR__ . '/../includes/layout/app_footer.php';
