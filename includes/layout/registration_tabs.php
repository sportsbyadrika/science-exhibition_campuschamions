<?php $rt = current_path(); ?>
<div class="flex flex-wrap gap-2 mb-6">
  <a href="<?= url('institution/teams') ?>" class="<?= $rt === 'institution/teams' ? 'btn-dark' : 'btn-light' ?> btn-sm"><?= icon('users-2', 'w-3.5 h-3.5') ?>Registered Teams</a>
  <a href="<?= url('institution/reports') ?>" class="<?= $rt === 'institution/reports' ? 'btn-dark' : 'btn-light' ?> btn-sm"><?= icon('printer', 'w-3.5 h-3.5') ?>Reports</a>
</div>
