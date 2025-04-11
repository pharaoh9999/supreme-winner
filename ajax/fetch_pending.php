<?php
require '../includes/conn.php';

header('Content-Type: application/json');

$pdo = new AutoConn();
$conn = $pdo->open();

$client_phone = "254700000000"; // Replace with session-based phone check if needed

$stmt = $conn->prepare("SELECT number_plate, status FROM transactions WHERE client_phone = ? AND (flutterwave_verified = 0 OR status != 'completed') ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$client_phone]);
$results = $stmt->fetchAll();

echo json_encode($results);
$pdo->close();
