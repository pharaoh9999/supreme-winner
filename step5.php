<?php
require_once 'includes/conn.php';

if (
    !isset($_POST['transaction_no']) ||
    !isset($_POST['original_amount']) ||
    !isset($_POST['penalty']) ||
    !isset($_POST['total']) ||
    !isset($_POST['broker_fee']) ||
    !isset($_POST['client_phone']) ||
    !isset($_POST['number_plate'])
) {
    echo "Missing input data.";
    exit;
}

$transaction_no = $_POST['transaction_no'];
$original_amount = floatval($_POST['original_amount']);
$penalty = floatval($_POST['penalty']);
$total = floatval($_POST['total']);
$broker_fee = floatval($_POST['broker_fee']);
$client_phone = preg_replace('/[^0-9]/', '', $_POST['client_phone']);
$number_plate = strtoupper(trim($_POST['number_plate']));

if (strlen($client_phone) != 12 || substr($client_phone, 0, 3) !== "254") {
    echo "Invalid phone number.";
    exit;
}

// Calculate new payable amount: (total / 2) - 5 + broker_fee
$new_payable = ($total / 2) - 5 + $broker_fee;

// Generate a unique Flutterwave tx_ref
$tx_ref = "KEVER-" . uniqid();

// Update transaction to KES 5 in Nairobi API
$update_payload = [
    "transaction_no" => $transaction_no,
    "amount" => "5",
    "bank_ref" => null,
    "transaction_mobile_no" => $client_phone,
    "mobile_number" => $client_phone,
    "name" => ""
];

$ch = curl_init("https://nairobiservices.go.ke/api/parking/parking/transaction/update");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Cookie: csrftoken=e5leC8rQ9Nzggc04qM4vBdW36LnQTqfM'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($update_payload));
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (!isset($result['status']) || $result['status'] != 200) {
    echo "Failed to update transaction to 5 bob.";
    exit;
}

// Save transaction to DB (or update if it already exists)
try {
    $pdo = new AutoConn();
    $conn = $pdo->open();

    // Insert or update with tx_ref and payable
    $stmt = $conn->prepare("
        INSERT INTO transactions (transaction_no, number_plate, original_amount, penalty, total, broker_fee, client_phone, payable, tx_ref, amount, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 5, 'initiated', NOW())
        ON DUPLICATE KEY UPDATE
            broker_fee = VALUES(broker_fee),
            client_phone = VALUES(client_phone),
            payable = VALUES(payable),
            tx_ref = VALUES(tx_ref),
            amount = 5,
            status = 'initiated',
            updated_at = NOW()
    ");
    $stmt->execute([$transaction_no, $number_plate, $original_amount, $penalty, $total, $broker_fee, $client_phone, $new_payable, $tx_ref]);

    $pdo->close();
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage();
    exit;
}

// FLUTTERWAVE PAY REDIRECT
$public_key = "FLWPUBK_TEST-dfd2df1462b090aa264b1884370ca898-X"; // Replace with your public key
$callback_url = "https://yourdomain.com/verify_payment.php"; // Replace with your real endpoint

$payment_url = "https://checkout.flutterwave.com/v3/hosted/pay";

echo '
    <form id="flwRedirectForm" method="POST" action="'.$payment_url.'">
        <input type="hidden" name="public_key" value="'.$public_key.'">
        <input type="hidden" name="tx_ref" value="'.$tx_ref.'">
        <input type="hidden" name="amount" value="'.$new_payable.'">
        <input type="hidden" name="currency" value="KES">
        <input type="hidden" name="redirect_url" value="'.$callback_url.'">
        <input type="hidden" name="payment_options" value="mobilemoney">
        <input type="hidden" name="customer[name]" value="Parking Client">
        <input type="hidden" name="customer[phonenumber]" value="'.$client_phone.'">
        <input type="hidden" name="customer[email]" value="parking@kever.io">
        <input type="hidden" name="customizations[title]" value="Nairobi Parking Module">
        <input type="hidden" name="customizations[description]" value="Pay for Nairobi parking service">
    </form>

    <script>
        document.getElementById("flwRedirectForm").submit();
    </script>
';
