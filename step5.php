<?php
require './includes/config.php';
require './includes/functions.php';
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

$parking_zone = $_POST['parking_zone'] ?? 2;
$vehicle_type = $_POST['vehicle_type'] ?? 'S.WAGON';
$parking_duration = 'daily';
$random_number = mt_rand(5, 20);

$new_payable = (($total / 2) - $random_number) + $broker_fee;

try {
    $pdo = new AutoConn();
    $conn = $pdo->open();

    $stmt = $conn->prepare("SELECT flutterwave_verified, tx_ref, user_id FROM transactions WHERE transaction_no = ? LIMIT 1");
    $stmt->execute([$transaction_no]);
    $row = $stmt->fetch();

    if ($row && $row['flutterwave_verified'] == 1) {
        if ($row['user_id'] != $user_id) {
            echo 'This transaction has matured under a different attendant. Contact support for further assistance!';
        } else {
            echo '
            <form id="continueStep6" method="POST" action="step6.php">
                <input type="hidden" name="flutterwave_tx_ref" value="'.htmlspecialchars($row['tx_ref']).'">
                <input type="hidden" name="transaction_no" value="'.htmlspecialchars($transaction_no).'">
                <input type="hidden" name="client_phone" value="'.htmlspecialchars($client_phone).'">
                <input type="hidden" name="payable" value="'.htmlspecialchars($new_payable).'">
                <input type="hidden" name="number_plate" value="'.htmlspecialchars($number_plate).'">
                <input type="hidden" name="random_number" value="'.htmlspecialchars($random_number).'">
                <button type="submit" class="btn btn-success">Continue to Step 6</button>
            </form>
            <script>
              document.getElementById("continueStep6").submit();
            </script>';
        }
        exit;
    }

    $tx_ref = "NRS-APPM-" . uniqid();

    $stmt = $conn->prepare("
        INSERT INTO transactions (
            transaction_no, number_plate, original_amount, penalty, total,
            broker_fee, client_phone, payable, tx_ref, amount, status,
            flutterwave_verified, zone_id, vehicle_type, parking_duration, user_id, created_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, {$random_number}, 'initiated', 0, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            broker_fee = VALUES(broker_fee),
            client_phone = VALUES(client_phone),
            payable = VALUES(payable),
            tx_ref = VALUES(tx_ref),
            amount = {$random_number},
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

$paystack_public_key = 'pk_test_6bc7894bc9912cba9710a79b5f61d2a73b98136a';
$callback_url = "https://nairobi.autos/verify_payment.php";

echo '
<form id="paystackForm">
  <script src="https://js.paystack.co/v1/inline.js"></script>
  <script>
    var handler = PaystackPop.setup({
      key: "' . $paystack_public_key . '",
      email: "parking@appm.nairobiservices.go.ke",
      amount: ' . ((int)($new_payable * 100)) . ',
      currency: "KES",
      reference: "' . $tx_ref . '",
      callback: function(response) {
        window.location.href = "' . $callback_url . '?status=success&tx_ref=' . $tx_ref . '&transaction_id=" + response.reference;
      },
      onClose: function() {
        alert("Payment window closed.");
      }
    });
    handler.openIframe();
  </script>
</form>';
