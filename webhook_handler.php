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
$phone = null;

if (isset($data['data']['recipient']['details']['account_number'])) {
    $phone = preg_replace('/[^0-9]/', '', $data['data']['recipient']['details']['account_number']);
} elseif (isset($data['data']['customer']['phone'])) {
    $phone = preg_replace('/[^0-9]/', '', $data['data']['customer']['phone']);
}

$pdo = new AutoConn();
$conn = $pdo->open();

try {
    $log_table = '';
    $update_table = '';

    if (str_starts_with($event, 'transfer.')) {
        // Handle withdrawal events
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

        $update = $conn->prepare("UPDATE withdrawals SET status = ?, updated_at = NOW() WHERE ref = ?");
        $update->execute([$final_status, $ref]);

        $log_table = 'withdrawal_logs';
    } elseif ($event === 'charge.success') {
        // Handle payment events
        $final_status = 'success';

        $update = $conn->prepare("UPDATE transactions SET flutterwave_verified = 1, updated_at = NOW() WHERE tx_ref = ?");
        $update->execute([$ref]);

        $log_table = 'payment_logs';
    } else {
        $final_status = 'ignored';
    }

    if ($log_table !== '') {
        $log = $conn->prepare("INSERT INTO $log_table (ref, status, amount, phone, raw_payload, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $log->execute([
            $ref,
            $final_status,
            $amount,
            $phone,
            $payload
        ]);
    }

    http_response_code(200);
    echo json_encode(["success" => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "System failure: " . $e->getMessage()]);
}

$pdo->close();
