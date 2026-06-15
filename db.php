<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_NOTICE);
$host = 'localhost';
$db = 'f1211429_ps';
$user = 'f1211429_ps';
$pass = 'Alex993399@@';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: ".$e->getMessage());
}
?>