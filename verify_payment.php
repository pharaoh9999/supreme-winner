<?php
session_start();
require_once 'includes/conn.php';

if (!isset($_GET['tx_ref']) || !isset($_GET['status']) || !isset($_GET['transaction_id'])) {
    die("Invalid redirect. Missing data.");
}

$tx_ref = $_GET['tx_ref'];
$status = strtolower($_GET['status']);
$transaction_id = $_GET['transaction_id'];

if ($status !== 'success') {
    echo "<div style='margin: 2rem; font-family: sans-serif;'>
            <h3 style='color: red;'>❌ Payment Failed or Cancelled</h3>
            <p>Transaction Reference: <strong>$tx_ref</strong></p>
            <p>Status: <strong>" . htmlspecialchars($status) . "</strong></p>
            <a href='index.php' style='color: #007bff;'>Try Again</a>
          </div>";
    exit;
}

try {
    $pdo = new AutoConn();
    $conn = $pdo->open();

    $stmt = $conn->prepare("SELECT transaction_no, client_phone, payable, number_plate, amount FROM transactions WHERE tx_ref = ? LIMIT 1");
    $stmt->execute([$tx_ref]);
    $row = $stmt->fetch();

    $pdo->close();

    if (!$row) {
        echo "<div style='margin: 2rem; font-family: sans-serif; color: red;'>Transaction not found in system.</div>";
        exit;
    }

    $transaction_no = $row['transaction_no'];
    $client_phone = $row['client_phone'];
    $payable = $row['payable'];
    $number_plate = $row['number_plate'];
    $random_number = $row['amount'];

} catch (Exception $e) {
    echo "DB error: " . $e->getMessage();
    exit;
}
?>

<form id="continueStep6" method="POST" action="step6.php">
    <input type="hidden" name="flutterwave_tx_ref" value="<?php echo htmlspecialchars($tx_ref); ?>">
    <input type="hidden" name="paystack_transaction_id" value="<?php echo htmlspecialchars($transaction_id); ?>">
    <input type="hidden" name="transaction_no" value="<?php echo htmlspecialchars($transaction_no); ?>">
    <input type="hidden" name="client_phone" value="<?php echo htmlspecialchars($client_phone); ?>">
    <input type="hidden" name="payable" value="<?php echo htmlspecialchars($payable); ?>">
    <input type="hidden" name="random_number" value="<?php echo htmlspecialchars($random_number); ?>">
    <input type="hidden" name="number_plate" value="<?php echo htmlspecialchars($number_plate); ?>">
    <button type="submit" class="btn btn-success">Click here if not redirected</button>
</form>

<script>
  document.getElementById("continueStep6").submit();
</script>
