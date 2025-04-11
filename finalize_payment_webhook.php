<?php
require './includes/conn.php';

header('Content-Type: application/json');

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (!isset($data['event']) || $data['event'] !== 'charge.success') {
    http_response_code(400);
    echo json_encode(["error" => "Invalid or unsupported webhook event"]);
    exit;
}

$tx_ref = $data['data']['reference'] ?? null;
$status = strtolower($data['data']['status'] ?? '');
$amount_kobo = $data['data']['amount'] ?? 0;
$amount = $amount_kobo / 100;
$email = $data['data']['customer']['email'] ?? null;
$paid_at = $data['data']['paid_at'] ?? null;

if (!$tx_ref || !$status || !$amount) {
    http_response_code(400);
    echo json_encode(["error" => "Missing essential transaction data"]);
    exit;
}

$pdo = new AutoConn();
$conn = $pdo->open();

try {
    $stmt = $conn->prepare("SELECT transaction_no FROM transactions WHERE tx_ref = ? LIMIT 1");
    $stmt->execute([$tx_ref]);
    $transaction = $stmt->fetch();

    if (!$transaction) {
        http_response_code(404);
        echo json_encode(["error" => "Transaction not found"]);
        exit;
    }

    // Update transaction to mark it verified
    $update = $conn->prepare("UPDATE transactions SET flutterwave_verified = 1, updated_at = NOW() WHERE tx_ref = ?");
    $update->execute([$tx_ref]);

    // Log the webhook
    $log = $conn->prepare("INSERT INTO payment_logs (ref, status, amount, email, raw_payload, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $log->execute([
        $tx_ref,
        $status,
        $amount,
        $email,
        $payload
    ]);

    http_response_code(200);
    echo json_encode(["success" => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "System error: " . $e->getMessage()]);
}

$pdo->close();
