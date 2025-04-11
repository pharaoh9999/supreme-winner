<?php
require './includes/conn.php';

header('Content-Type: application/json');

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (!isset($data['event']) || !isset($data['data']['reference'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid webhook data"]);
    exit;
}

$event = $data['event'];
$ref = $data['data']['reference'];
$status = strtolower($data['data']['status'] ?? 'unknown');
$amount = isset($data['data']['amount']) ? floatval($data['data']['amount']) / 100 : 0;
$phone = isset($data['data']['recipient']['details']['account_number']) ? preg_replace('/[^0-9]/', '', $data['data']['recipient']['details']['account_number']) : null;

$pdo = new AutoConn();
$conn = $pdo->open();

try {
    // Determine correct status from event
    switch ($event) {
        case 'transfer.success':
            $final_status = 'success';
            break;
        case 'transfer.failed':
            $final_status = 'failed';
            break;
        case 'transfer.reversed':
            $final_status = 'reversed';
            break;
        default:
            $final_status = 'unknown';
            break;
    }

    // Secure update
    $update = $conn->prepare("UPDATE withdrawals SET status = ?, updated_at = NOW() WHERE ref = ?");
    $update->execute([$final_status, $ref]);

    // Log webhook
    $log = $conn->prepare("INSERT INTO withdrawal_logs (ref, status, amount, phone, raw_payload, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $log->execute([
        $ref,
        $final_status,
        $amount,
        $phone,
        $payload
    ]);

    http_response_code(200);
    echo json_encode(["success" => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "System failure: " . $e->getMessage()]);
}

$pdo->close();
