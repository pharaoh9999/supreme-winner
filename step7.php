<?php
require_once 'includes/conn.php';

if (!isset($_POST['transaction_no']) || !isset($_POST['original_total'])) {
    echo "Missing required data.";
    exit;
}

$transaction_no = $_POST['transaction_no'];
$original_total = floatval($_POST['original_total']);

try {
    $pdo = new AutoConn();
    $conn = $pdo->open();

    $stmt = $conn->prepare("SELECT * FROM transactions WHERE transaction_no = ? LIMIT 1");
    $stmt->execute([$transaction_no]);
    $data = $stmt->fetch();

    if (!$data) {
        echo "Transaction not found in database.";
        exit;
    }

    $client_phone = $data['client_phone'];

    // Update in your own DB
    $update = $conn->prepare("UPDATE transactions SET status = 'completed', updated_at = NOW(), amount = ? WHERE transaction_no = ?");
    $update->execute([$original_total, $transaction_no]);

    $pdo->close();

    // Optionally: send confirmation SMS
    $sms_message = "Parking for vehicle {$data['number_plate']} has been successfully paid. Total: KES " . number_format($original_total);
    $sms_url = "https://nairobiservices.go.ke/api/authentication/sms?mobile=$client_phone&message=" . urlencode($sms_message);

    $ch = curl_init($sms_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Cookie: csrftoken=e5leC8rQ9Nzggc04qM4vBdW36LnQTqfM'
    ]);
    $sms_response = curl_exec($ch);
    curl_close($ch);

    $sms_data = json_decode($sms_response, true);
    $sms_status = $sms_data['status'] == 200 ? 'SMS sent to user.' : 'SMS failed.';

    echo "<div class='alert alert-success'>
            ✅ Transaction finalized and updated to full amount.<br>
            📲 {$sms_status}
          </div>";

} catch (Exception $e) {
    echo "Error finalizing transaction: " . $e->getMessage();
    exit;
}
