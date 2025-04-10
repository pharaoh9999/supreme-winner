<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$TokenVerificationExeception = true;
require 'vendor/autoload.php';
use Sonata\GoogleAuthenticator\GoogleAuthenticator;

include 'includes/config.php';
require './includes/functions.php';


$sess = $_SESSION['testa'] = 'session test a';

echo $sess;