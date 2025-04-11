<?php
require './includes/conn.php';

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (!isset($data['data']['reference']) || !isset($data['data']['status'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid webhook data"]);
    exit;
}

$ref = $data['data']['reference'];
$status = strtolower($data['data']['status']);
$amount = isset($data['data']['amount']) ? floatval($data['data']['amount']) : 0;
$phone = isset($data['data']['account_number']) ? preg_replace('/[^0-9]/', '', $data['data']['account_number']) : null;

$pdo = new AutoConn();
$conn = $pdo->open();

// Secure transaction update
$update = $conn->prepare("UPDATE withdrawals SET status = ?, updated_at = NOW() WHERE ref = ?");
$update->execute([$status, $ref]);

// Log webhook to DB
$log = $conn->prepare("INSERT INTO withdrawal_logs (ref, status, amount, phone, raw_payload, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
$log->execute([
    $ref,
    $status,
    $amount,
    $phone,
    $payload
]);

http_response_code(200);
echo json_encode(["success" => true]);
$pdo->close();
