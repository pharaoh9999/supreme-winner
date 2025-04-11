<?php
require '../includes/conn.php';

header('Content-Type: application/json');

$pdo = new AutoConn();
$conn = $pdo->open();

$client_phone = "254700000000"; // Replace with dynamic phone from session if needed

$stmt = $conn->prepare("SELECT number_plate, created_at FROM transactions WHERE client_phone = ? AND status = 'completed' ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$client_phone]);
$results = $stmt->fetchAll();

echo json_encode($results);
$pdo->close();
