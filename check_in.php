<?php
$TokenVerificationExeception = true;
require 'vendor/autoload.php';

use Sonata\GoogleAuthenticator\GoogleAuthenticator;

include 'includes/config.php';

if (isset($_COOKIE['auth_token'])) {
    header("Location: login.php?err=check_in");
}
function login($username, $password)
{
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

    return $apiResponse;
}

function generate_device_hash()
{
    $components = [
        $_SERVER['HTTP_USER_AGENT'],
        $_SERVER['HTTP_ACCEPT_LANGUAGE'],
        gethostname(),
        $_SERVER['HTTP_ACCEPT_ENCODING']
    ];
    return hash('sha256', implode('|', $components));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['password']) && isset($_GET['username'])) {
    $otp = $_GET['auth'];
    $username = $_GET['username'];
    $password = $_GET['password'];
    // Step 1: Authenticate username and password with the API
    $apiResponse = login($username, $password);
    if (isset($apiResponse['error'])) {
        echo "
        <script>
        alert('" . $apiResponse['error'] . "');
        window.location.href = 'check_in.php'; 
        </script>
        ";
        exit;
    } elseif (!isset($apiResponse['token'])) {
        echo "
        <script>
        alert('An error occured. Try again later!');
        window.location.href = 'check_in.php'; 
        </script>
        ";
        exit;
    }

    $stmt = $conn->prepare("SELECT qr_url FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    // Verify the OTP
    if (empty($user['qr_url'])) {
        echo "
    <script>
    alert('Account has already been activated!');
    window.location.href = 'login.php'; 
    </script>
    ";
    } else {
        echo '
        <style>
        .modal-overlay {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.modal-box {
    background: white;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    width: 300px;
}

.modal-box img {
    margin-bottom: 15px;
}

.modal-box input {
    width: 90%;
    padding: 5px;
    margin-bottom: 10px;
}
        </style>
    <div id="customPrompt" style="display:none;">
    <div class="modal-overlay">
        <div class="modal-box">
            <img src="'.$user['qr_url'].'" alt="Prompt Image" width="150"/>
            <p>Enter Key Code:</p>
            <input type="text" id="userInput" placeholder="Enter key code here..."/>
            <button onclick="submitInput()">Submit</button>
            <button onclick="closePrompt()">Cancel</button>
        </div>
    </div>
</div>

    ';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['auth']) && isset($_GET['password']) && isset($_GET['username'])) {
    $otp = $_GET['auth'];
    $username = $_GET['username'];
    $password = $_GET['password'];
    // Step 1: Authenticate username and password with the API
    $apiResponse = login($username, $password);
    if (isset($apiResponse['error'])) {
        echo "
        <script>
        alert('" . $apiResponse['error'] . "');
        window.location.href = 'check_in.php'; 
        </script>
        ";
        exit;
    }

    $ga_secret = base64_decode($apiResponse['ga_secret']); // Decoding if the API encoded it

    // Initialize Google Authenticator
    $gAuth = new GoogleAuthenticator();
    $secret = $ga_secret; // Use the stored secret key

    $auth_token_obj = generate_device_hash();
    $stmt = $conn->prepare("UPDATE users SET auth_token =: auth_token WHERE username = :username");
    $stmt->execute(['auth_token' => $auth_token_obj]);
    $user = $stmt->fetch();

    // Verify the OTP
    if ($gAuth->checkCode($secret, $otp)) {
        setcookie('auth_token', $auth_token_obj, [
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

    <div id="customPrompt" style="display:none;">
        <div class="modal-overlay">
            <div class="modal-box">
                <img src="your-image.jpg" alt="Prompt Image" width="150" />
                <p>Enter Key Code:</p>
                <input type="text" id="userInput" placeholder="Enter key code here..." />
                <button onclick="submitInput()">Submit</button>
                <button onclick="closePrompt()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        var username = window.prompt("Enter Username:");
        var password = window.prompt("Enter Password:");
        var veto_key = window.prompt("Enter Key Code:");
        window.location.assign("./check_in.php?auth=" + veto_key + "&username=" + username + "&password=" + password);
    </script>
    <script>
        // Open the prompt
        function openPrompt() {
            document.getElementById('customPrompt').style.display = 'block';
        }

        // Close the prompt
        function closePrompt() {
            document.getElementById('customPrompt').style.display = 'none';
        }

        // Submit handler
        function submitInput() {
            let value = document.getElementById('userInput').value;
            closePrompt();
            alert("You entered: " + value); // Or handle as needed
        }

        // Example call
        openPrompt();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
</body>

</html>