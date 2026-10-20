<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$query = strtoupper(trim($_GET['query'] ?? ''));
if (empty($query)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Ticket or license plate query required']);
    exit;
}

$bays_file = __DIR__ . '/../assets/data/bays.json';
$bays = json_decode(file_get_contents($bays_file), true);

$found = null;
foreach ($bays['zones'] as $zone) {
    foreach ($zone['bays'] as $bay) {
        if ($bay['status'] !== 'free' && (
            strtoupper((string)($bay['ticket'] ?? '')) === $query ||
            strtoupper((string)($bay['plate'] ?? '')) === $query
        )) {
            $found = $bay;
            break 2;
        }
    }
}

if (!$found) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => "No active session found for query: {$query}"]);
    exit;
}

$tariffs = ['2-wheeler' => 20, 'sedan' => 40, 'suv' => 50, 'ev' => 60, 'valet' => 80];
$checkin_ts = !empty($found['checkin']) ? strtotime($found['checkin']) : time() - 3600;
$hours = max(1, (int)ceil((time() - $checkin_ts) / 3600));
$rate = $tariffs[strtolower($found['category'] ?? 'sedan')] ?? 40;
$total_due = $hours * $rate;

echo json_encode([
    'status' => 'success',
    'data'   => [
        'ticket'    => $found['ticket'],
        'bay'       => $found['id'],
        'plate'     => $found['plate'],
        'category'  => $found['category'],
        'checkin'   => $found['checkin'],
        'elapsed'   => "{$hours}h 00m",
        'total_due' => $total_due
    ]
]);
