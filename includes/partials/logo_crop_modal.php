<?php /** Square logo cropper modal used with assets/js/logo-crop.js. Optional: $cropTitle, $cropSize */ ?>
<!-- MODAL: LOGO CROPPER -->
<div id="logoCropModal" data-size="<?= (int) ($cropSize ?? 400) ?>" class="hidden fixed inset-0 z-50 bg-black/75 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl">
    <div class="p-4 border-b border-slate-200 flex items-center justify-between bg-slate-900 text-white">
      <h3 class="text-sm font-bold flex items-center"><?= icon('crop', 'w-4 h-4 mr-2 text-cyan-400') ?><?= e($cropTitle ?? 'Crop Logo') ?></h3>
      <button type="button" data-logo-close class="text-slate-400 hover:text-white"><?= icon('x', 'w-5 h-5') ?></button>
    </div>
    <div class="p-4 bg-[repeating-conic-gradient(#e2e8f0_0_25%,#f8fafc_0_50%)] bg-[length:20px_20px] max-h-[60vh] flex items-center justify-center overflow-hidden">
      <img id="logoCropTarget" src="" alt="Crop area" class="max-w-full max-h-[50vh]">
    </div>
    <div class="p-4 border-t border-slate-200 flex items-center justify-between bg-slate-50">
      <span class="text-[11px] text-slate-500 font-mono">Square • saved at <?= (int) ($cropSize ?? 400) ?> × <?= (int) ($cropSize ?? 400) ?> px</span>
      <div class="flex space-x-2">
        <button type="button" data-logo-close class="px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200 rounded-lg">Cancel</button>
        <button type="button" id="logoApply" class="px-4 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white text-xs font-bold rounded-lg shadow flex items-center"><?= icon('check', 'w-3.5 h-3.5 mr-1') ?>Crop &amp; Save</button>
      </div>
    </div>
  </div>
</div>
