(() => {
  'use strict';
  const $ = (selector) => document.querySelector(selector);
  const search = $('#service-search');
  const items = Array.from(document.querySelectorAll('.service-item'));
  let category = 'all';
  function filter() {
    const query = (search?.value || '').trim().toLocaleLowerCase('fa-IR');
    let found = 0;
    items.forEach((item) => {
      const visible = (category === 'all' || item.dataset.category === category) && item.dataset.title.toLocaleLowerCase('fa-IR').includes(query);
      item.hidden = !visible;
      found += Number(visible);
    });
    if ($('#no-results')) $('#no-results').hidden = found !== 0;
  }
  search?.addEventListener('input', filter);
  document.querySelectorAll('[data-category]').forEach((button) => {
    if (!button.classList.contains('filter-tab')) return;
    button.addEventListener('click', () => {
      category = button.dataset.category;
      document.querySelectorAll('.filter-tab').forEach((b) => b.classList.toggle('active', b === button));
      filter();
    });
  });
  const modalEl = $('#inquiry-modal');
  if (!modalEl) return;
  const catalog = JSON.parse($('#catalog-json').textContent);
  const form = $('#inquiry-form');
  const result = $('#inquiry-result');
  let modal;
  function showResult(message, good = false) {
    result.hidden = false;
    result.className = `inquiry-result ${good ? 'is-good' : 'is-error'}`;
    result.textContent = message;
  }
  document.querySelectorAll('[data-open]').forEach((button) => button.addEventListener('click', () => {
    const slug = button.dataset.open;
    const def = catalog[slug];
    if (!def || button.disabled) return;
    form.reset(); result.hidden = true; result.replaceChildren();
    $('#service-slug').value = slug;
    $('#inquiry-title').textContent = def.title;
    $('#inquiry-subtitle').textContent = def.subtitle;
    const fields = $('#inquiry-fields'); fields.replaceChildren();
    Object.entries(def.fields).forEach(([name, spec]) => {
      const group = document.createElement('div'); group.className = 'mb-3';
      const label = document.createElement('label'); label.className = 'form-label'; label.textContent = spec.label; label.htmlFor = `input-${name}`;
      const input = document.createElement('input'); input.className = 'form-control'; input.name = name; input.id = `input-${name}`; input.placeholder = spec.placeholder; input.required = true; input.maxLength = 120; input.autocomplete = 'off';
      if (['national_code', 'mobile', 'card', 'postal_code', 'account'].includes(name)) input.inputMode = 'numeric';
      const note = document.createElement('div'); note.className = 'invalid-feedback';
      group.append(label, input, note); fields.append(group);
    });
    modal ??= new tabler.Modal(modalEl);
    modal.show();
  }));
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    result.hidden = true; result.replaceChildren();
    form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    const submit = $('#submit-inquiry');
    submit.disabled = true; submit.textContent = 'در حال دریافت پاسخ...';
    try {
      const response = await fetch('/api/inquiry', { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
      const data = await response.json();
      if (!response.ok || !data.ok) {
        if (data.errors) Object.entries(data.errors).forEach(([field, message]) => {
          const input = Array.from(form.querySelectorAll('input')).find((el) => el.name === field);
          if (input) { input.classList.add('is-invalid'); input.parentElement.querySelector('.invalid-feedback').textContent = message; }
        });
        showResult(data.message || 'خطایی در ثبت درخواست رخ داد.'); return;
      }
      showResult(data.message, true);
      const pre = document.createElement('pre'); pre.className = 'result-json'; pre.dir = 'ltr';
      pre.textContent = JSON.stringify(data.result, null, 2);
      result.append(pre);
    } catch (_) { showResult('ارتباط با سرور برقرار نشد. لطفاً دوباره تلاش کنید.'); }
    finally { submit.disabled = false; submit.textContent = 'دریافت نتیجه استعلام'; }
  });
})();
