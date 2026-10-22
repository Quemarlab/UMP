/* UMP admin behaviour */
(function () {
  'use strict';

  // Sidebar (mobile)
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('backdrop');
  const toggle = document.getElementById('sidebarToggle');
  const setOpen = (open) => {
    sidebar && sidebar.classList.toggle('open', open);
    backdrop && backdrop.classList.toggle('show', open);
  };
  toggle && toggle.addEventListener('click', () => setOpen(!sidebar.classList.contains('open')));
  backdrop && backdrop.addEventListener('click', () => setOpen(false));

  // Dark mode
  const themeBtn = document.getElementById('themeToggle');
  const icon = themeBtn && themeBtn.querySelector('i');
  const paintIcon = () => {
    if (icon) icon.className = 'bi ' + (document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'bi-sun' : 'bi-moon-stars');
  };
  paintIcon();
  themeBtn && themeBtn.addEventListener('click', () => {
    const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-bs-theme', next);
    try { localStorage.setItem('ump-theme', next); } catch (e) { /* private mode */ }
    paintIcon();
  });

  // Confirmation modal for destructive forms: <form data-confirm="Delete this?">
  const modalEl = document.getElementById('confirmModal');
  if (modalEl && window.bootstrap) {
    const modal = new bootstrap.Modal(modalEl);
    let pending = null;
    document.addEventListener('submit', (ev) => {
      const form = ev.target;
      if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed) return;
      ev.preventDefault();
      pending = form;
      document.getElementById('confirmTitle').textContent = form.dataset.confirm;
      document.getElementById('confirmText').textContent = form.dataset.confirmText || 'This cannot be undone.';
      modal.show();
    });
    document.getElementById('confirmOk').addEventListener('click', () => {
      if (!pending) return;
      const form = pending;
      pending = null;
      modal.hide(); // close the dialog right away: background (AJAX) actions never reload the page, so it would stay open
      form.dataset.confirmed = '1';
      if (form.requestSubmit) form.requestSubmit(); else form.submit();
    });
  }


  // Image input preview
  document.querySelectorAll('input[type=file][data-preview]').forEach((input) => {
    const img = document.getElementById(input.dataset.preview);
    input.addEventListener('change', () => {
      const f = input.files && input.files[0];
      if (f && img) { img.src = URL.createObjectURL(f); img.style.display = 'block'; }
    });
  });

  // Auto-generate slug hint & character counters
  document.querySelectorAll('[data-maxlen-counter]').forEach((el) => {
    const out = document.querySelector(el.dataset.maxlenCounter);
    const upd = () => { if (out) out.textContent = el.value.length + ' / ' + el.maxLength; };
    el.addEventListener('input', upd); upd();
  });

  // Live clock in the sidebar
  const clock = document.getElementById('clock');
  if (clock) {
    const tick = () => { clock.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }); };
    tick(); setInterval(tick, 1000);
  }
  // CSP-friendly helpers: <button data-print>, <select data-autosubmit>
  document.addEventListener('click', (e) => { if (e.target.closest('[data-print]')) window.print(); });
  document.addEventListener('change', (e) => { if (e.target.matches('[data-autosubmit]') && e.target.form) e.target.form.submit(); });})();
