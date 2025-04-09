<?php
session_start();
require 'vendor/autoload.php';
use Sonata\GoogleAuthenticator\GoogleAuthenticator;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = $_POST['otp'];

    // Initialize Google Authenticator
    $gAuth = new GoogleAuthenticator();
    $secret = $_SESSION['ga_secret']; // Use the stored secret key

    // Verify the OTP
    if ($gAuth->checkCode($secret, $otp)) {
        $_SESSION['authenticated'] = true; // Mark the user as fully authenticated
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
}
?>
