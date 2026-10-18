// Renders UPI QR codes from a upi://pay link (data-uri) and keeps the amount in sync
// with the Fund Transfer form's amount field.
(function () {
  if (typeof qrcode !== 'function') return;

  function render(el, uri) {
    const qr = qrcode(0, 'M');
    qr.addData(uri);
    qr.make();
    el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
  }

  ['upiQr', 'settingsQr'].forEach(function (id) {
    const el = document.getElementById(id);
    if (el && el.dataset.uri) render(el, el.dataset.uri);
  });

  const box = document.getElementById('upiQr');
  const amount = document.getElementById('payAmount');
  if (!box || !amount) return;
  const link = document.getElementById('upiPayLink');
  const text = document.getElementById('upiAmountText');

  amount.addEventListener('input', function () {
    const value = parseFloat(amount.value);
    const url = new URL(box.dataset.uri.replace('upi://', 'https://upi.local/'));
    if (value > 0) url.searchParams.set('am', value.toFixed(2)); else url.searchParams.delete('am');
    const uri = 'upi://pay?' + url.searchParams.toString().replace(/\+/g, '%20').replace(/%40/g, '@');
    render(box, uri);
    if (link) link.href = uri;
    if (text) text.textContent = '₹ ' + (value > 0 ? value : 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  });
})();
