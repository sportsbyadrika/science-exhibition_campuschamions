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

  // Toast notifications (flash messages): slide in, auto-close, pause on hover
  document.querySelectorAll('#toastStack .toast').forEach(function (toast, i) {
    const total = Number(toast.dataset.timeout) || 5000;
    const bar = toast.querySelector('.toast-bar');
    let remaining = total, started, timer;
    const close = function () {
      clearTimeout(timer);
      toast.classList.add('translate-x-[120%]', 'opacity-0');
      setTimeout(function () { toast.remove(); }, 300);
    };
    const run = function () {
      started = Date.now();
      timer = setTimeout(close, remaining);
      if (bar) { bar.style.transition = 'transform ' + remaining + 'ms linear'; bar.style.transform = 'scaleX(0)'; }
    };
    const pause = function () {
      clearTimeout(timer);
      remaining -= Date.now() - started;
      if (bar) { const s = getComputedStyle(bar).transform; bar.style.transition = 'none'; bar.style.transform = s === 'none' ? '' : s; }
    };
    setTimeout(function () {
      toast.classList.remove('translate-x-[120%]', 'opacity-0');
      run();
    }, 60 + i * 120);
    toast.addEventListener('mouseenter', pause);
    toast.addEventListener('mouseleave', run);
    toast.querySelector('.toast-close').addEventListener('click', close);
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
