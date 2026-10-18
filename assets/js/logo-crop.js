// Institution logo: choose image -> square crop (Cropper.js) -> submit as PNG data URL.
(function () {
  const btn = document.getElementById('logoBtn');
  if (!btn) return;
  const file = document.getElementById('logoFile');
  const modal = document.getElementById('logoCropModal');
  const target = document.getElementById('logoCropTarget');
  let cropper = null;

  const close = () => { modal.classList.add('hidden'); if (cropper) { cropper.destroy(); cropper = null; } };
  btn.addEventListener('click', () => file.click());
  file.addEventListener('change', (e) => {
    const f = e.target.files[0];
    file.value = '';
    if (!f) return;
    if (!/^image\/(png|jpeg|webp)$/.test(f.type)) { alert('Please choose a PNG, JPG or WEBP image.'); return; }
    const reader = new FileReader();
    reader.onload = (ev) => {
      target.src = ev.target.result;
      modal.classList.remove('hidden');
      if (cropper) cropper.destroy();
      cropper = new Cropper(target, { aspectRatio: 1, viewMode: 1, autoCropArea: 0.9, background: false });
    };
    reader.readAsDataURL(f);
  });
  modal.querySelectorAll('[data-logo-close]').forEach((b) => b.addEventListener('click', close));
  document.getElementById('logoApply').addEventListener('click', () => {
    if (!cropper) return;
    const dataUrl = cropper.getCroppedCanvas({ width: 400, height: 400, imageSmoothingQuality: 'high' }).toDataURL('image/png');
    document.getElementById('logoData').value = dataUrl;
    const prev = document.getElementById('logoPreview');
    prev.src = dataUrl; prev.classList.remove('hidden');
    document.getElementById('logoPlaceholder').classList.add('hidden');
    close();
    document.getElementById('logoForm').submit();
  });
})();
