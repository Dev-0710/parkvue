<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$ledger_file = __DIR__ . '/../assets/data/ledger.json';
$bays_file   = __DIR__ . '/../assets/data/bays.json';

if (!file_exists($ledger_file)) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'code' => 503, 'message' => 'Ledger unavailable']);
    exit;
}

$raw_input = file_get_contents('php://input');
$body      = json_decode($raw_input, true) ?? [];

$reason   = trim($body['reason'] ?? 'MANUAL EMERGENCY RELEASE');
$operator = trim($body['operator'] ?? 'Operator Alan Vance');
$now_iso  = gmdate('Y-m-d\TH:i:s\Z');
$txn_id   = 'TXN-' . rand(10000, 99999);

$ledger_raw = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($ledger_file));
$ledger     = json_decode($ledger_raw, true);

// Append EMERGENCY override transaction
array_unshift($ledger['transactions'], [
    'id'         => $txn_id,
    'timestamp'  => $now_iso,
    'event_type' => 'MANUAL_OVERRIDE',
    'plate'      => 'EMERGENCY-GATE-02',
    'zone_bay'   => 'LANE-02 PASS',
    'tender'     => $operator,
    'amount'     => 0.00,
    'status'     => 'EMERGENCY'
]);

$ledger['metrics']['barrier_overrides'] = (int)($ledger['metrics']['barrier_overrides'] ?? 0) + 1;
file_put_contents($ledger_file, json_encode($ledger, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

// Also set barrier status to open in bays.json if present
if (file_exists($bays_file)) {
    $bays_raw = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($bays_file));
    $bays     = json_decode($bays_raw, true);
    if (isset($bays['barrier'])) {
        $bays['barrier']['status'] = 'emergency_open';
        $bays['last_updated'] = $now_iso;
        file_put_contents($bays_file, json_encode($bays, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}

echo json_encode([
    'status'      => 'success',
    'command'     => 'BARRIER_EMERGENCY_OVERRIDE',
    'barrier_cmd' => 'BARRIER_PULSE_OPEN',
    'event_id'    => $txn_id,
    'timestamp'   => $now_iso,
    'reason'      => $reason,
    'overrides'   => $ledger['metrics']['barrier_overrides'],
    'message'     => 'Emergency barrier release logged to ledger successfully'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
