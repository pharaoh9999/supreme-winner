<?php
$TokenVerificationExeception = true;
require 'vendor/autoload.php';

use Sonata\GoogleAuthenticator\GoogleAuthenticator;

include 'includes/config.php';
require './includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $otp = $_POST['auth'];
    // Step 1: Authenticate username and password with the API
    $apiResponse = login('kever', '24051786');

    $ga_secret = base64_decode($apiResponse['ga_secret']); // Decoding if the API encoded it

    // Initialize Google Authenticator
    $gAuth = new GoogleAuthenticator();
    $secret = $ga_secret; // Use the stored secret key

    // Verify the OTP
    if ($gAuth->checkCode($secret, $otp)) {
        setcookie('auth_token', '5ca2ae839d5ebef8447a', [
            'expires' => time() + 86400 * 30,
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        echo "
    <script>
    alert('Access verified successfully.');
    window.location.href = 'login.php'; 
    </script>
    ";
    } else {
        echo "
    <script>
    alert('Invalid Code.');
    window.location.href = 'check_in.php'; 
    </script>
    ";
    }
}

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>APPM ~ CheckIn</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
    <script src="https://code.jquery.com/jquery-3.6.3.js" integrity="sha256-nQLuAZGRRcILA+6dMBOvcRh5Pe310sBpanc6+QBmyVM=" crossorigin="anonymous"></script>

    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.css">

    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.js"></script>

</head>

<body>

<script>
    var veto_key = window.prompt("Enter Key:");
    window.location.assign("./check_in.php?auth=" + veto_key);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
</body>

</html>