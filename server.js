/**
 * server.js — ParkVue Smart Plaza · Node.js Server
 * Compact, readable Express backend for college project report.
 */

const express = require('express');
const fs      = require('fs');
const path    = require('path');

const app  = express();
const PORT = process.env.PORT || 3000;
const ROOT = __dirname;
const BAYS_FILE   = path.join(ROOT, 'assets', 'data', 'bays.json');
const LEDGER_FILE = path.join(ROOT, 'assets', 'data', 'ledger.json');

app.use(express.json());
app.use((req, res, next) => {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
  if (req.method === 'OPTIONS') return res.sendStatus(204);
  next();
});

// Helper: read and write JSON flat-files
const readJSON  = (f) => JSON.parse(fs.readFileSync(f, 'utf8').replace(/^\uFEFF/, ''));
const writeJSON = (f, d) => fs.writeFileSync(f, JSON.stringify(d, null, 2), 'utf8');
const now       = () => new Date().toISOString();

const TARIFFS = { '2-wheeler': 20, sedan: 40, suv: 50, standard: 40, ev: 60, valet: 80 };

// ── GET /api/terminal_state.php ──
app.get('/api/terminal_state.php', (req, res) => {
  try {
    const bays   = readJSON(BAYS_FILE);
    const ledger = readJSON(LEDGER_FILE);
    res.json({
      status: 'success',
      terminal: bays.terminal || 'GATE-02-NORTH',
      timestamp: now(),
      data: {
        zones: bays.zones,
        metrics: ledger.metrics || {},
        recent_transactions: ledger.recent_transactions || [],
        operator: 'Alan Vance',
        badge: 'B'
      }
    });
  } catch (e) {
    res.status(500).json({ status: 'error', message: e.message });
  }
});

// ── POST /api/entry_checkin.php ──
app.post('/api/entry_checkin.php', (req, res) => {
  try {
    const { vehicle_plate, classification, is_valet } = req.body;
    const plate = (vehicle_plate || '').toUpperCase().trim();
    if (!plate) return res.status(400).json({ status: 'error', message: 'License plate is required' });

    const bays   = readJSON(BAYS_FILE);
    const ledger = readJSON(LEDGER_FILE);

    // Determine target zone
    let zKey = is_valet ? 'V' : 'B';
    const cat = (classification || '').toLowerCase();
    if (cat.includes('2-wheeler')) zKey = 'A';
    else if (cat.includes('ev')) zKey = 'C';

    const zone = bays.zones[zKey];
    const bay  = (zone.bays || []).find(b => (b.status || '').toLowerCase() === 'free');
    if (!bay) return res.status(409).json({ status: 'error', message: `Zone ${zKey} is currently full` });

    const ticketId = `TKT-${Math.floor(1000 + Math.random() * 9000)}`;
    const checkinTime = now();

    // Allocate bay
    bay.status   = cat.includes('ev') ? 'charging' : 'occupied';
    bay.plate    = plate;
    bay.category = classification || 'Sedan';
    bay.ticket   = ticketId;
    bay.checkin  = checkinTime;
    bay.dwell    = 'Just now';
    zone.occupied = (zone.occupied || 0) + 1;
    zone.free     = Math.max(0, (zone.capacity || 0) - zone.occupied);

    // Log transaction
    ledger.recent_transactions = ledger.recent_transactions || [];
    ledger.recent_transactions.unshift({
      timestamp: checkinTime,
      event_type: 'ENTRY_CHECKIN',
      plate,
      zone_bay: bay.id,
      ticket: ticketId,
      tender: 'Pass Issued',
      amount: 0,
      status: 'COMPLETED'
    });

    writeJSON(BAYS_FILE, bays);
    writeJSON(LEDGER_FILE, ledger);

    res.json({
      status: 'success',
      data: { ticket: ticketId, bay: bay.id, plate, category: classification, checkin: checkinTime }
    });
  } catch (e) {
    res.status(500).json({ status: 'error', message: e.message });
  }
});

// ── GET /api/exit_checkout.php ──
app.get('/api/exit_checkout.php', (req, res) => {
  try {
    const q = (req.query.query || '').trim().toUpperCase();
    if (!q) return res.status(400).json({ status: 'error', message: 'Search query required' });

    const bays = readJSON(BAYS_FILE);
    let found = null, foundZone = null;

    for (const [zk, z] of Object.entries(bays.zones)) {
      for (const b of (z.bays || [])) {
        if (b.status !== 'free' && ((b.ticket || '').toUpperCase() === q || (b.plate || '').toUpperCase() === q)) {
          found = b; foundZone = zk; break;
        }
      }
      if (found) break;
    }

    if (!found) return res.status(404).json({ status: 'error', message: 'No active ticket or vehicle found' });

    const checkinMs = found.checkin ? new Date(found.checkin).getTime() : Date.now() - 3600000;
    const hours = Math.max(1, Math.ceil((Date.now() - checkinMs) / 3600000));
    const rate = TARIFFS[(found.category || 'sedan').toLowerCase()] || 40;
    const totalDue = hours * rate;

    res.json({
      status: 'success',
      data: {
        ticket: found.ticket,
        bay: found.id,
        plate: found.plate,
        category: found.category,
        checkin: found.checkin,
        elapsed: `${hours}h 00m`,
        total_due: totalDue
      }
    });
  } catch (e) {
    res.status(500).json({ status: 'error', message: e.message });
  }
});

// ── POST /api/settle_payment.php ──
app.post('/api/settle_payment.php', (req, res) => {
  try {
    const { ticket_id, tender_type, total_due } = req.body;
    const bays   = readJSON(BAYS_FILE);
    const ledger = readJSON(LEDGER_FILE);

    let foundBay = null, foundZone = null;
    for (const [zk, z] of Object.entries(bays.zones)) {
      for (const b of (z.bays || [])) {
        if (b.ticket === ticket_id) { foundBay = b; foundZone = z; break; }
      }
      if (foundBay) break;
    }

    if (foundBay) {
      const plate = foundBay.plate;
      foundBay.status   = 'free';
      foundBay.plate    = null;
      foundBay.ticket   = null;
      foundBay.checkin  = null;
      foundBay.dwell    = null;
      foundZone.occupied = Math.max(0, (foundZone.occupied || 1) - 1);
      foundZone.free     = (foundZone.capacity || 0) - foundZone.occupied;

      // Update ledger
      const due = parseFloat(total_due) || 40;
      ledger.recent_transactions = ledger.recent_transactions || [];
      ledger.recent_transactions.unshift({
        timestamp: now(),
        event_type: 'FEE_SETTLED',
        plate,
        zone_bay: foundBay.id,
        ticket: ticket_id,
        tender: tender_type || 'Cash',
        amount: due,
        status: 'COMPLETED'
      });

      // Update metrics
      ledger.metrics = ledger.metrics || {};
      ledger.metrics.vehicles_processed = (ledger.metrics.vehicles_processed || 0) + 1;
      if ((tender_type || '').toLowerCase() === 'cash') {
        ledger.metrics.cash_in_drawer = (ledger.metrics.cash_in_drawer || 0) + due;
      } else {
        ledger.metrics.digital_fastag_total = (ledger.metrics.digital_fastag_total || 0) + due;
      }

      writeJSON(BAYS_FILE, bays);
      writeJSON(LEDGER_FILE, ledger);
    }

    res.json({
      status: 'success',
      data: { receipt_ref: `REC-${Date.now().toString().slice(-6)}`, ticket_id, total_due }
    });
  } catch (e) {
    res.status(500).json({ status: 'error', message: e.message });
  }
});

// ── POST /api/manual_override.php ──
app.post('/api/manual_override.php', (req, res) => {
  try {
    const ledger = readJSON(LEDGER_FILE);
    ledger.metrics = ledger.metrics || {};
    ledger.metrics.barrier_overrides = (ledger.metrics.barrier_overrides || 0) + 1;
    writeJSON(LEDGER_FILE, ledger);
    res.json({ status: 'success', message: 'Manual override logged' });
  } catch (e) {
    res.status(500).json({ status: 'error', message: e.message });
  }
});

// ── GET /api/shift_report.php ──
app.get('/api/shift_report.php', (req, res) => {
  try {
    const ledger = readJSON(LEDGER_FILE);
    const m = ledger.metrics || {};
    const cash = +m.cash_in_drawer || 0;
    const digital = +m.digital_fastag_total || 0;
    res.json({
      status: 'success',
      data: {
        operator: 'Alan Vance',
        badge: 'B',
        vehicles_processed: m.vehicles_processed || 0,
        cash_in_drawer: cash,
        digital_fastag: digital,
        grand_total: cash + digital
      }
    });
  } catch (e) {
    res.status(500).json({ status: 'error', message: e.message });
  }
});

// ── Serve index.php as HTML ──
app.get('/', (req, res) => {
  try {
    let html = fs.readFileSync(path.join(ROOT, 'index.php'), 'utf8');
    html = html.replace(/<\?php[\s\S]*?\?>/g, '');
    html = html.replace(/<\?=\s*htmlspecialchars\(\$(\w+)\)\s*\?>/g, (_, v) => {
      const vars = { facility: 'PARKVUE SMART PLAZA', gate_label: 'Gate Terminal #02', operator: 'Alan Vance', badge: 'B' };
      return vars[v] || '';
    });
    res.setHeader('Content-Type', 'text/html');
    res.send(html);
  } catch (e) {
    res.status(500).send('Error loading page: ' + e.message);
  }
});

app.use(express.static(ROOT));

app.listen(PORT, () => {
  console.log(`ParkVue Server running at http://localhost:${PORT}`);
});
