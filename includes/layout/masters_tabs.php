<?php $mt = current_path(); ?>
<div class="flex flex-wrap gap-2 mb-6">
  <?php foreach ([['admin/events', 'Event Master', 'flask-conical', ['admin/events', 'admin/event-form']], ['admin/categories', 'Categories & Classes', 'layers', ['admin/categories']], ['admin/food-items', 'Food Coupon Heads', 'utensils', ['admin/food-items']]] as [$p, $l, $i, $m]): ?>
    <a href="<?= url($p) ?>" class="<?= in_array($mt, $m, true) ? 'btn-dark' : 'btn-light' ?> btn-sm"><?= icon($i, 'w-3.5 h-3.5') ?><?= e($l) ?></a>
  <?php endforeach; ?>
</div>
