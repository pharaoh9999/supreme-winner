<?php
require './includes/config.php';
require './includes/functions.php';
require './includes/conn.php';

$pdo = new AutoConn();
$conn = $pdo->open();

$user_id = 1; // TODO: Replace this with logged-in user ID or broker ID session

// Fetch total earnings for the user
$stmt = $conn->prepare("SELECT SUM(broker_fee) as total_earnings FROM transactions WHERE client_phone = ? AND flutterwave_verified = 1");
$stmt->execute(["254700000000"]); // Replace with dynamic phone/user lookup
$earnings = $stmt->fetch();
$total_earnings = $earnings['total_earnings'] ?? 0;

$pdo->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Profile Dashboard - Nairobi Parking</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="bg-light">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">Nairobi Parking</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link" href="index.php">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link active" href="profile.php">Profile Dashboard</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- Dashboard Content -->
<div class="container mt-5">
  <div class="text-center mb-4">
    <h2 class="fw-bold">👤 My Profile Dashboard</h2>
    <p class="text-muted">Manage your earnings, track vehicle payments, and withdraw commissions.</p>
  </div>

  <div class="row g-4">
    <div class="col-md-6">
      <div class="card shadow-sm p-3">
        <h5 class="text-primary">💰 Earnings Overview</h5>
        <p>Balance: <strong>KES <?php echo number_format($total_earnings, 2); ?></strong></p>
        <button class="btn btn-outline-primary w-100">Withdraw Funds</button>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card shadow-sm p-3">
        <h5 class="text-primary">🚗 Vehicles You've Paid For</h5>
        <ul class="list-group" id="paidVehicles">
          <li class="list-group-item">Loading...</li>
        </ul>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card shadow-sm p-3">
        <h5 class="text-primary">⚠️ Incomplete Parking Processes</h5>
        <ul class="list-group" id="pendingVehicles">
          <li class="list-group-item">Loading...</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<script>
// Placeholder for upcoming AJAX logic
</script>

</body>
</html>
