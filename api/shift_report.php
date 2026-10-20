<?php
declare(strict_types=1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

$ledger_file = __DIR__ . '/../assets/data/ledger.json';

if (!file_exists($ledger_file)) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'code' => 503, 'message' => 'Ledger unavailable']);
    exit;
}

$ledger = json_decode(file_get_contents($ledger_file), true);
$metrics = $ledger['metrics'];

$cash_total    = (float)$metrics['cash_in_drawer'];
$digital_total = (float)$metrics['digital_fastag_total'];
$grand_total   = $cash_total + $digital_total;
$digital_pct   = $grand_total > 0 ? round(($digital_total / $grand_total) * 100, 1) : 0;

// Break down by event type
$by_type = [];
foreach ($ledger['transactions'] as $txn) {
    $t = $txn['event_type'];
    if (!isset($by_type[$t])) $by_type[$t] = ['count' => 0, 'total' => 0.0];
    $by_type[$t]['count']++;
    $by_type[$t]['total'] = round($by_type[$t]['total'] + (float)$txn['amount'], 2);
}

// Override events
$overrides = array_values(array_filter($ledger['transactions'], fn($t) => $t['event_type'] === 'MANUAL_OVERRIDE'));

echo json_encode([
    'status'    => 'success',
    'terminal'  => 'GATE-02-NORTH',
    'timestamp' => gmdate('c'),
    'data'      => [
        'operator'           => $ledger['operator'],
        'badge'              => $ledger['badge'],
        'shift_start'        => $ledger['shift_start'],
        'shift_end'          => gmdate('c'),
        'vehicles_processed' => $metrics['vehicles_processed'],
        'cash_in_drawer'     => $cash_total,
        'digital_fastag'     => $digital_total,
        'grand_total'        => round($grand_total, 2),
        'digital_pct'        => $digital_pct,
        'barrier_overrides'  => $metrics['barrier_overrides'],
        'breakdown_by_type'  => $by_type,
        'overrides'          => $overrides,
        'all_transactions'   => $ledger['transactions'],
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
