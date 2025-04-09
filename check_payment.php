<?php
require './includes/config.php'; // Include IP whitelisting from config.php
require './includes/function.php'; // Include IP whitelisting from config.php

if (!isset($_GET['plate'])) {
    echo 'error';
    exit;
}

$plate = strtoupper(trim($_GET['plate']));

$url = "https://nairobiservices.go.ke/api/parking/parking/confirmed/$plate";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Cookie: csrftoken=e5leC8rQ9Nzggc04qM4vBdW36LnQTqfM'
]);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (isset($data['paid']) && $data['paid'] === true) {
    echo 'paid';
} else {
    echo 'notpaid';
}
