<?php
// auth/check.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Not logged in → redirect to login
if (!isset($_SESSION['user_id'])) {
    header('Location: ./auth/login.php');
    exit;
}

// Role-based access
// Pages only admin can access
$current_file = basename($_SERVER['PHP_SELF']);

// Pages only admin can access — just filenames
$admin_only_pages = [
    'summary.php',
    'monthly.php',
    'birthdays.php',
];

if (
    in_array($current_file, $admin_only_pages) &&
    $_SESSION['role'] !== 'admin'
) {
    header('Location: /NewPhp1/Php_Tasks/006_Patient_Visit_And_Follow-Up_Manager/auth/denied.php');
    exit;
}
