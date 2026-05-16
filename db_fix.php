<?php
// db_fix.php — Автоматическое исправление структуры БД (Усиленная версия)
require_once __DIR__ . '/config.php';

echo "<h2>Усиленное исправление структуры БД EHPST</h2>";

function fixIndex($pdo, $table, $column) {
    echo "<li>Обработка индекса $table.$column... ";
    try {
        // Получаем список индексов
        $stmt = $pdo->query("SHOW INDEX FROM $table");
        $indexes = $stmt->fetchAll();

        foreach ($indexes as $idx) {
            if ($idx['Column_name'] === $column) {
                $keyName = $idx['Key_name'];
                // Если индекс уникальный или мы просто хотим его пересоздать как обычный
                echo " (Удаление $keyName) ";
                $pdo->exec("ALTER TABLE $table DROP INDEX `$keyName` ");
            }
        }

        // Создаем обычный индекс
        $pdo->exec("ALTER TABLE $table ADD INDEX `$column` (`$column`) ");
        echo "<span style='color:green'>ГОТОВО</span>";
    } catch (Exception $e) {
        echo "<span style='color:red'>ОШИБКА: " . htmlspecialchars($e->getMessage()) . "</span>";
    }
    echo "</li>";
}

try {
    // 1. Исправление parcel_codes
    fixIndex($pdo, 'parcel_codes', 'parcel_id');

    // 2. Проверка и добавление created_at в parcel_status
    echo "<li>Проверка created_at в parcel_status... ";
    $s = $pdo->query("DESCRIBE parcel_status");
    $cols = $s->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('created_at', $cols)) {
        $pdo->exec("ALTER TABLE parcel_status ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");
        echo "<span style='color:green'>ДОБАВЛЕНО</span></li>";
    } else {
        echo "<span style='color:blue'>УЖЕ ЕСТЬ</span></li>";
    }

    // 3. Проверка и добавление created_at в notifications
    echo "<li>Проверка created_at в notifications... ";
    $s = $pdo->query("DESCRIBE notifications");
    $cols = $s->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('created_at', $cols)) {
        $pdo->exec("ALTER TABLE notifications ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");
        echo "<span style='color:green'>ДОБАВЛЕНО</span></li>";
    } else {
        echo "<span style='color:blue'>УЖЕ ЕСТЬ</span></li>";
    }

    echo "<h3 style='color:green'>База данных успешно обновлена!</h3>";

} catch (PDOException $e) {
    echo "<h3 style='color:red'>КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage() . "</h3>";
}

echo "<hr><a href='dashboard.php'>Вернуться в Дашборд</a>";
