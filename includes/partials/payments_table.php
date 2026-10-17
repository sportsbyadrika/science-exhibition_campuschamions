<?php
/**
 * Admin fund transfer table with verify / reject actions.
 * Expects: $payments (rows; institution_name & affiliation_code when $showInstitution)
 */
$showInstitution ??= false;
?>
<div class="table-wrap">
  <table class="data-table">
    <thead><tr>
      <?php if ($showInstitution): ?><th>Institution</th><?php endif; ?>
      <th>UTR / Reference</th><th>Date &amp; Mode</th><th class="!text-right">Amount</th><th>Proof</th><th>Status</th><th class="!text-right">Verification</th>
    </tr></thead>
    <tbody>
    <?php foreach ($payments as $p): ?>
      <tr>
        <?php if ($showInstitution): ?>
          <td><a href="<?= url('admin/institution', ['id' => $p['institution_id']]) ?>" class="font-semibold text-slate-900 hover:text-cyan-700"><?= e($p['institution_name']) ?></a><span class="block text-[11px] text-slate-400 font-mono"><?= e($p['affiliation_code']) ?></span></td>
        <?php endif; ?>
        <td class="font-mono font-bold text-cyan-800"><?= e($p['utr_no']) ?><?php if ($p['remitter_bank']): ?><span class="block font-sans font-normal text-[10px] text-slate-400"><?= e($p['remitter_bank']) ?></span><?php endif; ?></td>
        <td><?= e(fmt_date($p['payment_date'])) ?><span class="block text-[10px] text-slate-400"><?= e($p['payment_mode']) ?> • submitted <?= e(fmt_date($p['created_at'])) ?></span></td>
        <td class="text-right font-bold"><?= inr($p['amount']) ?><?php if ($p['status'] === 'approved'): ?><span class="block text-[10px] text-emerald-700">Approved <?= inr($p['approved_amount'] ?? $p['amount']) ?></span><?php endif; ?></td>
        <td><?php if ($p['proof_path']): ?><a href="<?= media_url('payment', (int) $p['id']) ?>" target="_blank" class="inline-flex items-center text-cyan-700 hover:underline font-semibold"><?= icon('paperclip', 'w-3.5 h-3.5 mr-1') ?>View</a><?php else: ?>—<?php endif; ?></td>
        <td><?= status_badge($p['status']) ?><?php if ($p['remarks']): ?><span class="block text-[10px] text-slate-500 mt-0.5"><?= e($p['remarks']) ?></span><?php endif; ?></td>
        <td class="text-right whitespace-nowrap">
          <div class="inline-flex items-center gap-1">
            <?php if ($p['status'] !== 'approved'): ?>
              <form method="post" class="inline-flex items-center gap-1" data-confirm="Approve this fund transfer?">
                <?= csrf_field() ?><input type="hidden" name="action" value="payment_status"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="status" value="approved">
                <input type="number" step="0.01" min="0" name="approved_amount" value="<?= e($p['amount']) ?>" title="Approved amount" class="w-24 px-2 py-1 text-xs border border-slate-300 rounded-lg text-right">
                <button class="btn-success btn-sm" title="Approve"><?= icon('check', 'w-3.5 h-3.5') ?></button>
              </form>
            <?php endif; ?>
            <?php if ($p['status'] !== 'rejected'): ?>
              <form method="post" data-prompt="Reason for rejection (sent to the institution):">
                <?= csrf_field() ?><input type="hidden" name="action" value="payment_status"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="status" value="rejected"><input type="hidden" name="remarks">
                <button class="btn-danger btn-sm" title="Reject"><?= icon('x', 'w-3.5 h-3.5') ?></button>
              </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$payments): ?><tr><td colspan="<?= $showInstitution ? 7 : 6 ?>" class="text-center text-slate-400 py-8">No fund transfers found.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
