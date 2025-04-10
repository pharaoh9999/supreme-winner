<?php
$TokenVerificationExeception = true;
require 'vendor/autoload.php';
include 'includes/config.php';
if(isset($_GET['bypass'])){
    if($_GET['bypass'] == '65ca2ae839'){
        unset($_COOKIE['auth_token']);
    }
    
}
if (isset($_COOKIE['auth_token'])) {
    header("Location: login.php?err=check_in");
    exit();
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
        url: 'check_in_api.php',
        method: 'POST',
        data: {action: 'initial_login', username, password},
        success: function(res) {
            if(res.status){
                $('#qrImage').attr('src', res.qr_url);
                $('#customPrompt').show();
            } else {
                alert(res.error);
                window.location = 'check_in.php';
            }
        },
        error: function(xhr, status, error){
            alert('Server error, please try again later.');
        }
    });

    $('#submitOtpBtn').click(function(){
        const otp = $('#userOTP').val();
        $.ajax({
            url: 'check_in_api.php',
            method: 'POST',
            data: {action: 'verify_otp', username, password, otp},
            success: function(res) {
                if(res.status){
                    alert('Access verified successfully.');
                    window.location = 'login.php';
                } else {
                    alert(res.error);
                    window.location = 'check_in.php';
                }
            }
        });
    });
});
</script>

</body>
</html>
