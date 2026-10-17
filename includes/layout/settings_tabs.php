<?php $st = current_path(); ?>
<div class="flex flex-wrap gap-2 mb-6">
  <a href="<?= url('admin/settings') ?>" class="<?= $st === 'admin/settings' ? 'btn-dark' : 'btn-light' ?> btn-sm"><?= icon('sliders', 'w-3.5 h-3.5') ?>Portal Settings</a>
  <?php if (is_superadmin()): ?>
    <a href="<?= url('admin/activity') ?>" class="<?= $st === 'admin/activity' ? 'btn-dark' : 'btn-light' ?> btn-sm"><?= icon('history', 'w-3.5 h-3.5') ?>Activity Log</a>
  <?php endif; ?>
</div>
