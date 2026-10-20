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
$ticket_id   = strtoupper(trim($input['ticket_id'] ?? ''));
$tender_type = ucfirst(trim($input['tender_type'] ?? 'Cash'));
$total_due   = (float)($input['total_due'] ?? 40.0);

if (empty($ticket_id)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Ticket ID is required']);
    exit;
}

// Find bay by ticket
$stmt = $pdo->prepare("SELECT * FROM bays WHERE ticket = ? LIMIT 1");
$stmt->execute([$ticket_id]);
$bay = $stmt->fetch();

if ($bay) {
    $plate = $bay['plate'];
    $bay_id = $bay['id'];

    // Free up bay in MySQL
    $upd = $pdo->prepare("UPDATE bays SET status = 'free', plate = NULL, ticket = NULL, checkin = NULL, dwell = NULL WHERE ticket = ?");
    $upd->execute([$ticket_id]);

    // Record completed transaction
    $txn = $pdo->prepare("INSERT INTO transactions (ticket, plate, zone_bay, event_type, tender, amount, status) VALUES (?, ?, ?, 'FEE_SETTLED', ?, ?, 'COMPLETED')");
    $txn->execute([$ticket_id, $plate, $bay_id, $tender_type, $total_due]);

    // Update revenue metrics
    if (strtolower($tender_type) === 'cash') {
        $pStmt = $pdo->prepare("UPDATE shift_metrics SET cash_in_drawer = cash_in_drawer + ? WHERE id = 1");
    } else {
        $pStmt = $pdo->prepare("UPDATE shift_metrics SET digital_fastag_total = digital_fastag_total + ? WHERE id = 1");
    }
    $pStmt->execute([$total_due]);
}

echo json_encode([
    'status' => 'success',
    'data'   => [
        'receipt_ref' => 'REC-' . substr((string)time(), -6),
        'ticket_id'   => $ticket_id,
        'total_due'   => $total_due
    ]
]);
