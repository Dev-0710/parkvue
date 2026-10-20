<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$ticket_id   = strtoupper(trim($input['ticket_id'] ?? ''));
$tender_type = ucfirst(trim($input['tender_type'] ?? 'Cash'));
$total_due   = (float)($input['total_due'] ?? 40.0);

if (empty($ticket_id)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Ticket ID is required']);
    exit;
}

$bays_file   = __DIR__ . '/../assets/data/bays.json';
$ledger_file = __DIR__ . '/../assets/data/ledger.json';
$bays   = json_decode(file_get_contents($bays_file), true);
$ledger = json_decode(file_get_contents($ledger_file), true);

$found_bay  = null;
$found_zone = null;

foreach ($bays['zones'] as &$zone) {
    foreach ($zone['bays'] as &$bay) {
        if (strtoupper((string)($bay['ticket'] ?? '')) === $ticket_id) {
            $found_bay  = &$bay;
            $found_zone = &$zone;
            break 2;
        }
    }
}

if ($found_bay) {
    $plate = $found_bay['plate'];
    $bay_id = $found_bay['id'];

    // Free bay
    $found_bay['status']   = 'free';
    $found_bay['plate']    = null;
    $found_bay['ticket']   = null;
    $found_bay['checkin']  = null;
    $found_bay['dwell']    = null;
    $found_zone['occupied'] = max(0, (int)$found_zone['occupied'] - 1);
    $found_zone['free']     = (int)$found_zone['capacity'] - (int)$found_zone['occupied'];

    // Update transactions & ledger
    $now_iso = gmdate('c');
    if (!isset($ledger['transactions'])) $ledger['transactions'] = [];
    array_unshift($ledger['transactions'], [
        'timestamp'  => $now_iso,
        'event_type' => 'FEE_SETTLED',
        'plate'      => $plate,
        'zone_bay'   => $bay_id,
        'ticket'     => $ticket_id,
        'tender'     => $tender_type,
        'amount'     => $total_due,
        'status'     => 'COMPLETED'
    ]);
    $ledger['recent_transactions'] = $ledger['transactions'];

    if (strtolower($tender_type) === 'cash') {
        $ledger['metrics']['cash_in_drawer'] = (float)($ledger['metrics']['cash_in_drawer'] ?? 0) + $total_due;
    } else {
        $ledger['metrics']['digital_fastag_total'] = (float)($ledger['metrics']['digital_fastag_total'] ?? 0) + $total_due;
    }

    file_put_contents($bays_file, json_encode($bays, JSON_PRETTY_PRINT));
    file_put_contents($ledger_file, json_encode($ledger, JSON_PRETTY_PRINT));
}

echo json_encode([
    'status' => 'success',
    'data'   => [
        'receipt_ref' => 'REC-' . substr((string)time(), -6),
        'ticket_id'   => $ticket_id,
        'total_due'   => $total_due
    ]
]);
