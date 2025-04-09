<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Parking Payment - Nairobi</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="bg-light">

<div class="container mt-5">
  <div class="card shadow p-4">
    <h4 class="mb-3">Pay for Parking</h4>

    <form id="parkingForm">
      <div class="mb-3">
        <label for="number_plate" class="form-label">Number Plate</label>
        <input type="text" class="form-control" id="number_plate" name="number_plate" required placeholder="e.g. KDN840Q">
      </div>

      <div class="mb-3">
        <label for="zone" class="form-label">Select Zone</label>
        <select class="form-select" id="zone" name="zone" required></select>
      </div>

      <button type="submit" class="btn btn-primary">Check Status</button>
    </form>

    <div id="step1Msg" class="mt-3"></div>
  </div>
</div>

<script>
$(document).ready(function () {
  $.getJSON("ajax/get_zones.php", function(data) {
    let options = '<option disabled selected>Select Zone</option>';
    $.each(data, function(i, zone) {
      options += `<option value="${zone.id}">${zone.zone}</option>`;
    });
    $("#zone").html(options);
  });

  $("#parkingForm").on("submit", function(e) {
    e.preventDefault();
    const number_plate = $("#number_plate").val().toUpperCase().trim();
    const zone_id = $("#zone").val();
    $("#step1Msg").html('<div class="text-info">Checking parking status...</div>');

    // Proceed to step 2 with AJAX
    $.ajax({
      url: "step2.php",
      type: "POST",
      data: { number_plate, zone_id },
      success: function(response) {
        $("#step1Msg").html(`<div class="text-success">${response}</div>`);
      },
      error: function() {
        $("#step1Msg").html(`<div class="text-danger">Failed to check status. Try again.</div>`);
      }
    });
  });
});
</script>

</body>
</html>
