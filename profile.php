<?php
require './includes/config.php';
require './includes/functions.php';
require './includes/conn.php';

$pdo = new AutoConn();
$conn = $pdo->open();

$client_phone = "254700000000"; // Replace with session-bound value

// Earnings
$stmt = $conn->prepare("SELECT SUM(broker_fee) as total_earnings FROM transactions WHERE client_phone = ? AND flutterwave_verified = 1");
$stmt->execute([$client_phone]);
$total_earnings = $stmt->fetchColumn() ?? 0;

// Withdrawals
$stmt = $conn->prepare("SELECT amount, ref, status, created_at FROM withdrawals WHERE client_phone = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$client_phone]);
$withdrawals = $stmt->fetchAll();

// Chart data - paid vs pending
$stmt = $conn->prepare("SELECT status, COUNT(*) AS count FROM transactions WHERE client_phone = ? GROUP BY status");
$stmt->execute([$client_phone]);
$statuses = $stmt->fetchAll();
$chart_labels = [];
$chart_data = [];
foreach ($statuses as $row) {
  $chart_labels[] = ucfirst($row['status']);
  $chart_data[] = (int)$row['count'];
}

// Chart data - earnings trend
$stmt = $conn->prepare("SELECT DATE(created_at) as date, SUM(broker_fee) as total FROM transactions WHERE client_phone = ? AND flutterwave_verified = 1 GROUP BY DATE(created_at) ORDER BY date ASC LIMIT 10");
$stmt->execute([$client_phone]);
$trend_labels = [];
$trend_data = [];
foreach ($stmt->fetchAll() as $row) {
  $trend_labels[] = $row['date'];
  $trend_data[] = (float)$row['total'];
}

$pdo->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Profile Dashboard - Nairobi Parking</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        <p>Balance: <strong id="earningsAmount">KES <?php echo number_format($total_earnings, 2); ?></strong></p>
        <form id="withdrawForm" method="POST" action="withdraw.php">
          <input type="hidden" name="phone" value="<?php echo htmlspecialchars($client_phone); ?>">
          <input type="hidden" name="amount" value="<?php echo $total_earnings; ?>">
          <button type="submit" class="btn btn-outline-primary w-100" <?php echo ($total_earnings < 10) ? 'disabled' : ''; ?>>Withdraw Funds</button>
        </form>
        <small class="text-muted">* Minimum withdrawal is KES 10</small>
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

    <div class="col-md-6">
      <div class="card shadow-sm p-3">
        <h5 class="text-primary">📅 Recent Withdrawals</h5>
        <ul class="list-group">
          <?php if ($withdrawals): ?>
            <?php foreach ($withdrawals as $row): ?>
              <li class="list-group-item d-flex justify-content-between">
                <span>KES <?php echo number_format($row['amount'], 2); ?> <small>(<?php echo $row['status']; ?>)</small></span>
                <small><?php echo date('M d, H:i', strtotime($row['created_at'])); ?></small>
              </li>
            <?php endforeach; ?>
          <?php else: ?>
            <li class="list-group-item">No withdrawals found.</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>

  <div class="text-center my-5">
    <h4 class="fw-bold">📊 Visual Summary</h4>
  </div>

  <div class="row g-4 mb-5">
    <div class="col-md-6">
      <div class="card p-3 shadow-sm">
        <h5 class="text-center">Earnings Trend</h5>
        <canvas id="trendChart"></canvas>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card p-3 shadow-sm">
        <h5 class="text-center">Paid vs Pending</h5>
        <canvas id="statusChart"></canvas>
      </div>
    </div>
  </div>

  <div class="card p-3 shadow-sm">
    <h5 class="text-center">Recent Withdrawals</h5>
    <canvas id="withdrawalsChart"></canvas>
  </div>
</div>

<script>
$(document).ready(function () {
  $.getJSON('ajax/fetch_paid.php', function(data) {
    const list = $('#paidVehicles').empty();
    if (data.length) {
      data.forEach(v => list.append(`<li class="list-group-item">${v.number_plate} (${v.created_at})</li>`));
    } else {
      list.append('<li class="list-group-item">None yet.</li>');
    }
  });

  $.getJSON('ajax/fetch_pending.php', function(data) {
    const list = $('#pendingVehicles').empty();
    if (data.length) {
      data.forEach(v => list.append(`<li class="list-group-item">${v.number_plate} (${v.status})</li>`));
    } else {
      list.append('<li class="list-group-item">None pending.</li>');
    }
  });
});

new Chart(document.getElementById('trendChart'), {
  type: 'line',
  data: {
    labels: <?php echo json_encode($trend_labels); ?>,
    datasets: [{
      label: 'Earnings (KES)',
      data: <?php echo json_encode($trend_data); ?>,
      tension: 0.3,
      fill: true,
      borderWidth: 2
    }]
  }
});

new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: <?php echo json_encode($chart_labels); ?>,
    datasets: [{
      data: <?php echo json_encode($chart_data); ?>,
      borderWidth: 1
    }]
  }
});

new Chart(document.getElementById('withdrawalsChart'), {
  type: 'bar',
  data: {
    labels: <?php echo json_encode(array_column($withdrawals, 'ref')); ?>,
    datasets: [{
      label: 'KES',
      data: <?php echo json_encode(array_column($withdrawals, 'amount')); ?>,
      backgroundColor: '#0d6efd'
    }]
  }
});
</script>

</body>
</html>