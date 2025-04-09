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

// 🧠 FLUTTERWAVE VERIFY API
$secret_key = 'FLWSECK_TEST-baa9d3ab9bbcea7673ce70ff011e60a1-X'; // Replace with your live/test Flutterwave secret key
$encryptet_key = 'FLWSECK_TEST9fe5009f32ba'; // Replace with your live/test Flutterwave secret key
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
    echo "Flutterwave payment verification failed or amount mismatch.";
    exit;
}

// ✅ Flutterwave Payment Verified
try {
    $pdo = new AutoConn();
    $conn = $pdo->open();

    $stmt = $conn->prepare("SELECT * FROM transactions WHERE transaction_no = ? LIMIT 1");
    $stmt->execute([$transaction_no]);
    $data = $stmt->fetch();
    $pdo->close();

    if (!$data) {
        echo "Transaction not found.";
        exit;
    }

    $penalty = floatval($data['penalty']);
    $parking_zone = intval($data['zone_id'] ?? 2);
    $vehicle_type = 'S.WAGON';
    $parking_duration = 'daily';
    $amount = 5;
    $total = 5;

    // 1. Clear Penalty (if any)
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
        $penalty_resp = curl_exec($ch);
        curl_close($ch);
    }

    // 2. Reinitiate Pay API with 5 bob
    $pay_payload = [
        "number_plate" => $number_plate,
        "parking_duration" => $parking_duration,
        "parking_zone" => $parking_zone,
        "vehicle_type" => $vehicle_type,
        "amount" => $amount,
        "penalty" => 0,
        "total" => $total,
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

    $pay_result = json_decode($pay_response, true);
    if (!isset($pay_result['data']['transaction_no'])) {
        echo "KES 5 payment initiation failed.";
        exit;
    }

    // 3. Confirm parking paid (final status check)
    $confirm_url = "https://nairobiservices.go.ke/api/parking/parking/confirmed/$number_plate";
    $ch = curl_init($confirm_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Cookie: csrftoken=e5leC8rQ9Nzggc04qM4vBdW36LnQTqfM'
    ]);
    $confirm_response = curl_exec($ch);
    curl_close($ch);

    $confirm_data = json_decode($confirm_response, true);
    if (!isset($confirm_data['paid']) || $confirm_data['paid'] !== true) {
        echo "Parking not yet marked as paid. Please retry shortly.";
        exit;
    }

    echo "<div class='alert alert-success'>✅ Payment verified and parking has been paid.</div>";

    echo '<form id="step7Form" method="POST">
            <input type="hidden" name="transaction_no" value="' . $transaction_no . '">
            <input type="hidden" name="original_total" value="' . $data['total'] . '">
            <button type="submit" class="btn btn-primary mt-3">Finalize Transaction (Step 8)</button>
          </form>
          <div id="step7Msg" class="mt-3"></div>
          
          <script>
            $("#step7Form").on("submit", function(e) {
              e.preventDefault();
              const formData = $(this).serialize();
              $("#step7Msg").html("Finalizing...");
              $.ajax({
                url: "step7.php",
                method: "POST",
                data: formData,
                success: function(response) {
                  $("#step7Msg").html(response);
                },
                error: function() {
                  $("#step7Msg").html("Failed to finalize.");
                }
              });
          });
          </script>';
} catch (Exception $e) {
    echo "System error: " . $e->getMessage();
    exit;
}
