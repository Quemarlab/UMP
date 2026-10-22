/* UMP charts: small dependency-free SVG bar and line charts with hover tooltips. */
(function () {
  'use strict';

  const dataEl = document.getElementById('chartData');
  if (!dataEl) return;
  let rows = JSON.parse(dataEl.textContent);
  const NS = 'http://www.w3.org/2000/svg';
  const css = (n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim();
  const fmt = (n) => Math.round(n * 100) / 100 === Math.round(n) ? Math.round(n).toLocaleString() : (Math.round(n * 100) / 100).toLocaleString();
  const dayLabel = (d) => new Date(d + 'T00:00:00').toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
  const el = (name, attrs, parent) => {
    const n = document.createElementNS(NS, name);
    for (const k in attrs) n.setAttribute(k, attrs[k]);
    parent && parent.appendChild(n);
    return n;
  };
  const niceMax = (v) => {
    if (v <= 0) return 1;
    const p = Math.pow(10, Math.floor(Math.log10(v)));
    const f = v / p;
    return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10) * p;
  };

  function draw(host) {
    host.innerHTML = '';
    const kind = host.dataset.chart;
    const unit = host.dataset.unit || '';
    const W = Math.max(280, host.clientWidth), H = kind === 'bars' ? 260 : 220;
    const m = { t: 10, r: 8, b: 26, l: 46 };
    const iw = W - m.l - m.r, ih = H - m.t - m.b;
    const svg = el('svg', { viewBox: `0 0 ${W} ${H}`, width: W, height: H, role: 'img', 'aria-label': host.closest('.card').querySelector('.card-header').textContent.trim() }, host);

    const vals = kind === 'bars'
      ? rows.flatMap((r) => [r.received, r.issued])
      : rows.map((r) => r.closing);
    const max = niceMax(Math.max(...vals));
    const y = (v) => m.t + ih - (v / max) * ih;
    const x = (i) => m.l + (iw / rows.length) * (i + 0.5);

    // recessive grid + y labels
    for (let i = 0; i <= 4; i++) {
      const v = (max / 4) * i, yy = y(v);
      el('line', { x1: m.l, x2: W - m.r, y1: yy, y2: yy, stroke: css('--line'), 'stroke-width': 1 }, svg);
      const t = el('text', { x: m.l - 6, y: yy + 4, 'text-anchor': 'end', 'font-size': 11, fill: css('--ink-muted') }, svg);
      t.textContent = v >= 1000 ? (v / 1000) + 'k' : v;
    }
    const step = Math.ceil(rows.length / (W < 480 ? 5 : 8));
    rows.forEach((r, i) => {
      const last = i === rows.length - 1;
      // every Nth day, plus the final day; skip a regular label that would collide with the final one
      if (!last && (i % step !== 0 || rows.length - 1 - i < step)) return;
      const t = el('text', { x: last ? W - m.r : x(i), y: H - 8, 'text-anchor': last ? 'end' : 'middle', 'font-size': 11, fill: css('--ink-muted') }, svg);
      t.textContent = dayLabel(r.date);
    });

    const tip = document.createElement('div');
    tip.className = 'chart-tip';
    host.appendChild(tip);
    const showTip = (i, ev) => {
      const r = rows[i];
      tip.innerHTML = `<strong>${dayLabel(r.date)}</strong>` + (kind === 'bars'
        ? `<div><i class="sw sw-recv"></i>Received ${fmt(r.received)} ${unit}</div><div><i class="sw sw-iss"></i>Issued ${fmt(r.issued)} ${unit}</div>` + (r.recorded ? '' : '<div class="text-muted">Not recorded</div>')
        : `<div>Closing ${fmt(r.closing)} ${unit}</div>`);
      tip.style.opacity = 1;
      const box = host.getBoundingClientRect();
      const left = Math.min(Math.max(8, x(i) - tip.offsetWidth / 2), W - tip.offsetWidth - 8);
      tip.style.left = left + 'px';
      tip.style.top = Math.max(0, (ev && ev.clientY ? ev.clientY - box.top : 40) - tip.offsetHeight - 14) + 'px';
    };

    if (kind === 'bars') {
      const slot = iw / rows.length, bw = Math.max(2, Math.min(10, slot * 0.36));
      rows.forEach((r, i) => {
        [['received', css('--c-recv'), -bw - 1], ['issued', css('--c-iss'), 1]].forEach(([k, color, off]) => {
          const h = Math.max(r[k] > 0 ? 2 : 0, ih - (y(r[k]) - m.t));
          if (h > 0) el('rect', { x: x(i) + off, y: m.t + ih - h, width: bw, height: h, rx: 2, fill: color }, svg);
        });
      });
    } else {
      const pts = rows.map((r, i) => [x(i), y(r.closing)]);
      const line = pts.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1)).join(' ');
      el('path', { d: line + ` L${pts[pts.length - 1][0]} ${m.t + ih} L${pts[0][0]} ${m.t + ih} Z`, fill: css('--c-stock'), opacity: 0.12 }, svg);
      el('path', { d: line, fill: 'none', stroke: css('--c-stock'), 'stroke-width': 2, 'stroke-linejoin': 'round' }, svg);
      const last = pts[pts.length - 1];
      el('circle', { cx: last[0], cy: last[1], r: 4, fill: css('--c-stock'), stroke: css('--card-bg'), 'stroke-width': 2 }, svg);
    }

    // generous hit areas: one full-height column per day
    const guide = el('line', { y1: m.t, y2: m.t + ih, stroke: css('--ink-muted'), 'stroke-width': 1, 'stroke-dasharray': '3 3', opacity: 0 }, svg);
    rows.forEach((r, i) => {
      const slot = iw / rows.length;
      const hit = el('rect', { x: m.l + slot * i, y: m.t, width: slot, height: ih, fill: 'transparent' }, svg);
      hit.addEventListener('mousemove', (ev) => { guide.setAttribute('x1', x(i)); guide.setAttribute('x2', x(i)); guide.setAttribute('opacity', 1); showTip(i, ev); });
      hit.addEventListener('mouseleave', () => { guide.setAttribute('opacity', 0); tip.style.opacity = 0; });
    });

    if (!rows.some((r) => r.recorded)) {
      const t = el('text', { x: W / 2, y: H / 2, 'text-anchor': 'middle', fill: css('--ink-muted'), 'font-size': 13 }, svg);
      t.textContent = 'No records in this period yet';
    }
  }

  const all = () => document.querySelectorAll('.chart[data-chart]');
  const render = () => all().forEach(draw);
  render();
  let t;
  window.addEventListener('resize', () => { clearTimeout(t); t = setTimeout(render, 120); });
  window.umpCharts = { set(next) { rows = next; render(); } };
  new MutationObserver(render).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
})();
