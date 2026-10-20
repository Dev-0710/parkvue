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

require_once __DIR__ . '/db.php';
$pdo = getDB();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$plate = strtoupper(trim($input['vehicle_plate'] ?? $input['plate'] ?? ''));
$category = trim($input['classification'] ?? $input['category'] ?? 'Sedan');
$is_valet = !empty($input['is_valet']);

if (empty($plate)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Vehicle plate is required']);
    exit;
}

// Select zone
$zone_code = 'B';
$cat_lower = strtolower($category);
if ($is_valet) $zone_code = 'V';
elseif (str_contains($cat_lower, '2-wheeler')) $zone_code = 'A';
elseif (str_contains($cat_lower, 'ev')) $zone_code = 'C';

// Find vacant bay
$bayStmt = $pdo->prepare("SELECT id FROM bays WHERE zone_code = ? AND status = 'free' LIMIT 1");
$bayStmt->execute([$zone_code]);
$bay = $bayStmt->fetch();

if (!$bay) {
    http_response_code(409);
    echo json_encode(['status' => 'error', 'message' => "Zone {$zone_code} is currently full"]);
    exit;
}

$bay_id = $bay['id'];
$ticket_id = 'TKT-' . rand(7410, 9999);
$new_status = ($zone_code === 'C') ? 'charging' : 'occupied';
$now_iso = gmdate('c');

// Allocate bay in MySQL
$upd = $pdo->prepare("UPDATE bays SET status = ?, plate = ?, category = ?, ticket = ?, checkin = NOW(), dwell = 'Just now' WHERE id = ?");
$upd->execute([$new_status, $plate, $category, $ticket_id, $bay_id]);

// Log transaction in MySQL
$txn = $pdo->prepare("INSERT INTO transactions (ticket, plate, zone_bay, event_type, tender, amount, status) VALUES (?, ?, ?, 'ENTRY_CHECKIN', 'Pass Issued', 0, 'COMPLETED')");
$txn->execute([$ticket_id, $plate, $bay_id]);

// Update shift metrics
$pdo->exec("UPDATE shift_metrics SET vehicles_processed = vehicles_processed + 1 WHERE id = 1");

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
