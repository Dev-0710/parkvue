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

require_once __DIR__ . '/db.php';
$pdo = getDB();

$stmt = $pdo->prepare("
    SELECT b.*, z.hourly_rate 
    FROM bays b 
    JOIN zones z ON b.zone_code = z.code 
    WHERE (UPPER(b.ticket) = ? OR UPPER(b.plate) = ?) AND b.status != 'free' 
    LIMIT 1
");
$stmt->execute([$query, $query]);
$found = $stmt->fetch();

if (!$found) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => "No active session found for query: {$query}"]);
    exit;
}

$checkin_ts = !empty($found['checkin']) ? strtotime($found['checkin']) : time() - 3600;
$hours = max(1, (int)ceil((time() - $checkin_ts) / 3600));
$rate = (float)($found['hourly_rate'] ?? 40.0);
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
