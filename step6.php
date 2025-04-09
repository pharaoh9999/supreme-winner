<?php
require_once 'includes/conn.php';

if (
    !isset($_POST['transaction_no']) ||
    !isset($_POST['client_phone']) ||
    !isset($_POST['payable']) ||
    !isset($_POST['number_plate']) ||
    !isset($_POST['flutterwave_tx_ref'])
) {
    echo "Missing data.";
    exit;
}

$transaction_no = $_POST['transaction_no'];
$client_phone = $_POST['client_phone'];
$number_plate = strtoupper(trim($_POST['number_plate']));
$payable = floatval($_POST['payable']);
$flutterwave_tx_ref = trim($_POST['flutterwave_tx_ref']);

try {
    $pdo = new AutoConn();
    $conn = $pdo->open();

    $stmt = $conn->prepare("SELECT * FROM transactions WHERE transaction_no = ? LIMIT 1");
    $stmt->execute([$transaction_no]);
    $data = $stmt->fetch();

    if (!$data) {
        echo "Transaction not found.";
        exit;
    }

    $flutterwave_verified = $data['flutterwave_verified'];
    $penalty = floatval($data['penalty']);
    $parking_zone = intval($data['zone_id']);
    $vehicle_type = $data['vehicle_type'];
    $parking_duration = $data['parking_duration'];
    $original_total = floatval($data['total']);

    // If Flutterwave not verified, do it now
    if (!$flutterwave_verified) {
        $secret_key = 'FLWSECK_TEST-baa9d3ab9bbcea7673ce70ff011e60a1-X'; // Replace with real key
        $verify_url = "https://api.flutterwave.com/v3/transactions/verify_by_reference?tx_ref=$flutterwave_tx_ref";

        $ch = curl_init($verify_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $secret_key"
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        if (
            !isset($result['status']) ||
            $result['status'] !== 'success' ||
            $result['data']['amount'] < $payable ||
            $result['data']['currency'] !== 'KES'
        ) {
            echo "<div class='alert alert-danger'>Flutterwave payment verification failed or amount mismatch.</div>";
            exit;
        }

        // Update DB to mark verified
        $update = $conn->prepare("UPDATE transactions SET flutterwave_verified = 1 WHERE transaction_no = ?");
        $update->execute([$transaction_no]);
    }

    // Clear penalty if needed
    if ($penalty > 0) {
        $boundary = "----WebKitFormBoundary7MA4YWxkTrZu0gW";
        $penalty_payload = "--$boundary\r\n";
        $penalty_payload .= "Content-Disposition: form-data; name=\"number_plate\"\r\n\r\n";
        $penalty_payload .= "$number_plate\r\n";
        $penalty_payload .= "--$boundary--\r\n";

        $ch = curl_init("https://nairobiservices.go.ke/api/parking/parking/penalty/clear");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: multipart/form-data; boundary=$boundary",
            'Cookie: csrftoken=e5leC8rQ9Nzggc04qM4vBdW36LnQTqfM'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $penalty_payload);
        curl_exec($ch);
        curl_close($ch);
    }

    // Trigger Pay API with KES 5
    $pay_payload = [
        "number_plate" => $number_plate,
        "parking_duration" => $parking_duration,
        "parking_zone" => $parking_zone,
        "vehicle_type" => $vehicle_type,
        "amount" => 5,
        "penalty" => 0,
        "total" => 5,
        "mobile_number" => $client_phone,
        "parkingType" => $parking_duration
    ];

    $ch = curl_init("https://nairobiservices.go.ke/api/parking/parking/daily/pay");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Cookie: csrftoken=e5leC8rQ9Nzggc04qM4vBdW36LnQTqfM'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($pay_payload));
    $pay_response = curl_exec($ch);
    curl_close($ch);

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>System error: " . $e->getMessage() . "</div>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Waiting for Payment</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="bg-light">

<div class="container mt-5">
  <div class="card shadow p-4 text-center">
    <h4 class="mb-3 text-success">✅ STK Push Sent</h4>
    <p>Check your phone <strong><?php echo $client_phone; ?></strong> and pay KES 5.</p>
    <div id="statusBox" class="mt-3">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Waiting...</span>
      </div>
      <p class="mt-2">Waiting for payment confirmation...</p>
    </div>

    <div id="retryBox" class="d-none mt-4">
      <div class="alert alert-warning">Payment not detected after 2 minutes.</div>
      <form method="POST" action="step6.php">
        <input type="hidden" name="flutterwave_tx_ref" value="<?php echo htmlspecialchars($flutterwave_tx_ref); ?>">
        <input type="hidden" name="transaction_no" value="<?php echo htmlspecialchars($transaction_no); ?>">
        <input type="hidden" name="client_phone" value="<?php echo htmlspecialchars($client_phone); ?>">
        <input type="hidden" name="payable" value="<?php echo htmlspecialchars($payable); ?>">
        <input type="hidden" name="number_plate" value="<?php echo htmlspecialchars($number_plate); ?>">
        <button type="submit" class="btn btn-dark">🔁 Retry STK Push</button>
      </form>
    </div>

    <div id="finalBox" class="mt-4 d-none"></div>
  </div>
</div>

<script>
let attempts = 0;
const maxAttempts = 24; // 2 minutes (24 x 5s)

function checkStatus() {
  attempts++;
  $.get("check_payment.php?plate=<?php echo $number_plate; ?>", function(data) {
    if (data === 'paid') {
      $('#statusBox').html("<div class='alert alert-success'>✅ Payment confirmed! Finalizing transaction...</div>");
      finalizeTransaction();
    } else if (attempts >= maxAttempts) {
      $('#statusBox').hide();
      $('#retryBox').removeClass('d-none');
    } else {
      setTimeout(checkStatus, 5000);
    }
  });
}

function finalizeTransaction() {
  $.post("finalize_transaction.php", {
    transaction_no: "<?php echo $transaction_no; ?>",
    final_amount: "<?php echo $original_total; ?>"
  }, function(response) {
    $('#finalBox').html(response).removeClass('d-none');
  });
}

$(document).ready(function () {
  setTimeout(checkStatus, 5000);
});
</script>

</body>
</html>
