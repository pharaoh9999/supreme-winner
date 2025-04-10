<?php
$TokenVerificationExeception = true;
require 'vendor/autoload.php';
use Sonata\GoogleAuthenticator\GoogleAuthenticator;

include 'includes/config.php';
require './includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Step 1: Authenticate username and password with the API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://kever.io/finder_10_auth.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'username' => $username,
        'password' => $password,
    ]);
    curl_setopt($ch, CURLOPT_COOKIE, "visitorId=973ad0dd0c565ca2ae839d5ebef8447a");

    $response = curl_exec($ch);
    $apiResponse = json_decode($response, true);
    curl_close($ch);

    if (isset($apiResponse['token'])) {
        // Store token and username in session
        $_SESSION['token'] = $apiResponse['token'];
        $_SESSION['username'] = $username;

        $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['user_id'] = $user['id'];


        // Fetch ga_secret from API responsesd
        $ga_secret = base64_decode($apiResponse['ga_secret']); // Decoding if the API encoded it
        
        // Step 2: Verify that ga_secret exists
        if ($ga_secret) {
            $_SESSION['ga_secret'] = $ga_secret; // Temporarily store in session for OTP verification
            echo json_encode(['token' => $apiResponse['token'], 'ga_setup_required' => false]);
        } else {
            echo json_encode(['error' => '2FA setup incomplete for this user.']);
        }
    } else {
        if(isset($apiResponse['error'])){
            echo json_encode(['error' => $apiResponse['error']]);
        }else{
            echo json_encode(['error' => 'An error occured and this process could not proceed!']);
        }
        
    }
}
