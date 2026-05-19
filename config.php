<?php
// config.php
// ВНИМАНИЕ: Замените данные подключения на ваши реальные данные.
// Для безопасности рекомендуется использовать переменные окружения.
$host = 'localhost';
$db   = 'f1211429_ps';
$user = 'f1211429_ps';
$pass = 'Alex993399@@'; // Пароль от базы данных
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
     // В режиме разработки можно вывести ошибку, но для безопасности лучше оставить заглушку
     die("Ошибка подключения к базе данных. Проверьте настройки в config.php.");
}

// ГЛОБАЛЬНЫЕ НАСТРОЙКИ ОРГАНИЗАЦИИ
define('ORG_INVITE_CODE', 'EHPST-2024-WORK'); // Код для регистрации сотрудников
define('SYSTEM_VERSION', '5.5.1-MASTER');
?>
