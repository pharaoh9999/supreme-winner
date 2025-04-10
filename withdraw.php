<?php
require './includes/config.php';
require './includes/functions.php';
require './includes/conn.php';

header('Content-Type: application/json');

if (!isset($_POST['phone']) || !isset($_POST['amount']) || !isset($_POST['beneficiary_name']) || !isset($_POST['beneficiary_phone'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

$beneficiary_phone = preg_replace('/[^0-9]/', '', $_POST['beneficiary_phone']);
$phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
$amount = floatval($_POST['amount']);
$beneficiary_name = trim($_POST['beneficiary_name']);

if (strlen($beneficiary_phone) !== 12 || substr($beneficiary_phone, 0, 3) !== '254' || $amount < 10) {
    echo json_encode(['success' => false, 'message' => 'Invalid withdrawal request.']);
    exit;
}

$pdo = new AutoConn();
$conn = $pdo->open();

try {
    $user_id = $_SESSION['id'] ?? 1;

    $stmt = $conn->prepare("SELECT SUM(broker_fee) AS total FROM transactions WHERE user_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    $available = $row['total'] ?? 0;

    if ($amount > $available) {
        echo json_encode(['success' => false, 'message' => 'Withdrawal amount exceeds available balance.']);
        exit;
    }

    function normalizeMpesaPhone($raw_phone) {
        $digits = preg_replace('/[^0-9]/', '', $raw_phone);
    
        if (preg_match('/^(?:254|\+254)(7|1)\d{8}$/', $digits)) {
            return '0' . substr($digits, -9); // → 0712345678 / 0112345678
        }
    
        if (preg_match('/^0(7|1)\d{8}$/', $digits)) {
            return $digits; // Already correct
        }
    
        return null; // Invalid format
    }

    $mpesa_account_number = normalizeMpesaPhone($beneficiary_phone);
if (!$mpesa_account_number) {
    echo json_encode(['success' => false, 'message' => 'Unsupported phone format for M-Pesa.']);
    exit;
}

    $reference = 'WD-' . uniqid();
    $secret_key = 'sk_live_7151dcc2790def66d1327a4b06ec9ed3efa4dcfb'; // Replace with your real Paystack secret key

    // Create transfer recipient
    $recipient_data = [
        'type' => 'mobile_money',
        'name' => $beneficiary_name,
        'account_number' => $mpesa_account_number,
        'bank_code' => 'MPESA',
        'currency' => 'KES'
    ];

    $ch = curl_init('https://api.paystack.co/transferrecipient');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $secret_key",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($recipient_data)
    ]);

    $recipient_response = curl_exec($ch);
    curl_close($ch);
    $recipient_result = json_decode($recipient_response, true);

    if (!isset($recipient_result['status']) || !$recipient_result['status']) {
        if (isset($recipient_result['message'])) {
            $transfer_error = $recipient_result['message'];
        } elseif (isset($recipient_result['meta']['nextStep'])) {
            $transfer_error = $recipient_result['meta']['nextStep'];
        } else {
            $transfer_error = $recipient_response;
        }
        echo json_encode(['success' => false, 'message' => 'Failed to create transfer recipient - ' . $transfer_error.' - ' .$beneficiary_phone, 'debug' => $recipient_result]);
        exit;
    }

    $recipient_code = $recipient_result['data']['recipient_code'];

    // Initiate transfer
    $transfer_data = [
        'source' => 'balance',
        'amount' => intval($amount * 100), // Paystack uses kobo
        'recipient' => $recipient_code,
        'reason' => 'Withdrawal - Nairobi Parking Module',
        'reference' => $reference,
        'currency' => 'KES'
    ];

    $ch = curl_init('https://api.paystack.co/transfer');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $secret_key",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($transfer_data)
    ]);

    $transfer_response = curl_exec($ch);
    curl_close($ch);

    $transfer_result = json_decode($transfer_response, true);

    if (!isset($transfer_result['status']) || !$transfer_result['status']) {
        $message = $transfer_result['message'] ?? 'Paystack transfer failed.';
        echo json_encode(['success' => false, 'message' => 'Paystack: ' . $message, 'debug' => $transfer_result]);
        exit;
    }

    $save = $conn->prepare("INSERT INTO withdrawals (user_id, amount, ref, status, created_at) VALUES (?, ?, ?, ?, NOW())");
    $save->execute([$user_id, $amount, $reference, 'pending']);

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
