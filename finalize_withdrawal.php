<?php
require './includes/conn.php';

header('Content-Type: application/json');

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (!isset($data['data']['reference']) || !isset($data['data']['status'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid webhook data"]);
    exit;
}

$ref = $data['data']['reference'];
$status = strtolower($data['data']['status']);
$amount = isset($data['data']['amount']) ? floatval($data['data']['amount']) / 100 : 0; // Paystack sends amount in kobo
$phone = isset($data['data']['recipient']['details']['account_number']) ? preg_replace('/[^0-9]/', '', $data['data']['recipient']['details']['account_number']) : null;

$pdo = new AutoConn();
$conn = $pdo->open();

try {
    // Update the withdrawal status securely
    $update = $conn->prepare("UPDATE withdrawals SET status = ?, updated_at = NOW() WHERE ref = ?");
    $update->execute([$status, $ref]);

    // Log the webhook for auditing
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
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "System failure: " . $e->getMessage()]);
}

$pdo->close();
