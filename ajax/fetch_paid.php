<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '../includes/conn.php';

header('Content-Type: application/json');

$pdo = new AutoConn();
$conn = $pdo->open();
if(isset($_SESSION['id'])){
$user_raw_id = $_SESSION['id']; // Replace with dynamic phone from session if needed

}else{
    $user_raw_id = '666'; // Replace with dynamic phone from session if needed

}

$stmt = $conn->prepare("SELECT number_plate, created_at FROM transactions WHERE user_id = ? AND status = 'completed' ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$user_raw_id]);
$results = $stmt->fetchAll();

echo json_encode($results);
$pdo->close();
