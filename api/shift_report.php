<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

require_once __DIR__ . '/db.php';
$pdo = getDB();

$metrics = $pdo->query("SELECT * FROM shift_metrics WHERE id = 1")->fetch() ?: [];

$cash    = (float)($metrics['cash_in_drawer'] ?? 0);
$digital = (float)($metrics['digital_fastag_total'] ?? 0);
$total   = $cash + $digital;

echo json_encode([
    'status' => 'success',
    'data'   => [
        'operator'           => $metrics['operator'] ?? 'Alan Vance',
        'badge'              => $metrics['badge'] ?? 'B',
        'shift_start'        => $metrics['shift_start'] ?? gmdate('c'),
        'vehicles_processed' => (int)($metrics['vehicles_processed'] ?? 0),
        'cash_in_drawer'     => $cash,
        'digital_fastag'     => $digital,
        'grand_total'        => $total,
        'barrier_overrides'  => (int)($metrics['barrier_overrides'] ?? 0)
    ]
]);
