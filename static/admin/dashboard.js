/* Dashboard interactions without page reloads: calendar month switching and the chart range. */
(function () {
  'use strict';

  // ---- Calendar: click the arrows (or "today") to swap the month in place.
  const card = document.getElementById('calCard');
  if (card) {
    let busy = false;
    card.addEventListener('click', async (ev) => {
      const a = ev.target.closest('a[data-cal]');
      if (!a || busy || ev.metaKey || ev.ctrlKey || ev.shiftKey) return;
      ev.preventDefault();
      busy = true;
      card.style.opacity = '.55';
      try {
        const res = await fetch(location.pathname + '?partial=cal&cal=' + encodeURIComponent(a.dataset.cal), { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } });
        if (!res.ok || res.redirected) throw new Error('bad response');
        card.innerHTML = await res.text();
      } catch (e) {
        location.href = a.href; // fall back to a normal page load (e.g. the session expired)
      }
      card.style.opacity = '';
      busy = false;
    });
  }

  // ---- Chart range: 7 / 30 / 90 days. Remembers the choice in this browser.
  const buttons = document.querySelectorAll('[data-range]');
  if (buttons.length && window.umpCharts) {
    const label = (n) => 'last ' + n + ' days';
    const load = async (n) => {
      buttons.forEach((b) => b.classList.toggle('active', +b.dataset.range === n));
      try {
        const res = await fetch(location.pathname + '?series=' + n, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } });
        if (!res.ok) throw new Error('bad response');
        window.umpCharts.set(await res.json());
        document.querySelectorAll('.range-label').forEach((el) => { el.textContent = label(n); });
        try { localStorage.setItem('ump-range', String(n)); } catch (e) { /* private mode */ }
      } catch (e) { /* keep the current chart */ }
    };
    buttons.forEach((b) => b.addEventListener('click', () => load(+b.dataset.range)));
    let saved = 30;
    try { saved = parseInt(localStorage.getItem('ump-range'), 10) || 30; } catch (e) { /* ignore */ }
    if (saved !== 30 && [7, 90].includes(saved)) load(saved);
  }
})();