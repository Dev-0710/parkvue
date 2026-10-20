<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/db.php';
$pdo = getDB();

// Fetch zones with active bay occupancy
$zonesStmt = $pdo->query("SELECT * FROM zones ORDER BY code ASC");
$zonesData = [];
while ($z = $zonesStmt->fetch()) {
    $code = $z['code'];
    $baysStmt = $pdo->prepare("SELECT * FROM bays WHERE zone_code = ? ORDER BY id ASC");
    $baysStmt->execute([$code]);
    $bays = $baysStmt->fetchAll();

    $occupied = 0;
    foreach ($bays as $b) {
        if ($b['status'] !== 'free') $occupied++;
    }

    $zonesData[$code] = [
        'code'     => $code,
        'label'    => $z['label'],
        'type'     => $z['type'],
        'capacity' => (int)$z['capacity'],
        'occupied' => $occupied,
        'free'     => max(0, (int)$z['capacity'] - $occupied),
        'rate'     => (float)$z['hourly_rate'],
        'bays'     => $bays
    ];
}

// Fetch shift metrics and transactions
$metrics = $pdo->query("SELECT * FROM shift_metrics WHERE id = 1")->fetch() ?: [];
$txns = $pdo->query("SELECT * FROM transactions ORDER BY id DESC LIMIT 10")->fetchAll();

echo json_encode([
    'status'    => 'success',
    'terminal'  => 'GATE-02-NORTH',
    'timestamp' => gmdate('c'),
    'data'      => [
        'zones'               => $zonesData,
        'metrics'             => $metrics,
        'recent_transactions' => $txns,
        'operator'            => $metrics['operator'] ?? 'Alan Vance',
        'badge'               => $metrics['badge'] ?? 'B'
    ]
], JSON_PRETTY_PRINT);
