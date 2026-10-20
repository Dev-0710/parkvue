/**
 * terminal.js — Core Parking Terminal Logic
 * Handles Check-In, Checkout, Live Slots Grid, and Activity Ledger.
 */

const Terminal = {
  entryCategory: 'sedan',
  exitTender: 'cash',
  exitData: null,
  activeFilter: 'all',
  lastState: null,
  pollTimer: null
};

// ── DOM Initialization ──
document.addEventListener('DOMContentLoaded', () => {
  initClock();
  initEntryWorkflow();
  initExitWorkflow();
  initFilters();
  initModals();
  startPolling();
});

// ── 1. Live Digital Clock ──
function initClock() {
  const el = document.getElementById('utc-clock');
  function tick() {
    if (el) el.textContent = new Date().toLocaleTimeString();
  }
  tick();
  setInterval(tick, 1000);
}

// ── 2. Data Polling & UI Rendering ──
function startPolling() {
  fetchState();
  Terminal.pollTimer = setInterval(fetchState, 3000);
}

async function fetchState() {
  const res = await ParkVueAPI.getTerminalState();
  if (res.status !== 'success') return;
  const d = res.data;
  Terminal.lastState = d;

  // Update Top Stats
  let totalCap = 0, totalOcc = 0;
  Object.values(d.zones || {}).forEach(z => {
    totalCap += z.capacity || 0;
    totalOcc += z.occupied || 0;
  });
  const free = Math.max(0, totalCap - totalOcc);
  const occPct = totalCap > 0 ? Math.round((totalOcc / totalCap) * 100) : 0;

  setEl('stat-capacity', totalCap);
  setEl('stat-occupied', totalOcc);
  setEl('stat-occ-pct', `${occPct}% occupied`);
  setEl('stat-free', free);

  const grandRev = (+d.metrics?.grand_total || (+d.metrics?.cash_in_drawer || 0) + (+d.metrics?.digital_fastag_total || 0));
  setEl('stat-revenue', `₹${grandRev.toLocaleString('en-IN')}`);

  renderBayGrid();
  renderLedger(d.recent_transactions);
}

// ── 3. Parking Bays Rendering ──
function renderBayGrid() {
  const container = document.getElementById('bay-grid-container');
  if (!container || !Terminal.lastState?.zones) return;

  const allBays = [];
  Object.entries(Terminal.lastState.zones).forEach(([zKey, zData]) => {
    if (Terminal.activeFilter === 'all' || Terminal.activeFilter === zKey) {
      (zData.bays || []).forEach(bay => allBays.push({ ...bay, zoneKey: zKey }));
    }
  });

  container.innerHTML = allBays.map(bay => {
    const isFree = (bay.status || '').toLowerCase() === 'free';
    const statusClass = isFree ? 'free' : (bay.status === 'charging' ? 'charging' : 'occupied');
    const statusText = isFree ? 'Vacant' : (bay.dwell || 'Occupied');
    const plateText = bay.plate || '—';

    return `
      <div class="bay-slot ${statusClass}">
        <div class="bay-id">${bay.id}</div>
        <div class="bay-status">${isFree ? 'Available' : 'Occupied'}</div>
        <div class="bay-plate" title="${plateText}">${plateText}</div>
      </div>`;
  }).join('');
}

function initFilters() {
  document.querySelectorAll('.bay-filter button').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.bay-filter button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      Terminal.activeFilter = btn.dataset.filter;
      renderBayGrid();
    });
  });
}

// ── 4. Activity Ledger Rendering ──
function renderLedger(txns) {
  const tbody = document.getElementById('ledger-tbody');
  if (!tbody || !txns?.length) return;

  tbody.innerHTML = txns.slice(0, 8).map(t => {
    const isExit = (t.event_type || '').includes('EXIT') || (t.event_type || '').includes('CHECKOUT');
    const badge = isExit ? 'badge-blue' : 'badge-green';
    const time = t.timestamp ? new Date(t.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '—';
    return `
      <tr>
        <td>${time}</td>
        <td><span class="badge ${badge}">${t.event_type || 'ENTRY'}</span></td>
        <td style="font-family:var(--font-mono)">${t.plate || '—'}</td>
        <td>${t.zone_bay || '—'}</td>
        <td>${t.tender || '—'}</td>
        <td>₹${(+t.amount || 0).toFixed(2)}</td>
        <td><span class="badge badge-green">${t.status || 'DONE'}</span></td>
      </tr>`;
  }).join('');
}

// ── 5. Vehicle Entry Workflow ──
function initEntryWorkflow() {
  // Classification buttons
  document.querySelectorAll('.column-ops .panel:first-of-type .btn-select').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.column-ops .panel:first-of-type .btn-select').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      Terminal.entryCategory = btn.dataset.type;
    });
  });

  // Sample Plate Button
  document.getElementById('btn-sample-plate')?.addEventListener('click', () => {
    const samples = ['MH12AB1234', 'DL7SZ9876', 'KA09EF4321', 'TS08GH5678', 'HR26DK4004', 'UP32MN9012'];
    const chosen = samples[Math.floor(Math.random() * samples.length)];
    const input = document.getElementById('entry-plate-input');
    if (input) input.value = chosen;
    Toast.info(`Sample plate inserted: ${chosen}`);
  });

  // Check-In Submit
  document.getElementById('btn-assign-issue')?.addEventListener('click', async () => {
    const input = document.getElementById('entry-plate-input');
    const plate = input?.value.trim().toUpperCase();
    if (!plate) {
      Toast.error('Please enter a vehicle license plate number');
      input?.focus();
      return;
    }

    const isValet = document.getElementById('valet-check')?.checked;
    const res = await ParkVueAPI.entryCheckin({
      vehicle_plate: plate,
      classification: isValet ? 'Valet' : Terminal.entryCategory,
      is_valet: isValet
    });

    if (res.status !== 'success') {
      Toast.error(res.message || 'Check-in failed');
      return;
    }

    const d = res.data || res;
    showEntrySlip(d);
    Toast.success(`Ticket #${d.ticket} issued for Bay ${d.bay}!`);
    input.value = '';
    fetchState();
  });

  // Print entry slip
  document.getElementById('btn-print-entry-slip')?.addEventListener('click', () => {
    ParkVueBilling.printReceipt(Terminal.lastIssuedSlip || {});
  });
}

function showEntrySlip(d) {
  Terminal.lastIssuedSlip = d;
  const slip = document.getElementById('entry-pass-block');
  if (!slip) return;
  setEl('pass-ticket-num', `#${d.ticket}`);
  setEl('pass-bay-id', d.bay);
  setEl('pass-vehicle', d.plate);
  setEl('pass-entry-time', new Date(d.checkin || Date.now()).toLocaleTimeString());
  ParkVueBilling.renderBarcode(document.getElementById('pass-barcode'), d.ticket || 'TKT-0000');
  slip.classList.add('show');
}

// ── 6. Vehicle Exit & Billing Workflow ──
function initExitWorkflow() {
  document.getElementById('btn-fetch-ticket')?.addEventListener('click', searchExitTicket);
  document.getElementById('exit-ticket-input')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') searchExitTicket();
  });

  // Tender buttons
  document.querySelectorAll('.column-ops .panel:nth-of-type(2) .btn-select').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.column-ops .panel:nth-of-type(2) .btn-select').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      Terminal.exitTender = btn.dataset.tender;
      const cashRow = document.getElementById('cash-calc-row');
      if (cashRow) cashRow.style.display = Terminal.exitTender === 'cash' ? 'block' : 'none';
    });
  });

  // Cash change math
  document.getElementById('exit-cash-input')?.addEventListener('input', e => {
    const due = Terminal.exitData?.total_due || 0;
    const calc = ParkVueBilling.calcChange(e.target.value, due);
    const disp = document.getElementById('change-display');
    if (!disp) return;
    if (calc.valid) {
      disp.innerHTML = `<span style="color:var(--success)">Change Due: ₹${calc.change.toFixed(2)}</span>`;
    } else {
      disp.innerHTML = `<span style="color:var(--danger)">Short by: ₹${calc.deficit.toFixed(2)}</span>`;
    }
  });

  // Settle Payment & Open Gate
  document.getElementById('btn-barrier-release')?.addEventListener('click', async () => {
    if (!Terminal.exitData) return;
    const due = Terminal.exitData.total_due || 0;
    const ticket = Terminal.exitData.ticket;

    if (Terminal.exitTender === 'cash') {
      const paid = parseFloat(document.getElementById('exit-cash-input')?.value) || 0;
      if (paid < due) {
        Toast.error(`Insufficient payment. ₹${due} due.`);
        return;
      }
    }

    const res = await ParkVueAPI.settlePayment({
      ticket_id: ticket,
      tender_type: Terminal.exitTender,
      total_due: due
    });

    if (res.status !== 'success') {
      Toast.error(res.message || 'Settlement failed');
      return;
    }

    Toast.success(`Payment of ₹${due} settled via ${Terminal.exitTender.toUpperCase()}`);
    openGateBarrier();

    ParkVueBilling.printReceipt({
      ticket,
      plate: Terminal.exitData.plate,
      bay: Terminal.exitData.bay,
      checkin: Terminal.exitData.checkin,
      checkout: new Date().toISOString(),
      total_due: due,
      tender: Terminal.exitTender
    });

    // Reset Exit UI
    Terminal.exitData = null;
    document.getElementById('ticket-info-block')?.classList.remove('show');
    document.getElementById('exit-ticket-input').value = '';
    fetchState();
  });
}

async function searchExitTicket() {
  const query = document.getElementById('exit-ticket-input')?.value.trim();
  if (!query) {
    Toast.error('Please enter a Ticket ID or License Plate');
    return;
  }

  const res = await ParkVueAPI.exitCheckout(query);
  if (res.status !== 'success') {
    Toast.error(res.message || 'Ticket/Vehicle not found in active slots');
    return;
  }

  const d = res.data;
  Terminal.exitData = d;

  const box = document.getElementById('ticket-info-block');
  box?.classList.add('show');

  setEl('exit-plate-display', d.plate);
  setEl('exit-ticket-ref', `Ticket #${d.ticket} (Bay ${d.bay} · ${d.category || 'Standard'})`);
  setEl('exit-elapsed', `⏱ ${d.elapsed || '1h 00m'}`);
  setEl('exit-total-due', `₹${(+d.total_due || 40).toFixed(2)}`);

  const cashIn = document.getElementById('exit-cash-input');
  if (cashIn) cashIn.value = '';
  setEl('change-display', '');
}

// ── 7. Barrier Arm Animation ──
function openGateBarrier() {
  const el = document.getElementById('barrier-arm-state');
  if (!el) return;
  el.textContent = 'GATE OPEN — PASSING';
  el.className = 'badge badge-green';

  setTimeout(() => {
    el.textContent = 'GATE CLOSED';
    el.className = 'badge badge-green';
  }, 4000);
}

// ── 8. Modals & Actions ──
function initModals() {
  // Emergency Override
  document.getElementById('btn-emergency')?.addEventListener('click', async () => {
    if (confirm('Are you sure you want to trigger manual emergency gate release?')) {
      openGateBarrier();
      await ParkVueAPI.manualOverride('Emergency Manual Gate Release');
      Toast.info('Emergency barrier opened and logged.');
      fetchState();
    }
  });

  // End of Shift Report
  document.getElementById('btn-end-shift')?.addEventListener('click', async () => {
    const res = await ParkVueAPI.getShiftReport();
    if (res.status !== 'success') return;
    const d = res.data;
    setEl('modal-operator', `${d.operator} (Badge #${d.badge})`);
    setEl('modal-vehicles', `${d.vehicles_processed || 0} vehicles`);
    setEl('modal-cash', `₹${(+d.cash_in_drawer || 0).toLocaleString('en-IN')}`);
    setEl('modal-digital', `₹${(+d.digital_fastag || 0).toLocaleString('en-IN')}`);
    setEl('modal-grand-total', `₹${(+d.grand_total || 0).toLocaleString('en-IN')}`);
    document.getElementById('shift-modal')?.classList.add('open');
  });

  document.getElementById('modal-close')?.addEventListener('click', () => {
    document.getElementById('shift-modal')?.classList.remove('open');
  });
}

// ── Utilities ──
function setEl(id, val) {
  const el = document.getElementById(id);
  if (el) el.innerHTML = val;
}

const Toast = {
  show(msg, type = 'info') {
    const box = document.getElementById('toast-container');
    if (!box) return;
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.textContent = msg;
    box.appendChild(t);
    setTimeout(() => t.remove(), 3200);
  },
  success(m) { this.show(m, 'success'); },
  error(m) { this.show(m, 'error'); },
  info(m) { this.show(m, 'info'); }
};
