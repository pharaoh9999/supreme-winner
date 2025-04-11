<?php
require './includes/config.php';
require './includes/functions.php';
require './includes/conn.php';

header('Content-Type: application/json');

if (!isset($_POST['phone']) || !isset($_POST['amount'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}
if (!isset($_POST['beneficiary_name']) || !isset($_POST['beneficiary_name'])) {
    echo json_encode(['success' => false, 'message' => 'Beneficiary name needed.']);
    exit;
}
if (!isset($_POST['beneficiary_phone']) || !isset($_POST['beneficiary_phone'])) {
    echo json_encode(['success' => false, 'message' => 'Beneficiary phone number needed.']);
    exit;
}

$beneficiary_phone = preg_replace('/[^0-9]/', '', $_POST['beneficiary_phone']);
$phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
$amount = floatval($_POST['amount']);
$beneficiary_name = $_POST['beneficiary_name'];

if (strlen($phone) !== 12 || substr($phone, 0, 3) !== '254' || $amount < 10) {
    echo json_encode(['success' => false, 'message' => 'Invalid withdrawal request.']);
    exit;
}

$pdo = new AutoConn();
$conn = $pdo->open();

try {
    $stmt = $conn->prepare("SELECT SUM(broker_fee) AS total FROM transactions WHERE client_phone = ? AND flutterwave_verified = 1");
    $stmt->execute([$phone]);
    $row = $stmt->fetch();
    $available = $row['total'] ?? 0;

    if ($amount > $available) {
        echo json_encode(['success' => false, 'message' => 'Withdrawal amount exceeds available balance.']);
        exit;
    }

    $reference = 'WD-' . uniqid();

    $secret_key = 'FLWSECK-1880561b6c6734eff7b4b978291085b8-1961d71dfe9vt-X';
    $ch = curl_init('https://api.flutterwave.com/v3/transfers');
    $payload = [
        'account_bank' => 'MPS',
        'account_number' => $beneficiary_phone,
        'amount' => $amount,
        'currency' => 'KES',
        'beneficiary_name' => $beneficiary_name,
        'reference' => $reference,
        'callback_url' => 'https://nairobi.autos/finalize_withdrawal.php',
        'narration' => 'Withdrawal - Nairobi Parking Module'
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $secret_key",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    if (!isset($result['status']) || $result['status'] !== 'success') {
        if (isset($result['message'])) {
            $flw_message = $result['message'];
        } else {
            $flw_message = 'Flutterwave transfer failed.';
        }
        echo json_encode([
            'success' => false,
            'message' => $flw_message,
            'debug' => $result
        ]);
        exit;
    }

    $save = $conn->prepare("INSERT INTO withdrawals (client_phone, amount, ref, status, created_at) VALUES (?, ?, ?, ?, NOW())");
    $save->execute([$phone, $amount, $reference, 'pending']);

    echo json_encode([
        'success' => true,
        'message' => "Withdrawal of KES " . number_format($amount, 2) . " has been initiated to $beneficiary_phone.",
        'reference' => $reference,
        'new_balance' => $available - $amount
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
}

$pdo->close();
