/**
 * billing.js — Tariff Calculator, Code 39 Barcode Generator & Thermal Slip Printer
 */
const ParkVueBilling = (() => {
  const RATES = { '2-wheeler': 20, sedan: 40, suv: 50, ev: 60, valet: 80, standard: 40 };

  // Standard Code 39 binary patterns (0 = Narrow, 1 = Wide)
  const CODE39 = {
    '0': '000110100', '1': '100100001', '2': '001100001', '3': '101100000',
    '4': '000110001', '5': '100110000', '6': '001110000', '7': '000100101',
    '8': '100100100', '9': '001100100', 'A': '100001001', 'B': '001001001',
    'C': '101001000', 'D': '000011001', 'E': '100011000', 'F': '001011000',
    'G': '000001101', 'H': '100001100', 'I': '001001100', 'J': '000011100',
    'K': '100000011', 'L': '001000011', 'M': '101000010', 'N': '000010011',
    'O': '100010010', 'P': '001010010', 'Q': '000000111', 'R': '100000110',
    'S': '001000110', 'T': '000010110', 'U': '110000001', 'V': '011000001',
    'W': '111000000', 'X': '010010001', 'Y': '110010000', 'Z': '011010000',
    '-': '010000101', '*': '010010100', ' ': '011000100'
  };

  // Generate 100% scannable ISO/IEC 16388 Code 39 SVG barcode
  function generateBarcodeSVG(text, barHeight = 44) {
    const raw = (text || 'TKT-0000').toUpperCase().replace(/[^A-Z0-9\-]/g, '');
    const clean = '*' + raw + '*';
    const N = 2, W = 5, gap = 2, quiet = 14;

    let totalWidth = quiet * 2;
    for (const ch of clean) {
      const pat = CODE39[ch];
      if (!pat) continue;
      for (let i = 0; i < 9; i++) totalWidth += (pat[i] === '1' ? W : N);
      totalWidth += gap;
    }

    let x = quiet, rects = '';
    for (const ch of clean) {
      const pat = CODE39[ch];
      if (!pat) continue;
      for (let i = 0; i < 9; i++) {
        const w = pat[i] === '1' ? W : N;
        if (i % 2 === 0) { // Alternating: 0=bar, 1=space, 2=bar...
          rects += `<rect x="${x}" y="6" width="${w}" height="${barHeight}" fill="#000"/>`;
        }
        x += w;
      }
      x += gap;
    }

    return `<svg viewBox="0 0 ${totalWidth} ${barHeight + 24}" xmlns="http://www.w3.org/2000/svg" style="background:#fff;border-radius:6px;padding:6px 8px;max-width:100%;height:auto;display:block;margin:auto;">
      ${rects}
      <text x="${totalWidth / 2}" y="${barHeight + 20}" text-anchor="middle" font-family="monospace" font-size="12" font-weight="700" fill="#000">${raw}</text>
    </svg>`;
  }

  function renderBarcode(container, text) {
    if (container) container.innerHTML = generateBarcodeSVG(text);
  }

  function computeFee(category, checkinISO) {
    const hours = Math.max(1, Math.ceil((Date.now() - new Date(checkinISO).getTime()) / 3600000));
    const cat = (category || 'sedan').toLowerCase();
    const rate = RATES[cat] || 40;
    return { hours, rate, total: hours * rate };
  }

  function calcChange(tendered, totalDue) {
    const paid = parseFloat(tendered) || 0;
    const due = parseFloat(totalDue) || 0;
    const diff = +(paid - due).toFixed(2);
    return { paid, due, change: Math.max(0, diff), deficit: diff < 0 ? Math.abs(diff) : 0, valid: diff >= 0 };
  }

  function printReceipt(data) {
    const area = document.getElementById('receipt-printable');
    if (!area) return;
    area.innerHTML = `
      <div style="font-family:monospace;max-width:280px;padding:14px;border:1px solid #000;margin:auto;text-align:center;">
        <h3 style="margin-bottom:4px">PARKVUE PLAZA</h3>
        <p style="font-size:11px">Gate Terminal #02</p>
        <hr style="margin:8px 0">
        <div style="text-align:left;font-size:12px;line-height:1.6">
          <p><strong>TICKET:</strong> ${data.ticket || '—'}</p>
          <p><strong>PLATE:</strong> ${data.plate || '—'}</p>
          <p><strong>SLOT:</strong> ${data.bay || '—'}</p>
          <p><strong>ENTRY:</strong> ${data.checkin ? new Date(data.checkin).toLocaleTimeString() : '—'}</p>
          ${data.checkout ? `<p><strong>EXIT:</strong> ${new Date(data.checkout).toLocaleTimeString()}</p>` : ''}
          ${data.total_due ? `<p><strong>TOTAL:</strong> ₹${data.total_due}</p>` : ''}
          ${data.tender ? `<p><strong>PAID VIA:</strong> ${data.tender}</p>` : ''}
        </div>
        <hr style="margin:8px 0">
        <div style="margin:8px auto">${generateBarcodeSVG(data.ticket || 'TKT-0000', 36)}</div>
        <p style="font-size:11px;margin-top:6px">Scan barcode at gate barrier to exit</p>
      </div>`;
    window.print();
  }

  return { computeFee, calcChange, printReceipt, generateBarcodeSVG, renderBarcode };
})();
