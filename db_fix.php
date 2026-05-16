<?php
// db_fix.php — Автоматическое исправление структуры БД (Версия 4.0 - Финальная)
require_once __DIR__ . '/config.php';

echo "<style>body{font-family:sans-serif;line-height:1.6;padding:20px;background:#f4f7f6;} .log{background:#fff;padding:15px;border-radius:8px;box-shadow:0 2px 5px rgba(0,0,0,0.1);} .ok{color:green;font-weight:bold;} .err{color:red;font-weight:bold;} .warn{color:orange;font-weight:bold;}</style>";
echo "<h2>Исправление структуры БД EHPST (Версия 4.0)</h2>";
echo "<div class='log'>";

try {
    // 1. Отключаем проверки внешних ключей
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "<li>Проверки внешних ключей отключены.</li>";

    // 2. Исправление parcel_codes
    echo "<li><b>Обработка таблицы parcel_codes:</b><ul>";

    // Пытаемся удалить внешний ключ, если он мешает
    try {
        $pdo->exec("ALTER TABLE parcel_codes DROP FOREIGN KEY `parcel_codes_ibfk_1` ");
        echo "<li>Внешний ключ `parcel_codes_ibfk_1` временно удален.</li>";
    } catch (Exception $e) {
        echo "<li>Внешний ключ не найден или уже удален.</li>";
    }

    // Получаем список индексов
    $stmt = $pdo->query("SHOW INDEX FROM parcel_codes");
    $indexes = $stmt->fetchAll();

    foreach ($indexes as $idx) {
        $keyName = $idx['Key_name'];
        if ($keyName !== 'PRIMARY') {
            try {
                $pdo->exec("ALTER TABLE parcel_codes DROP INDEX `$keyName` ");
                echo "<li>Индекс `$keyName` удален.</li>";
            } catch (Exception $e) {
                echo "<li>Не удалось удалить индекс `$keyName`.</li>";
            }
        }
    }

    // Создаем правильные индексы
    $pdo->exec("ALTER TABLE parcel_codes ADD INDEX `idx_parcel_id` (`parcel_id`) ");
    echo "<li>Создан стандартный индекс idx_parcel_id.</li>";

    // Возвращаем внешний ключ
    try {
        $pdo->exec("ALTER TABLE parcel_codes ADD CONSTRAINT `parcel_codes_ibfk_1` FOREIGN KEY (`parcel_id`) REFERENCES `parcels` (`id`) ON DELETE CASCADE");
        echo "<li>Внешний ключ `parcel_codes_ibfk_1` восстановлен.</li>";
    } catch (Exception $e) {
        echo "<li class='warn'>Не удалось восстановить внешний ключ (возможно, он уже существует с другим именем).</li>";
    }

    echo "</ul></li>";

    // 3. Проверка created_at в таблицах
    $tables = ['parcel_status', 'notifications', 'parcel_codes'];
    foreach ($tables as $table) {
        echo "<li>Проверка created_at в $table: ";
        $s = $pdo->query("DESCRIBE $table");
        $cols = $s->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('created_at', $cols)) {
            $pdo->exec("ALTER TABLE $table ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP");
            echo "<span class='ok'>ДОБАВЛЕНО</span>";
        } else {
            echo "<span class='ok'>ЕСТЬ</span>";
        }
        echo "</li>";
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<li>Проверки внешних ключей включены.</li>";

    echo "<h3 class='ok'>БАЗА ДАННЫХ ПРИВЕДЕНА В ПОРЯДОК!</h3>";

} catch (Exception $e) {
    echo "<h3 class='err'>ОШИБКА: " . $e->getMessage() . "</h3>";
}

echo "</div>";
echo "<hr><a href='dashboard.php' style='display:inline-block;padding:10px 20px;background:#4361ee;color:#fff;text-decoration:none;border-radius:5px;'>Вернуться в Дашборд</a>";
