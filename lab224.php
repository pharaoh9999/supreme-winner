<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sess = $_SESSION['test'] = 'session test';

echo $sess;