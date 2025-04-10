<?php
session_start();

// Clear all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

if(isset($_GET['err'])){
    $err = $_GET['err'];

}else{
    $err = 'logout';
}

// Redirect to the login page
header("Location: login.php?err=".$err);
exit();
