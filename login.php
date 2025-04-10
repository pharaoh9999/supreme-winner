<?php
$TokenVerificationExeception = true;
require './includes/config.php';
require './includes/functions.php';

use Sonata\GoogleAuthenticator\GoogleAuthenticator;
use Sonata\GoogleAuthenticator\GoogleQrUrl;
if(isset($_GET['err'])){
    $err = $_GET['err'];

}else{
    $err = '';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Parking Processing Module <?php echo $err; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <style>
        /* Cybersecurity theme styles */
        body {
            background-color: rgb(156, 175, 194);
            color:rgb(52, 56, 6);
            font-family: Arial, sans-serif;
        }

        h2,
        h4 {
            color:rgb(1, 63, 27);
            /* Neon green for cybersecurity look */
        }

        .container {
            background-color: rgb(169, 190, 214);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0px 0px 15px rgba(0, 200, 83, 0.2);
        }

        .btn-primary {
            background-color: #009688;
            border: none;
            color: #ffffff;
        }

        .btn-primary:hover,
        .btn-success:hover {
            background-color: #004d40;
        }

        .btn-success {
            background-color: #00c853;
            border: none;
        }

        .form-control {
            background-color: rgb(189, 203, 235);
            color:rgb(52, 56, 6);
            border: 1px solid #00c853;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <div class="text-center mb-3">
            <img src="./elements/logo.96513770.png" alt="Nairobi Parking" class="img-fluid" style="max-height: 120px;">
        </div>
        <h2 class="text-center">Admin Parking Processing Module</h2>
        <form id="loginForm" method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>
        <div id="otpSection" class="mt-3" style="display: none;">
            <h4 class="text-center">Enter OTP</h4>
            <input type="text" id="otp" class="form-control" placeholder="Enter OTP" required>
            <button id="verifyOtp" class="btn btn-success btn-block mt-2">Verify OTP</button>
        </div>
        <div id="gaSetup" class="mt-3" style="display: none;">
            <h4 class="text-center">Set Up Google Authenticator</h4>
            <p class="text-center">Scan the QR code below with your Google Authenticator app:</p>
            <div class="text-center">
                <img id="gaQrCode" src="" alt="QR Code" class="img-fluid">
            </div>
            <p class="mt-2 text-center">Then, enter the code below:</p>
            <input type="number" id="setupOtp" class="form-control" placeholder="Enter OTP from Google Authenticator" required>
            <button id="setupVerifyOtp" class="btn btn-success btn-block mt-2">Verify OTP</button>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#loginForm').on('submit', function(e) {
                e.preventDefault();
                const username = $('#username').val();
                const password = $('#password').val();

                $.ajax({
                    url: 'authenticate.php',
                    type: 'POST',
                    data: {
                        username: username,
                        password: password
                    },
                    success: function(response) {
                        const data = JSON.parse(response);
                        if (data.token) {
                            alert('Login successful. Proceeding to 2FA setup or OTP verification.');
                            if (data.ga_setup_required) {
                                $('#loginForm').hide();
                                $('#gaQrCode').attr('src', data.qr_code);
                                $('#gaSetup').show();
                            } else {
                                $('#loginForm').hide();
                                $('#otpSection').show();
                            }
                        } else {
                            alert('Login failed: ' + data.error);
                        }
                    }
                });
            });
        });
        $(document).ready(function() {
            $('#loginForm').on('submit', function(e) {
                e.preventDefault();
                const username = $('#username').val();
                const password = $('#password').val();

                $.ajax({
                    url: 'authenticate.php',
                    type: 'POST',
                    data: {
                        username: username,
                        password: password
                    },
                    success: function(response) {
                        console.log(response);
                        const data = JSON.parse(response);
                        if (data.token) {
                            alert('Login successful. Proceeding to 2FA setup or OTP verification.');
                            if (data.ga_setup_required) {
                                $('#loginForm').hide();
                                $('#gaQrCode').attr('src', data.qr_code);
                                $('#gaSetup').show();
                            } else {
                                $('#loginForm').hide();
                                $('#otpSection').show();
                            }
                        } else {
                            alert('Login failed: ' + data.error);
                        }
                    }
                });
            });

            // OTP verification section
            $('#verifyOtp').on('click', function() {
                const otp = $('#otp').val();

                $.ajax({
                    url: 'verify_otp.php',
                    type: 'POST',
                    data: {
                        otp: otp
                    },
                    success: function(response) {
                        const data = JSON.parse(response);
                        if (data.success) {
                            alert('OTP verified. Login complete.');
                            window.location.href = 'index.php'; // Redirect to the secure dashboard
                        } else {
                            alert('Invalid OTP. Please try again.');
                        }
                    }
                });
            });
        });
    </script>
</body>

</html>