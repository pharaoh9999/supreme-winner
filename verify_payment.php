<?php
if (!isset($_GET['tx_ref'])) {
    die("Missing tx_ref.");
}

$tx_ref = $_GET['tx_ref'];
// You can store it in session or forward to step6.php via POST automatically
?>

<form id="continueStep6" method="POST" action="step6.php">
    <input type="hidden" name="flutterwave_tx_ref" value="<?php echo htmlspecialchars($tx_ref); ?>">
    <!-- You can also auto-fetch client_phone & transaction_no from DB here if needed -->
    <button type="submit" class="btn btn-success">Click here if not redirected</button>
</form>

<script>
  document.getElementById("continueStep6").submit();
</script>
