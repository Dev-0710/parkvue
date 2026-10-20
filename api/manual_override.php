<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db.php';
$pdo = getDB();

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$operator = trim($body['operator'] ?? 'Operator Alan Vance');

// Log emergency override transaction in MySQL
$stmt = $pdo->prepare("
    INSERT INTO transactions (ticket, plate, zone_bay, event_type, tender, amount, status) 
    VALUES ('EMERGENCY', 'EMERGENCY-GATE-02', 'LANE-02 PASS', 'MANUAL_OVERRIDE', ?, 0, 'EMERGENCY')
");
$stmt->execute([$operator]);

// Increment override counter in metrics
$pdo->exec("UPDATE shift_metrics SET barrier_overrides = barrier_overrides + 1 WHERE id = 1");

echo json_encode([
    'status'  => 'success',
    'message' => 'Manual emergency barrier release logged'
]);
