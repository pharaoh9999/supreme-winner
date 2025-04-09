<?php
$url = 'https://nairobiservices.go.ke/api/parking/parking/zone/';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Cookie: csrftoken=e5leC8rQ9Nzggc04qM4vBdW36LnQTqfM'
]);
$response = curl_exec($ch);
curl_close($ch);
header('Content-Type: application/json');
echo $response;
