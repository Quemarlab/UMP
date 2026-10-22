/* Command palette (Ctrl+K or "/"): jump to any page, open a date, or find a record by GRV / GIV / notes. */
(function () {
  'use strict';
  const dataEl = document.getElementById('paletteData');
  const root = document.getElementById('palette');
  if (!dataEl || !root) return;

  const pages = JSON.parse(dataEl.textContent);
  const input = root.querySelector('input');
  const list = root.querySelector('.pal-list');
  let items = [], active = 0, timer = null, seq = 0;

  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const open = () => { root.hidden = false; document.body.classList.add('pal-open'); input.value = ''; build(''); input.focus(); };
  const close = () => { root.hidden = true; document.body.classList.remove('pal-open'); };

  function render() {
    if (!items.length) { list.innerHTML = '<div class="pal-empty">No matches. Try a date (2025-02-27, 27/02/2025, today) or a GRV / GIV number.</div>'; return; }
    let html = '', group = '';
    items.forEach((it, i) => {
      if (it.group !== group) { group = it.group; html += '<div class="pal-group">' + esc(group) + '</div>'; }
      html += '<a class="pal-item' + (i === active ? ' active' : '') + '" data-i="' + i + '" href="' + esc(it.url || '#') + '"><i class="bi ' + esc(it.icon || 'bi-arrow-right-short') + '"></i><span><strong>' + esc(it.label) + '</strong>' + (it.sub ? '<small>' + esc(it.sub) + '</small>' : '') + '</span></a>';
    });
    list.innerHTML = html;
    const act = list.querySelector('.active');
    act && act.scrollIntoView({ block: 'nearest' });
  }

  function build(q, remote) {
    const needle = q.toLowerCase();
    const local = pages.filter((p) => !needle || p.label.toLowerCase().includes(needle)).map((p) => ({ ...p, group: p.group || 'Go to' }));
    items = [];
    if (remote && remote.date) items.push({ group: 'Date', label: remote.date.label, url: remote.date.url, icon: 'bi-calendar-event' });
    if (remote) remote.records.forEach((r) => items.push({ group: 'Records', label: r.label, sub: r.sub, url: r.url, icon: 'bi-journal-text' }));
    items = items.concat(local);
    active = 0;
    render();
  }

  input.addEventListener('input', () => {
    const q = input.value.trim();
    clearTimeout(timer);
    build(q);
    if (q.length < 2) return;
    timer = setTimeout(async () => {
      const mine = ++seq;
      try {
        const res = await fetch(root.dataset.search + '?q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } });
        if (!res.ok || mine !== seq) return;
        build(q, await res.json());
      } catch (e) { /* offline: local results only */ }
    }, 180);
  });

  const go = (it) => {
    if (!it) return;
    close();
    if (it.action === 'theme') document.getElementById('themeToggle')?.click();
    else if (it.url) location.href = it.url;
  };

  input.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(items.length - 1, active + 1); render(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(0, active - 1); render(); }
    else if (e.key === 'Enter') { e.preventDefault(); go(items[active]); }
  });
  list.addEventListener('click', (e) => {
    const a = e.target.closest('.pal-item');
    if (!a) return;
    e.preventDefault();
    go(items[+a.dataset.i]);
  });
  list.addEventListener('mousemove', (e) => {
    const a = e.target.closest('.pal-item');
    if (a && +a.dataset.i !== active) { active = +a.dataset.i; list.querySelectorAll('.pal-item').forEach((n, i) => n.classList.toggle('active', i === active)); }
  });
  root.addEventListener('mousedown', (e) => { if (e.target === root) close(); });

  document.addEventListener('keydown', (e) => {
    const typing = /^(INPUT|TEXTAREA|SELECT)$/.test((document.activeElement || {}).tagName) || (document.activeElement || {}).isContentEditable;
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); root.hidden ? open() : close(); }
    else if (e.key === '/' && !typing && root.hidden) { e.preventDefault(); open(); }
    else if (e.key === 'Escape' && !root.hidden) { close(); }
  });
  document.querySelectorAll('[data-palette-open]').forEach((b) => b.addEventListener('click', open));
})();