<?php
require './includes/config.php';
require './includes/functions.php';
require './includes/conn.php';

if (!isset($_POST['phone']) || !isset($_POST['amount'])) {
    die("Invalid request.");
}

$phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
$amount = floatval($_POST['amount']);

if (strlen($phone) !== 12 || substr($phone, 0, 3) !== '254' || $amount < 10) {
    die("Invalid withdrawal request.");
}

$pdo = new AutoConn();
$conn = $pdo->open();

try {
    // Re-check available earnings (prevention of form tampering)
    $stmt = $conn->prepare("SELECT SUM(broker_fee) AS total FROM transactions WHERE client_phone = ? AND flutterwave_verified = 1");
    $stmt->execute([$phone]);
    $row = $stmt->fetch();
    $available = $row['total'] ?? 0;

    if ($amount > $available) {
        die("Withdrawal amount exceeds available balance.");
    }

    // Generate unique transfer reference
    $reference = 'WD-' . uniqid();

    // FLUTTERWAVE TRANSFER API
    $secret_key = 'FLWSECK_TEST-xxxxxxxxxxxxxxxxx'; // Replace with real secret key
    $ch = curl_init('https://api.flutterwave.com/v3/transfers');
    $payload = [
        'account_bank' => 'MPS', // M-PESA
        'account_number' => $phone,
        'amount' => $amount,
        'currency' => 'KES',
        'reference' => $reference,
        'callback_url' => 'https://yoursite.com/finalize_withdrawal.php',
        'narration' => 'Withdrawal - Nairobi Parking'
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
        die("Flutterwave transfer failed. Try again later.");
    }

    // Save withdrawal request
    $save = $conn->prepare("INSERT INTO withdrawals (client_phone, amount, ref, status, created_at) VALUES (?, ?, ?, ?, NOW())");
    $save->execute([$phone, $amount, $reference, 'pending']);

    echo "<div class='alert alert-success'>Withdrawal of KES " . number_format($amount, 2) . " has been initiated to $phone. Ref: $reference</div>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$pdo->close();
