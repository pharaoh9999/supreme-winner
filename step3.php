<?php
require './includes/config.php'; // Include IP whitelisting from config.php
require './includes/function.php'; // Include IP whitelisting from config.php


if (
    !isset($_POST['number_plate']) ||
    !isset($_POST['zone_id']) ||
    !isset($_POST['vehicle_type']) ||
    !isset($_POST['parking_duration'])
) {
    echo "Missing input data.";
    exit;
}

$number_plate = strtoupper(trim($_POST['number_plate']));
$parking_zone = intval($_POST['zone_id']);
$vehicle_type = strtolower(trim($_POST['vehicle_type']));
$parking_duration = strtolower(trim($_POST['parking_duration']));

$vehicle_type_dt = json_decode(httpPost('https://nairobiservices.go.ke/api/parking/parking/park_details',['parking_duration'=>'daily','number_plate'=>$number_plate,'parking_zone'=>'1']),true);

if(isset($vehicle_type_dt['vehicle_type'])){
    $vehicle_type = strtolower(trim($vehicle_type_dt['vehicle_type']));
}else{
    $vehicle_type = strtolower(trim($_POST['vehicle_type']));
}

$url = 'https://nairobiservices.go.ke/api/parking/parking/payment_details';

$data = [
    "number_plate" => $number_plate,
    "parking_duration" => $parking_duration,
    "parking_zone" => $parking_zone,
    "vehicle_type" => $vehicle_type
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

if (!isset($result['total'])) {
    echo "Failed to fetch payment details. Try again later.";
    exit;
}

$amount = $result['amount'];
$penalty = $result['penalty'];
$total = $result['total'];
?>

<div class="alert alert-secondary">
  <strong>Payment Summary</strong><br>
  Base Amount: <strong>KES <?php echo number_format($amount); ?></strong><br>
  Penalty: <strong>KES <?php echo number_format($penalty); ?></strong><br>
  <hr>
  <strong>Total: KES <?php echo number_format($total); ?></strong>
</div>

<form id="step4Form">
  <input type="hidden" name="number_plate" value="<?php echo htmlspecialchars($number_plate); ?>">
  <input type="hidden" name="parking_zone" value="<?php echo $parking_zone; ?>">
  <input type="hidden" name="vehicle_type" value="<?php echo htmlspecialchars($vehicle_type); ?>">
  <input type="hidden" name="parking_duration" value="<?php echo htmlspecialchars($parking_duration); ?>">
  <input type="hidden" name="amount" value="<?php echo $amount; ?>">
  <input type="hidden" name="penalty" value="<?php echo $penalty; ?>">
  <input type="hidden" name="total" value="<?php echo $total; ?>">

  <button type="submit" class="btn btn-primary mt-3">Initiate Transaction (Step 4)</button>
</form>

<div id="step4Msg" class="mt-3"></div>

<script>
  $("#step4Form").on("submit", function(e) {
    e.preventDefault();
    const formData = $(this).serialize();

    $("#step4Msg").html('<div class="text-info">Initiating transaction...</div>');

    $.ajax({
      url: "step4.php",
      method: "POST",
      data: formData,
      success: function(response) {
        $("#step4Msg").html(response);
      },
      error: function() {
        $("#step4Msg").html('<div class="text-danger">Failed to initiate transaction.</div>');
      }
    });
  });
</script>
