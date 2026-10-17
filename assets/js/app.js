// Shared front-end behaviour for the portal.
(function () {
  if (window.lucide) window.lucide.createIcons();

  // <form data-confirm="Are you sure?"> asks before submitting
  // <form data-prompt="Remarks?" data-prompt-field="remarks"> collects a remark into a hidden field
  document.addEventListener('submit', function (e) {
    const form = e.target;
    if (form.dataset.prompt) {
      const field = form.querySelector('[name="' + (form.dataset.promptField || 'remarks') + '"]');
      const value = window.prompt(form.dataset.prompt, field ? field.value : '');
      if (value === null) { e.preventDefault(); return; }
      if (field) field.value = value;
    }
    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
      e.preventDefault();
      return;
    }
  }, true);

  // prevent double submits (bubble phase, after page specific validation ran)
  document.addEventListener('submit', function (e) {
    if (e.defaultPrevented) return;
    e.target.querySelectorAll('button[type="submit"]').forEach(function (b) {
      setTimeout(function () { b.disabled = true; }, 0);
    });
  });

  // Refresh captcha images: <button data-captcha-refresh="#imgId">
  document.querySelectorAll('[data-captcha-refresh]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const img = document.querySelector(btn.dataset.captchaRefresh);
      if (!img) return;
      const src = img.getAttribute('src').split('&r=')[0];
      img.setAttribute('src', src + '&r=' + Date.now());
      const input = document.querySelector(btn.dataset.captchaInput || '');
      if (input) { input.value = ''; input.focus(); }
    });
  });
})();
