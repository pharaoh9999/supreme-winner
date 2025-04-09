<?php
require_once 'includes/conn.php';
include './includes/functions.php';


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

    $nrs_dt = json_decode(httpPost('https://nairobiservices.go.ke/api/parking/parking/transaction', ['invoice_no' => $transaction_no]), true);

    if (isset($nrs_dt['data'])) {
        $bank_ref = $nrs_dt['data']['bank_ref'];
        $transaction_mobile_no = $nrs_dt['data']['transaction_mobile_no'];
        $bank_ref = $nrs_dt['data']['bank_ref'];
        $bank_ref = $nrs_dt['data']['bank_ref'];

        $url = 'https://nairobiservices.go.ke/api/parking/parking/transaction/update';
        $data = [
            "transaction_no" => $transaction_no,
            "amount" => $final_amount,
            "bank_ref" => $bank_ref,
            "transaction_mobile_no" => $transaction_mobile_no,
            "mobile_number" => null,
            "name" => ""
        ];
        $nrs_upt = json_decode(httpPost($url, $data), true);
        if (isset($nrs_upt['data'])) {
            echo "<div class='alert alert-success'>🎉 Parking successfully completed and updated.</div>";
        } else {
            log_system('finalize_transaction.php part B', json_encode($nrs_upt));
            echo "<div class='alert alert-danger'>Error updating transaction!</div>";
        }
    } else {
        if (isset($nrs_dt['error'])) {
            echo "<div class='alert alert-danger'>" . $nrs_dt['error'] . "</div>";
        } else {
            log_system('finalize_transaction.php part A', json_encode($nrs_dt));
        }
    }
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>DB error: " . $e->getMessage() . "</div>";
}
