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
$plate = strtoupper(trim($input['vehicle_plate'] ?? $input['plate'] ?? ''));
$category = trim($input['classification'] ?? $input['category'] ?? 'Sedan');
$is_valet = !empty($input['is_valet']);

if (empty($plate)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Vehicle plate is required']);
    exit;
}

$bays_file   = __DIR__ . '/../assets/data/bays.json';
$ledger_file = __DIR__ . '/../assets/data/ledger.json';
$bays   = json_decode(file_get_contents($bays_file), true);
$ledger = json_decode(file_get_contents($ledger_file), true);

// Select zone based on vehicle type
$zone_key = 'B';
$cat_lower = strtolower($category);
if ($is_valet) $zone_key = 'V';
elseif (str_contains($cat_lower, '2-wheeler')) $zone_key = 'A';
elseif (str_contains($cat_lower, 'ev')) $zone_key = 'C';

// Find vacant bay in zone
$bay_idx = null;
foreach ($bays['zones'][$zone_key]['bays'] as $i => $b) {
    if (in_array(strtolower($b['status']), ['free', 'vacant'], true)) {
        $bay_idx = $i;
        break;
    }
}

if ($bay_idx === null) {
    http_response_code(409);
    echo json_encode(['status' => 'error', 'message' => "Zone {$zone_key} is full"]);
    exit;
}

$ticket_id = 'TKT-' . rand(7410, 9999);
$now_iso   = gmdate('c');
$bay_ref   = &$bays['zones'][$zone_key]['bays'][$bay_idx];
$bay_id    = $bay_ref['id'];

// Assign bay
$bay_ref['status']   = ($zone_key === 'C') ? 'charging' : 'occupied';
$bay_ref['plate']    = $plate;
$bay_ref['category'] = $category;
$bay_ref['checkin']  = $now_iso;
$bay_ref['ticket']   = $ticket_id;
$bay_ref['dwell']    = 'Just now';

$bays['zones'][$zone_key]['occupied'] = min((int)$bays['zones'][$zone_key]['capacity'], (int)$bays['zones'][$zone_key]['occupied'] + 1);
$bays['zones'][$zone_key]['free']     = max(0, (int)$bays['zones'][$zone_key]['capacity'] - (int)$bays['zones'][$zone_key]['occupied']);

// Log in ledger
array_unshift($ledger['recent_transactions'], [
    'timestamp'  => $now_iso,
    'event_type' => 'ENTRY_CHECKIN',
    'plate'      => $plate,
    'zone_bay'   => $bay_id,
    'ticket'     => $ticket_id,
    'tender'     => 'Pass Issued',
    'amount'     => 0.00,
    'status'     => 'COMPLETED'
]);
$ledger['metrics']['vehicles_processed'] = (int)($ledger['metrics']['vehicles_processed'] ?? 0) + 1;

file_put_contents($bays_file, json_encode($bays, JSON_PRETTY_PRINT));
file_put_contents($ledger_file, json_encode($ledger, JSON_PRETTY_PRINT));

echo json_encode([
    'status' => 'success',
    'data'   => [
        'ticket'   => $ticket_id,
        'bay'      => $bay_id,
        'plate'    => $plate,
        'category' => $category,
        'checkin'  => $now_iso
    ]
]);
