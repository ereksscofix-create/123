<?php
// config.php
$host = 'localhost';
$db   = 'f1211429_ps';
$user = 'f1211429_ps';
$pass = 'Alex993399@@';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die("Ошибка подключения к базе данных: " . $e->getMessage());
}
?>
