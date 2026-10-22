/* Daily record form: live totals, negative-stock guard, switch day. */
(function () {
  'use strict';
  const form = document.getElementById('recordForm');
  if (!form) return;

  const opening = parseFloat(form.dataset.opening) || 0;
  const num = (id) => Math.max(0, parseFloat((document.getElementById(id).value || '0').replace(',', '.')) || 0);
  const fmt = (n) => (Math.round(n * 100) / 100).toLocaleString(undefined, { maximumFractionDigits: 2 });
  const total = document.getElementById('totalReceived');
  const closing = document.getElementById('closingStock');
  const box = document.getElementById('closingBox');
  const warn = document.getElementById('negWarn');
  const save = document.getElementById('saveBtn');

  function recalc() {
    const received = num('received_day') + num('received_night');
    const close = opening + received - num('issued');
    total.textContent = fmt(received);
    closing.textContent = fmt(close);
    const neg = close < -0.004;
    box.classList.toggle('neg', neg);
    warn.classList.toggle('d-none', !neg);
    if (save) save.disabled = neg;
  }
  form.querySelectorAll('.calc').forEach((i) => i.addEventListener('input', recalc));
  recalc();

  // Picking another date loads that day's record (or an empty form).
  document.getElementById('record_date').addEventListener('change', (e) => {
    if (e.target.value) { dirty = false; location.href = location.pathname + '?date=' + encodeURIComponent(e.target.value); }
  });
  // Leaving with unsaved changes asks first; Ctrl/Cmd+S saves.
  let dirty = false;
  form.addEventListener('input', () => { dirty = true; });
  form.addEventListener('submit', () => { dirty = false; });
  window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      if (save && !save.disabled) form.requestSubmit(save);
    }
  });})();
