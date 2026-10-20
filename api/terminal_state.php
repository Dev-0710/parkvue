<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$bays_file   = __DIR__ . '/../assets/data/bays.json';
$ledger_file = __DIR__ . '/../assets/data/ledger.json';

if (!file_exists($bays_file) || !file_exists($ledger_file)) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Data store unavailable']);
    exit;
}

$bays   = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($bays_file)), true);
$ledger = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($ledger_file)), true);

echo json_encode([
    'status'    => 'success',
    'terminal'  => $bays['terminal'] ?? 'GATE-02-NORTH',
    'timestamp' => gmdate('c'),
    'data'      => [
        'zones'               => $bays['zones'] ?? [],
        'metrics'             => $ledger['metrics'] ?? [],
        'recent_transactions' => $ledger['transactions'] ?? $ledger['recent_transactions'] ?? [],
        'operator'            => $ledger['operator'] ?? 'Alan Vance',
        'badge'               => $ledger['badge'] ?? 'B'
    ]
], JSON_PRETTY_PRINT);
