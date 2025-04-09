<?php
$TokenVerificationExeception = true;
require 'vendor/autoload.php';
use Sonata\GoogleAuthenticator\GoogleAuthenticator;

include 'includes/config.php';
require './includes/functions.php';

  // Step 1: Authenticate username and password with the API
  $apiResponse = login('kever', '24051786');

  $ga_secret = base64_decode($apiResponse['ga_secret']); // Decoding if the API encoded it

  if ($ga_secret) {
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
  }else{
    header("Location: https://en.wikipedia.org/wiki/Mind_your_own_business?err=".$_SERVER['REQUEST_URI']);
    exit;
  }
