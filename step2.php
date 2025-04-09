<?php
require './includes/config.php'; // Include IP whitelisting from config.php
require './includes/functions.php'; // Include IP whitelisting from config.php

if (!isset($_POST['number_plate']) || !isset($_POST['zone_id'])) {
    echo "Missing data.";
    exit;
}

$number_plate = strtoupper(trim($_POST['number_plate']));
$zone_id = intval($_POST['zone_id']);

$url = "https://nairobiservices.go.ke/api/parking/parking/confirmed/$number_plate";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Cookie: csrftoken=e5leC8rQ9Nzggc04qM4vBdW36LnQTqfM'
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if (!isset($result['paid'])) {
    echo "Unable to fetch parking status. Try again later.";
    exit;
}

if ($result['paid'] === true) {
    echo "Parking for vehicle <strong>$number_plate</strong> is already paid.";
    exit;
}



// Parking not paid, proceed to step 3 UI
?>
<div class="alert alert-warning mt-3">Parking not paid. Proceeding to payment details...</div>

<form id="step3Form" class="mt-3">
  <input type="hidden" name="number_plate" value="<?php echo htmlspecialchars($number_plate); ?>">
  <input type="hidden" name="zone_id" value="<?php echo $zone_id; ?>">

  <div class="mb-3">
    <label for="vehicle_type" class="form-label">Vehicle Type</label>
    <select class="form-select" name="vehicle_type" id="vehicle_type" required>
      <option value="" selected disabled>Select vehicle type</option>
      <option value="saloon">Saloon</option>
      <option value="s.wagon">Station Wagon</option>
      <option value="pickup">Pickup</option>
      <option value="truck">Truck</option>
    </select>
  </div>

  <div class="mb-3">
    <label for="parking_duration" class="form-label">Parking Duration</label>
    <select class="form-select" name="parking_duration" id="parking_duration" required>
      <option value="daily" selected>Daily</option>
      <option value="monthly">Monthly</option>
    </select>
  </div>

  <button type="submit" class="btn btn-success">Generate Payment Details</button>
</form>

<div id="step3Msg" class="mt-3"></div>

<script>
  $("#step3Form").on("submit", function(e) {
    e.preventDefault();
    const formData = $(this).serialize();

    $("#step3Msg").html('<div class="text-info">Fetching payment details...</div>');

    $.ajax({
      url: "step3.php",
      method: "POST",
      data: formData,
      success: function(response) {
        $("#step3Msg").html(response);
      },
      error: function() {
        $("#step3Msg").html('<div class="text-danger">Failed to fetch payment details.</div>');
      }
    });
  });
</script>
