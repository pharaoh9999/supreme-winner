<?php
require './includes/config.php'; // Include IP whitelisting from config.php
require './includes/function.php'; // Include IP whitelisting from config.php

if (
    !isset($_POST['number_plate']) ||
    !isset($_POST['parking_zone']) ||
    !isset($_POST['vehicle_type']) ||
    !isset($_POST['parking_duration']) ||
    !isset($_POST['amount']) ||
    !isset($_POST['penalty']) ||
    !isset($_POST['total'])
) {
    echo "Missing required data.";
    exit;
}

$number_plate = strtoupper(trim($_POST['number_plate']));
$parking_zone = intval($_POST['parking_zone']);
$vehicle_type = strtoupper(trim($_POST['vehicle_type']));
$parking_duration = strtolower(trim($_POST['parking_duration']));
$amount = floatval($_POST['amount']);
$penalty = floatval($_POST['penalty']);
$total = floatval($_POST['total']);
$mobile_number = "254700000000";

$url = 'https://nairobiservices.go.ke/api/parking/parking/daily/pay';

$data = [
    "number_plate" => $number_plate,
    "parking_duration" => $parking_duration,
    "parking_zone" => $parking_zone,
    "vehicle_type" => $vehicle_type,
    "amount" => $amount,
    "penalty" => $penalty,
    "total" => $total,
    "mobile_number" => $mobile_number,
    "parkingType" => $parking_duration
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Cookie: csrftoken=e5leC8rQ9Nzggc04qM4vBdW36LnQTqfM'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (!isset($result['data']['transaction_no'])) {
    echo "Failed to initiate transaction. Try again.";
    exit;
}

$transaction_no = $result['data']['transaction_no'];
$returned_amount = $result['data']['amount'];
?>

<div class="alert alert-success">
  Transaction Initiated<br>
  <strong>Transaction No:</strong> <?php echo $transaction_no; ?><br>
  <strong>Amount:</strong> KES <?php echo number_format($returned_amount); ?>
</div>

<form id="step5Form" class="mt-4">
  <input type="hidden" name="transaction_no" value="<?php echo $transaction_no; ?>">
  <input type="hidden" name="original_amount" value="<?php echo $amount; ?>">
  <input type="hidden" name="penalty" value="<?php echo $penalty; ?>">
  <input type="hidden" name="total" value="<?php echo $total; ?>">
  <input type="hidden" name="number_plate" value="<?php echo htmlspecialchars($number_plate); ?>">

  <div class="mb-3">
    <label for="broker_fee" class="form-label">Broker Fee (KES)</label>
    <input type="number" class="form-control" name="broker_fee" id="broker_fee" min="0" required>
  </div>

  <div class="mb-3">
    <label for="client_phone" class="form-label">Client Phone Number</label>
    <input type="tel" class="form-control" name="client_phone" id="client_phone" placeholder="2547XXXXXXXX" required>
  </div>

  <button type="submit" class="btn btn-dark">Proceed to Step 6 (Update Transaction)</button>
</form>

<div id="step5Msg" class="mt-3"></div>

<script>
  $("#step5Form").on("submit", function(e) {
    e.preventDefault();
    const formData = $(this).serialize();

    $("#step5Msg").html('<div class="text-info">Processing broker facilitation...</div>');

    $.ajax({
      url: "step5.php",
      method: "POST",
      data: formData,
      success: function(response) {
        $("#step5Msg").html(response);
      },
      error: function() {
        $("#step5Msg").html('<div class="text-danger">Failed to process facilitation.</div>');
      }
    });
  });
</script>
