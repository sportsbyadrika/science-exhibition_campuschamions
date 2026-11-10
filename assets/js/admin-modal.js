// Admin modal: loads view / edit content for team entries and fund transfers.
// <button data-modal-url="/admin/team-entry?id=1&mode=view&return=...">
(function () {
  const modal = document.getElementById('adminModal');
  if (!modal) return;
  const box = document.getElementById('adminModalBox');
  const allowed = /\/admin\/(team-entry|fund-transfer)$/;

  function close() {
    modal.classList.add('hidden');
    box.innerHTML = '';
    document.body.classList.remove('overflow-hidden');
  }

  async function open(href) {
    const target = new URL(href, window.location.href);
    if (target.origin !== window.location.origin || !allowed.test(target.pathname)) return;
    box.innerHTML = '<div class="p-10 text-center text-xs text-slate-400">Loading…</div>';
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    try {
      const res = await fetch(target.toString(), { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
      if (!res.ok || res.redirected) throw new Error('HTTP ' + res.status);
      box.innerHTML = await res.text();
      if (window.lucide) window.lucide.createIcons();
      box.querySelectorAll('form.team-edit-form').forEach(initTeamForm);
    } catch (e) {
      box.innerHTML = '<div class="p-8 text-center text-sm text-rose-600">Could not load the details. Please refresh the page and try again.</div>'
        + '<div class="px-6 pb-6 text-center"><button type="button" data-modal-close class="btn-light btn-sm">Close</button></div>';
    }
  }

  document.addEventListener('click', function (e) {
    const opener = e.target.closest('[data-modal-url]');
    if (opener) { e.preventDefault(); open(opener.dataset.modalUrl); return; }
    if (e.target.closest('[data-modal-close]') || e.target === modal) close();
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.classList.contains('hidden')) close(); });

  // Re-open a modal after a failed save (?modal=...), then tidy the address bar
  const params = new URLSearchParams(window.location.search);
  if (params.get('modal')) {
    open(params.get('modal'));
    params.delete('modal');
    const q = params.toString();
    history.replaceState(null, '', window.location.pathname + (q ? '?' + q : '') + window.location.hash);
  }

  // Team edit form: categories follow the event, class lists follow the category
  function initTeamForm(form) {
    const events = JSON.parse(form.dataset.events || '[]');
    const cats = JSON.parse(form.dataset.categories || '[]');
    const evSel = form.querySelector('.js-event');
    const catSel = form.querySelector('.js-category');
    const findEv = () => events.find((x) => x.id === Number(evSel.value));
    const findCat = () => cats.find((x) => x.id === Number(catSel.value));

    function renderCats() {
      const ev = findEv();
      const keep = Number(catSel.value || catSel.dataset.selected);
      catSel.innerHTML = '';
      cats.filter((c) => !ev || ev.categories.includes(c.id)).forEach((c) => {
        const o = new Option(c.name, c.id);
        if (c.id === keep) o.selected = true;
        catSel.add(o);
      });
      form.querySelectorAll('.js-slot').forEach((slot) => {
        slot.classList.toggle('opacity-50', ev && Number(slot.dataset.slot) > ev.max);
      });
      renderClasses();
    }
    function renderClasses() {
      const cat = findCat();
      form.querySelectorAll('.js-class').forEach((sel) => {
        const keep = sel.value || sel.dataset.selected;
        sel.innerHTML = '<option value="">—</option>';
        (cat ? cat.classes : []).forEach((c) => {
          const o = new Option(c, c);
          if (c === keep) o.selected = true;
          sel.add(o);
        });
      });
    }
    evSel.addEventListener('change', renderCats);
    catSel.addEventListener('change', renderClasses);
    renderCats();
  }
})();
