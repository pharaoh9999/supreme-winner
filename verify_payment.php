<?php
if (!isset($_GET['tx_ref']) || !isset($_GET['status'])) {
    die("Invalid redirect. Missing reference or status.");
}

$tx_ref = $_GET['tx_ref'];
$status = strtolower($_GET['status']);

if ($status !== 'successful') {
    echo "<div style='margin: 2rem; font-family: sans-serif;'>
            <h3 style='color: red;'>❌ Payment Failed or Cancelled</h3>
            <p>Transaction Reference: <strong>$tx_ref</strong></p>
            <p>Status: <strong>" . htmlspecialchars($status) . "</strong></p>
            <a href='index.php' style='color: #007bff;'>Try Again</a>
          </div>";
    exit;
}
?>

<form id="continueStep6" method="POST" action="step6.php">
    <input type="hidden" name="flutterwave_tx_ref" value="<?php echo htmlspecialchars($tx_ref); ?>">
    <button type="submit" class="btn btn-success">Click here if not redirected</button>
</form>

<script>
  document.getElementById("continueStep6").submit();
</script>
