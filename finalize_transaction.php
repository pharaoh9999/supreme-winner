<?php
require_once 'includes/conn.php';

if (!isset($_POST['transaction_no']) || !isset($_POST['final_amount'])) {
    echo "Missing data.";
    exit;
}

$transaction_no = $_POST['transaction_no'];
$final_amount = floatval($_POST['final_amount']);

try {
    $pdo = new AutoConn();
    $conn = $pdo->open();

    $update = $conn->prepare("UPDATE transactions SET amount = ?, status = 'completed', updated_at = NOW() WHERE transaction_no = ?");
    $update->execute([$final_amount, $transaction_no]);

    echo "<div class='alert alert-success'>🎉 Parking successfully completed and updated.</div>";
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>DB error: " . $e->getMessage() . "</div>";
}
