<?php
require_once 'includes/conn.php';

if (!isset($_GET['tx_ref']) || !isset($_GET['status'])) {
    die("Invalid Flutterwave redirect. Missing data.");
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

// Look up transaction info from DB using tx_ref
try {
    $pdo = new AutoConn();
    $conn = $pdo->open();

    $stmt = $conn->prepare("SELECT transaction_no, client_phone FROM transactions WHERE tx_ref = ? LIMIT 1");
    $stmt->execute([$tx_ref]);
    $row = $stmt->fetch();

    $pdo->close();

    if (!$row) {
        echo "<div style='margin: 2rem; font-family: sans-serif; color: red;'>Transaction not found in system.</div>";
        exit;
    }

    $transaction_no = $row['transaction_no'];
    $client_phone = $row['client_phone'];

} catch (Exception $e) {
    echo "DB error: " . $e->getMessage();
    exit;
}
?>

<form id="continueStep6" method="POST" action="step6.php">
    <input type="hidden" name="flutterwave_tx_ref" value="<?php echo htmlspecialchars($tx_ref); ?>">
    <input type="hidden" name="transaction_no" value="<?php echo htmlspecialchars($transaction_no); ?>">
    <input type="hidden" name="client_phone" value="<?php echo htmlspecialchars($client_phone); ?>">
    <button type="submit" class="btn btn-success">Click here if not redirected</button>
</form>

<script>
  document.getElementById("continueStep6").submit();
</script>
