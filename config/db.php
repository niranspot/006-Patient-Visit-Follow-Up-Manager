<?php
$host = "localhost";
$dbname = "patient_manager";
$username = "root";
$password = "";

define('BASE_URL', 'http://localhost/newphp1/Php_Tasks/006_Patient_Visit_And_Follow-Up_Manager/');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>