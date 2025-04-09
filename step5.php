<?php
require './includes/config.php'; // Include IP whitelisting from config.php
require './includes/functions.php'; // Include IP whitelisting from config.php

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

$user_id = $_SESSION['user_id'];
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

// 🔧 Additional values needed for tracking
$parking_zone = $_POST['parking_zone'] ?? 2;
$vehicle_type = $_POST['vehicle_type'] ?? 'S.WAGON';
$parking_duration = 'daily';

// Calculate payable amount: (total / 2) - 5 + broker_fee
$new_payable = (($total / 2) - 5) + $broker_fee;

try {
    $pdo = new AutoConn();
    $conn = $pdo->open();

    // Check if Flutterwave was already paid
    $stmt = $conn->prepare("SELECT flutterwave_verified, tx_ref, user_id FROM transactions WHERE transaction_no = ? LIMIT 1");
    $stmt->execute([$transaction_no]);
    $row = $stmt->fetch();
    if ($row && $row['flutterwave_verified'] == 1) {
        if($row['user_id'] != $user_id){
            echo 'This transaction has matured under a different attendant. Contact support for further assistance!';
        }else{
             echo '
            <form id="continueStep6" method="POST" action="step6.php">
                <input type="hidden" name="flutterwave_tx_ref" value="'.htmlspecialchars($row['tx_ref']).'">
                <input type="hidden" name="transaction_no" value="'.htmlspecialchars($transaction_no).'">
                <input type="hidden" name="client_phone" value="'.htmlspecialchars($client_phone).'">
                <input type="hidden" name="payable" value="'.htmlspecialchars($new_payable).'">
                <input type="hidden" name="number_plate" value="'.htmlspecialchars($number_plate).'">
                <button type="submit" class="btn btn-success">Continue to Step 6</button>
            </form>
            <script>
              document.getElementById("continueStep6").submit();
            </script>
        ';
       
        }  
         exit;
    }
    // Generate tx_ref
    $tx_ref = "NRS-APPM-" . uniqid();
 

    // Save or update transaction with extra fields
    $stmt = $conn->prepare("
        INSERT INTO transactions (
            transaction_no, number_plate, original_amount, penalty, total,
            broker_fee, client_phone, payable, tx_ref, amount, status,
            flutterwave_verified, zone_id, vehicle_type, parking_duration, user_id, created_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 5, 'initiated', 0, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            broker_fee = VALUES(broker_fee),
            client_phone = VALUES(client_phone),
            payable = VALUES(payable),
            tx_ref = VALUES(tx_ref),
            amount = 5,
            zone_id = VALUES(zone_id),
            vehicle_type = VALUES(vehicle_type),
            parking_duration = VALUES(parking_duration),
            user_id = VALUES(user_id),
            updated_at = NOW()
    ");
    $stmt->execute([
        $transaction_no, $number_plate, $original_amount, $penalty, $total,
        $broker_fee, $client_phone, $new_payable, $tx_ref,
        $parking_zone, $vehicle_type, $parking_duration, $user_id
    ]);

    $pdo->close();

} catch (Exception $e) {
    echo "Database error: " . $e->getMessage();
    exit;
}

// Redirect to Flutterwave
$public_key = "FLWPUBK_TEST-dfd2df1462b090aa264b1884370ca898-X";
$callback_url = "https://nrske.sbnke.com/verify_payment.php";
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
        <input type="hidden" name="customer[email]" value="parking@appm.nairobiservices.go.ke">
        <input type="hidden" name="customizations[title]" value="Nairobi Parking Module">
        <input type="hidden" name="customizations[description]" value="Pay for Nairobi parking service">
    </form>

    <script>
        document.getElementById("flwRedirectForm").submit();
    </script>
';
