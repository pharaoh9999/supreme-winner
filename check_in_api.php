<?php
require 'vendor/autoload.php';
use Sonata\GoogleAuthenticator\GoogleAuthenticator;
include 'includes/config.php';
include 'includes/conn.php'; // Ensure DB connection

header('Content-Type: application/json');

function login($username, $password) {
    $ch = curl_init('https://kever.io/finder_10_auth.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['username' => $username, 'password' => $password],
        CURLOPT_COOKIE => "visitorId=973ad0dd0c565ca2ae839d5ebef8447a"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function generate_device_hash() {
    return hash('sha256', implode('|', [
        $_SERVER['HTTP_USER_AGENT'],
        $_SERVER['HTTP_ACCEPT_LANGUAGE'],
        gethostname(),
        $_SERVER['HTTP_ACCEPT_ENCODING']
    ]));
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if(empty($username) || empty($password)) {
        echo json_encode(['status' => false, 'error' => 'Please fill all fields.']);
        exit;
    }

    if($_POST['action'] === 'initial_login'){
        $apiResponse = login($username, $password);

        if(isset($apiResponse['error']) || !isset($apiResponse['token'])){
            echo json_encode(['status' => false, 'error' => $apiResponse['error'] ?? 'Authentication failed']);
            exit;
        }

        $stmt = $conn->prepare("SELECT qr_url,cookie_setup, role_id FROM users WHERE username = :username");
        $stmt->execute(['username'=>$username]);
        $user = $stmt->fetch();

        if($user['cookie_setup'] == 1 && (string)$user['role_id'] == '2'){
            echo json_encode(['status'=>false, 'error'=>'Account already activated']);
            exit;
        } else {
            echo json_encode(['status'=>true, 'qr_url'=>$user['qr_url']]);
        }
        exit;
    }

    if($_POST['action'] === 'verify_otp'){
        $otp = $_POST['otp'] ?? '';
        if(empty($otp)) {
            echo json_encode(['status' => false, 'error' => 'OTP is required']);
            exit;
        }

        $apiResponse = login($username, $password);

        if(isset($apiResponse['error'])){
            echo json_encode(['status' => false, 'error' => $apiResponse['error']]);
            exit;
        }

        $ga_secret = base64_decode($apiResponse['ga_secret']);
        $gAuth = new GoogleAuthenticator();

        if($gAuth->checkCode($ga_secret, $otp)){
            $auth_token_obj = generate_device_hash();
            $stmt = $conn->prepare("UPDATE users SET auth_token=:auth_token, cookie_setup=:cookie_setup WHERE username=:username");
            $stmt->execute(['auth_token'=>$auth_token_obj,'username'=>$username,'cookie_setup'=>'1']);

            setcookie('auth_token', $auth_token_obj, [
                'expires'=>time()+86400*30, 'secure'=>true, 'httponly'=>true, 'samesite'=>'Strict'
            ]);

            echo json_encode(['status'=>true]);
        } else {
            echo json_encode(['status'=>false,'error'=>'Invalid OTP']);
        }
        exit;
    }
}
