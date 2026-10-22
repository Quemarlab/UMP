/* Renders the two-factor setup QR code (qrcode-generator, MIT) without any inline script. */
(function () {
  'use strict';
  const el = document.getElementById('qr');
  if (!el || !window.qrcode) return;
  const qr = qrcode(0, 'M');
  qr.addData(el.dataset.uri);
  qr.make();
  el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
  const svg = el.querySelector('svg');
  if (svg) { svg.setAttribute('width', '184'); svg.setAttribute('height', '184'); }
})();