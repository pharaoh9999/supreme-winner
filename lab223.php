<?php
session_start();
require 'vendor/autoload.php';

use Sonata\GoogleAuthenticator\GoogleAuthenticator;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {


    // Initialize Google Authenticator
    $gAuth = new GoogleAuthenticator();
    $secret = 'VkJYRU9aQjdLTlk0REtWWg=='; // Use the stored secret key
    $secret = base64_decode($secret); // Use the stored secret key
    if (isset($_GET['otpja'])) {
        $otp = $_GET['otpja'];
    } else {
        echo 'No otp given';
    }
    // Verify the OTP
    if ($gAuth->checkCode($secret, $otp)) {
        $_SESSION['authenticated'] = true; // Mark the user as fully authenticated
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
}
