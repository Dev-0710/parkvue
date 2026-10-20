<?php
declare(strict_types=1);
$facility   = 'PARKVUE SMART PLAZA';
$gate_label = 'Gate Terminal #02';
$operator   = 'Alan Vance';
$badge      = 'B';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ParkVue — Smart Parking System</title>
  <link rel="stylesheet" href="assets/css/terminal.css">
  <link rel="stylesheet" href="assets/css/receipt.css" media="print">
</head>
<body>

  <!-- Top Navigation Header -->
  <header class="app-header">
    <div class="brand">
      <div class="brand-icon">P</div>
      <div>
        <div class="brand-title"><?= htmlspecialchars($facility) ?></div>
        <div class="brand-sub"><?= htmlspecialchars($gate_label) ?> · Smart Parking Management</div>
      </div>
    </div>
    <div class="header-actions">
      <div class="header-info">
        <strong><?= htmlspecialchars($operator) ?> (Badge #<?= htmlspecialchars($badge) ?>)</strong>
        <span id="utc-clock" class="live-clock">--:--:--</span>
      </div>
      <button class="btn btn-outline btn-sm" id="btn-end-shift">📊 Shift Report</button>
      <button class="btn btn-danger btn-sm" id="btn-emergency">⚠️ Emergency Gate</button>
    </div>
  </header>

  <!-- Summary Statistics Cards -->
  <section class="metrics-row">
    <div class="metric-card">
      <div class="metric-label">Total Capacity</div>
      <div id="stat-capacity" class="metric-value">221</div>
      <div class="metric-sub">Total Plaza Bays</div>
    </div>
    <div class="metric-card">
      <div class="metric-label">Occupied Spots</div>
      <div id="stat-occupied" class="metric-value" style="color:var(--danger)">--</div>
      <div id="stat-occ-pct" class="metric-sub">--% occupancy</div>
    </div>
    <div class="metric-card">
      <div class="metric-label">Available Spots</div>
      <div id="stat-free" class="metric-value" style="color:var(--success)">--</div>
      <div class="metric-sub">Ready for entry</div>
    </div>
    <div class="metric-card">
      <div class="metric-label">Total Collections</div>
      <div id="stat-revenue" class="metric-value" style="color:var(--info)">--</div>
      <div class="metric-sub">Cash + Digital</div>
    </div>
  </section>

  <!-- Main Workstation Layout -->
  <main class="main-grid">

    <!-- Left Column: Gate Operations (Entry & Exit) -->
    <div class="column-ops">

      <!-- 1. Vehicle Entry Check-in -->
      <section class="panel">
        <div class="panel-header">
          <div class="panel-title">🚗 Vehicle Entry (Check-In)</div>
          <button class="btn btn-outline btn-sm" id="btn-sample-plate" type="button">🎲 Sample Plate</button>
        </div>

        <div class="form-group">
          <label class="form-label" for="entry-plate-input">Vehicle License Plate Number</label>
          <input type="text" id="entry-plate-input" class="input-text input-mono" placeholder="e.g. MH12AB1234" maxlength="15" autocomplete="off">
        </div>

        <div class="form-group">
          <label class="form-label">Vehicle Category</label>
          <div class="btn-group" role="group">
            <button type="button" class="btn-select" data-type="2-wheeler">🏍️ 2-Wheeler</button>
            <button type="button" class="btn-select active" data-type="sedan">🚗 Car / Sedan</button>
            <button type="button" class="btn-select" data-type="suv">🚙 SUV</button>
            <button type="button" class="btn-select" data-type="ev">⚡ EV</button>
          </div>
        </div>

        <div class="form-group" style="display:flex;align-items:center;gap:8px;margin:10px 0;">
          <input type="checkbox" id="valet-check">
          <label for="valet-check" style="font-size:13px;cursor:pointer;">Assign to Dedicated Valet Staging (Zone V)</label>
        </div>

        <button class="btn btn-primary" id="btn-assign-issue">
          <span>Assign Spot &amp; Issue Entry Pass</span>
        </button>

        <!-- Entry Pass Display -->
        <div id="entry-pass-block" class="ticket-slip">
          <div class="ticket-slip-row"><strong>ENTRY TICKET PASS</strong><span id="pass-ticket-num" class="input-mono">#TKT-0000</span></div>
          <div class="ticket-slip-row"><span>Assigned Bay:</span><strong id="pass-bay-id" style="color:var(--success)">—</strong></div>
          <div class="ticket-slip-row"><span>Plate:</span><strong id="pass-vehicle" class="input-mono">—</strong></div>
          <div class="ticket-slip-row"><span>Check-In Time:</span><span id="pass-entry-time">—</span></div>
          <div id="pass-barcode" style="margin:10px 0;text-align:center;"></div>
          <button class="btn btn-outline btn-sm" id="btn-print-entry-slip" style="width:100%;margin-top:6px;">🖨️ Print Entry Slip</button>
        </div>
      </section>

      <!-- 2. Vehicle Exit & Billing Settlement -->
      <section class="panel">
        <div class="panel-header">
          <div class="panel-title">🏁 Vehicle Exit &amp; Payment</div>
          <div id="barrier-arm-state" class="badge badge-green">GATE CLOSED</div>
        </div>

        <div class="form-group">
          <label class="form-label" for="exit-ticket-input">Scan Ticket ID or Enter Vehicle Plate</label>
          <div class="search-box">
            <input type="text" id="exit-ticket-input" class="input-text input-mono" placeholder="e.g. TKT-7402 or CA 7XYZ89">
            <button class="btn btn-outline" id="btn-fetch-ticket">🔍 Search</button>
          </div>
        </div>

        <!-- Exit Ticket Settlement Box -->
        <div id="ticket-info-block" class="ticket-slip">
          <div class="ticket-slip-row">
            <div>
              <strong id="exit-plate-display" class="input-mono" style="font-size:15px">—</strong>
              <div id="exit-ticket-ref" style="font-size:12px;color:var(--text-muted)">Ticket #—</div>
            </div>
            <div id="exit-elapsed" class="badge badge-blue">⏱ 0h 00m</div>
          </div>
          <div class="ticket-slip-row" style="margin-top:8px">
            <span>Total Parking Fee:</span>
            <strong id="exit-total-due" style="font-size:18px;color:var(--success)">₹0.00</strong>
          </div>

          <div class="form-group" style="margin-top:12px">
            <label class="form-label">Payment Mode</label>
            <div class="btn-group">
              <button type="button" class="btn-select active" data-tender="cash">💵 Cash</button>
              <button type="button" class="btn-select" data-tender="upi">📲 UPI</button>
              <button type="button" class="btn-select" data-tender="card">💳 Card</button>
              <button type="button" class="btn-select" data-tender="fastag">📡 FASTag</button>
            </div>
          </div>

          <div class="form-group" id="cash-calc-row">
            <label class="form-label" for="exit-cash-input">Cash Tender Received (₹)</label>
            <input type="number" id="exit-cash-input" class="input-text input-mono" placeholder="0.00">
            <div id="change-display" style="font-size:12px;margin-top:4px;font-family:var(--font-mono)"></div>
          </div>

          <button class="btn btn-primary" id="btn-barrier-release" style="margin-top:10px;">
            🚦 Settle Payment &amp; Open Gate
          </button>
        </div>
      </section>

    </div>

    <!-- Right Column: Live Parking Bays & Recent Activity Ledger -->
    <div class="column-data">

      <!-- Live Bay Matrix -->
      <section class="panel">
        <div class="panel-header">
          <div class="panel-title">🅿️ Live Parking Bays</div>
          <div class="bay-filter">
            <button class="btn btn-sm btn-outline active" data-filter="all">All</button>
            <button class="btn btn-sm btn-outline" data-filter="A">Zone A (2W)</button>
            <button class="btn btn-sm btn-outline" data-filter="B">Zone B (Cars)</button>
            <button class="btn btn-sm btn-outline" data-filter="C">Zone C (EV)</button>
            <button class="btn btn-sm btn-outline" data-filter="V">Zone V (Valet)</button>
          </div>
        </div>

        <div id="bay-grid-container" class="bay-grid">
          <!-- Rendered dynamically by terminal.js -->
        </div>
      </section>

      <!-- Recent Transactions Table -->
      <section class="panel">
        <div class="panel-header">
          <div class="panel-title">📋 Activity Ledger</div>
          <span style="font-size:12px;color:var(--text-muted)">Live Updates</span>
        </div>

        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Time</th>
                <th>Event</th>
                <th>Plate</th>
                <th>Slot</th>
                <th>Tender</th>
                <th>Amount</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="ledger-tbody">
              <!-- Rendered dynamically by terminal.js -->
            </tbody>
          </table>
        </div>
      </section>

    </div>
  </main>

  <!-- End of Shift Report Modal -->
  <div id="shift-modal" class="modal-overlay">
    <div class="modal-card">
      <div class="modal-header">
        <h3>📊 Shift Summary Report</h3>
        <button class="modal-close" id="modal-close">&times;</button>
      </div>
      <div style="font-size:13px;line-height:1.8">
        <p><strong>Operator:</strong> <span id="modal-operator">—</span></p>
        <p><strong>Vehicles Handled:</strong> <span id="modal-vehicles" class="badge badge-blue">—</span></p>
        <p><strong>Cash Collections:</strong> <span id="modal-cash" style="color:var(--warning)">—</span></p>
        <p><strong>Digital / FASTag:</strong> <span id="modal-digital" style="color:var(--info)">—</span></p>
        <hr style="margin:10px 0;border-color:var(--border)">
        <p style="font-size:16px;"><strong>Grand Total:</strong> <span id="modal-grand-total" style="color:var(--success);font-weight:700">—</span></p>
      </div>
      <button class="btn btn-primary" onclick="document.getElementById('shift-modal').classList.remove('open')" style="margin-top:14px">Close</button>
    </div>
  </div>

  <div id="receipt-printable"></div>
  <div id="toast-container"></div>

  <!-- Scripts -->
  <script src="assets/js/api.js"></script>
  <script src="assets/js/billing.js"></script>
  <script src="assets/js/terminal.js"></script>
</body>
</html>
