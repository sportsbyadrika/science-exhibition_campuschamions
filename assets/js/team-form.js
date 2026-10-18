// Team entry form: event/category dependent fields, member slots and photo cropping.
(function () {
  const cfg = window.TEAM_FORM;
  const form = document.getElementById('teamForm');
  if (!cfg || !form) return;

  const eventSel = document.getElementById('event_id');
  const catSel = document.getElementById('category_id');
  const slots = Array.from(document.querySelectorAll('.member-slot'));
  const inr = (n) => '₹ ' + Number(n).toLocaleString('en-IN');
  const findEvent = () => cfg.events.find((e) => e.id === Number(eventSel.value));
  const findCat = () => cfg.categories.find((c) => c.id === Number(catSel.value));

  function renderCategories() {
    const ev = findEvent();
    const current = Number(catSel.value) || cfg.selected.category;
    catSel.innerHTML = '<option value="">— Select Category —</option>';
    cfg.categories.filter((c) => ev && ev.categories.includes(c.id)).forEach((c) => {
      const o = new Option(c.name + (c.description ? ' (' + c.description + ')' : ''), c.id);
      if (c.id === current) o.selected = true;
      catSel.add(o);
    });
    document.getElementById('eventFeeText').textContent = inr(ev ? ev.fee : 0);
    document.getElementById('eventRuleText').textContent = ev
      ? (ev.min === ev.max ? 'Each team consists of exactly ' + ev.min + ' student(s).' : 'Each team consists of ' + ev.min + ' to ' + ev.max + ' students.') + ' Entry fee is ' + inr(ev.fee) + ' per team.'
      : 'Select an event to see team size and entry fee.';
    document.getElementById('eventHelper').textContent = ev && ev.description ? ev.description : 'Select demonstration model type';
    renderSlots();
    renderClasses();
  }

  function renderSlots() {
    const ev = findEvent();
    const max = ev ? ev.max : 2;
    const min = ev ? ev.min : 2;
    slots.forEach((slot) => {
      const n = Number(slot.dataset.slot);
      slot.classList.toggle('hidden', n > max);
      slot.querySelector('.slot-required').textContent = n <= min ? 'Compulsory' : 'Optional';
      slot.querySelector('.photo-star').classList.toggle('hidden', !(ev && ev.photo));
    });
  }

  function renderClasses() {
    const cat = findCat();
    document.getElementById('categoryHelper').textContent = cat ? cat.name + ': ' + (cat.description || cat.classes.join(', ')) : '';
    slots.forEach((slot) => {
      const sel = slot.querySelector('.member-class');
      const keep = sel.value || sel.dataset.selected;
      sel.innerHTML = '<option value="">— Class —</option>';
      (cat ? cat.classes : []).forEach((c) => {
        const o = new Option(c, c);
        if (c === keep) o.selected = true;
        sel.add(o);
      });
    });
  }

  // Suggested team name: "<Institution> <Category> - Team <n>" (only replaces an empty or previously suggested value)
  const nameInput = document.getElementById('project_title');
  let lastSuggestion = '';
  function suggestName() {
    const cat = findCat();
    if (!cat || !nameInput) return;
    const n = ((cfg.categoryTeamCounts || {})[cat.id] || 0) + 1;
    const suggestion = cfg.institution + ' ' + cat.name + ' - Team ' + n;
    const current = nameInput.value.trim();
    if (current === '' || current === lastSuggestion) nameInput.value = suggestion;
    lastSuggestion = suggestion;
  }

  eventSel.addEventListener('change', () => { renderCategories(); suggestName(); });
  catSel.addEventListener('change', () => { renderClasses(); suggestName(); });
  renderCategories();
  if (nameInput && nameInput.value.trim() === '') suggestName();

  // ---------------- Photo cropper ----------------
  const modal = document.getElementById('cropperModal');
  const image = document.getElementById('cropperImageTarget');
  let cropper = null;
  let activeSlot = null;

  function closeModal() {
    modal.classList.add('hidden');
    if (cropper) { cropper.destroy(); cropper = null; }
  }

  slots.forEach((slot) => {
    const file = slot.querySelector('.photo-file');
    slot.querySelector('.photo-btn').addEventListener('click', () => file.click());
    file.addEventListener('change', (e) => {
      const f = e.target.files[0];
      if (!f) return;
      if (!/^image\//.test(f.type)) { alert('Please choose an image file.'); return; }
      activeSlot = slot;
      const reader = new FileReader();
      reader.onload = (ev) => {
        image.src = ev.target.result;
        modal.classList.remove('hidden');
        if (cropper) cropper.destroy();
        cropper = new Cropper(image, { aspectRatio: 3.5 / 4.5, viewMode: 1, autoCropArea: 0.85 });
      };
      reader.readAsDataURL(f);
      file.value = '';
    });
  });

  modal.querySelectorAll('[data-crop-close]').forEach((b) => b.addEventListener('click', closeModal));
  document.getElementById('applyCropBtn').addEventListener('click', () => {
    if (!cropper || !activeSlot) return;
    const dataUrl = cropper.getCroppedCanvas({ width: 350, height: 450, fillColor: '#fff' }).toDataURL('image/jpeg', 0.85);
    const prev = activeSlot.querySelector('.crop-preview');
    prev.src = dataUrl;
    prev.classList.remove('hidden');
    activeSlot.querySelector('.crop-placeholder').classList.add('hidden');
    activeSlot.querySelector('.photo-data').value = dataUrl;
    closeModal();
  });

  // ---------------- Client side validation ----------------
  form.addEventListener('submit', (e) => {
    const ev = findEvent();
    const errors = [];
    if (!ev) errors.push('Please select an event.');
    if (!catSel.value) errors.push('Please select a category.');
    if (!document.getElementById('project_title').value.trim()) errors.push('Please enter the team name.');
    if (ev) {
      let filled = 0;
      slots.forEach((slot) => {
        const n = Number(slot.dataset.slot);
        if (n > ev.max) { slot.querySelector('.member-name').value = ''; return; }
        const name = slot.querySelector('.member-name').value.trim();
        if (!name) { if (n <= ev.min) errors.push('Enter the name of student ' + n + '.'); return; }
        filled++;
        if (!slot.querySelector('.member-class').value) errors.push('Select the class of student ' + n + '.');
        if (ev.photo && !slot.querySelector('.photo-data').value && !slot.querySelector('.photo-existing').value) {
          errors.push('Upload and crop the photo of student ' + n + '.');
        }
      });
      if (filled < ev.min) errors.push('At least ' + ev.min + ' student(s) are required.');
    }
    if (errors.length) { e.preventDefault(); alert(errors.join('\n')); }
  });
})();
