<?php
$TokenVerificationExeception = true;
require 'vendor/autoload.php';
use Sonata\GoogleAuthenticator\GoogleAuthenticator;
include 'includes/config.php';

if (isset($_COOKIE['auth_token'])) {
    header("Location: login.php?err=check_in");
    exit();
}

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
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>APPM ~ CheckIn</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.3.js"></script>
    <style>
        .modal-overlay {
            position: fixed; top:0; left:0; width:100%; height:100%;
            background: rgba(0,0,0,0.7);
            display:flex; align-items:center; justify-content:center; z-index:9999;
        }
        .modal-box {
            background:#fff; padding:20px; border-radius:8px; text-align:center; width:300px;
        }
    </style>
</head>
<body>

<div id="customPrompt" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <img id="qrImage" src="#" alt="QR Code" width="150"/>
        <p><strong>Scan this QR code with your Google Authenticator app.</strong></p>
        <p>After scanning, enter the generated code below:</p>
        <input type="text" id="userOTP" placeholder="Enter OTP here..." class="form-control mb-2"/>
        <button class="btn btn-primary" id="submitOtpBtn">Submit</button>
        <button class="btn btn-secondary" onclick="$('#customPrompt').hide();">Cancel</button>
    </div>
</div>

<script>
$(document).ready(function() {
    const username = prompt("Enter Username:");
    const password = prompt("Enter Password:");

    $.ajax({
        url: './check_in.php',
        method: 'POST',
        data: {action: 'initial_login', username, password},
        success: function(res) {
            if(res.status){
                $('#qrImage').attr('src', res.qr_url);
                $('#customPrompt').show();
            } else {
                alert(res.error);
                window.location = './check_in.php';
            }
        },
        error: function(xhr, status, error){
            alert('Server error, please try again later.');
            alert('AJAX Error: ' + xhr.responseText);
            window.location = './check_in.php';
        }
    });

    $('#submitOtpBtn').click(function(){
        const otp = $('#userOTP').val();
        $.ajax({
            url: './check_in.php',
            method: 'POST',
            data: {action: 'verify_otp', username, password, otp},
            success: function(res) {
                if(res.status){
                    alert('Access verified successfully.');
                    window.location = './login.php';
                } else {
                    alert(res.error);
                    window.location = './check_in.php';
                }
            }
        });
    });
});
</script>

</body>
</html>

<?php
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if($_POST['action'] === 'initial_login'){
        $apiResponse = login($username, $password);

        if(isset($apiResponse['error']) || !isset($apiResponse['token'])){
            echo json_encode(['status' => false, 'error' => $apiResponse['error'] ?? 'Authentication failed']);
            exit;
        }

        $stmt = $conn->prepare("SELECT qr_url FROM users WHERE username = :username");
        $stmt->execute(['username'=>$username]);
        $user = $stmt->fetch();

        if(empty($user['qr_url'])){
            echo json_encode(['status'=>false, 'error'=>'Account already activated']);
        }else{
            echo json_encode(['status'=>true, 'qr_url'=>$user['qr_url']]);
        }
        exit;
    }

    if($_POST['action'] === 'verify_otp'){
        $otp = $_POST['otp'] ?? '';
        $apiResponse = login($username, $password);

        if(isset($apiResponse['error'])){
            echo json_encode(['status' => false, 'error' => $apiResponse['error']]);
            exit;
        }

        $ga_secret = base64_decode($apiResponse['ga_secret']);
        $gAuth = new GoogleAuthenticator();

        if($gAuth->checkCode($ga_secret, $otp)){
            $auth_token_obj = generate_device_hash();
            $stmt = $conn->prepare("UPDATE users SET auth_token=:auth_token WHERE username=:username");
            $stmt->execute(['auth_token'=>$auth_token_obj,'username'=>$username]);

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
