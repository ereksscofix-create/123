<?php
require_once __DIR__ . '/config.php';

echo "<h2>Проверка базы данных EHPST</h2>";

try {
    $tables = ['users', 'parcels', 'parcel_status', 'notifications', 'parcel_codes'];

    foreach ($tables as $table) {
        $stmt = $pdo->query("DESCRIBE $table");
        $columns = $stmt->fetchAll();

        echo "<h3>Таблица: $table</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            foreach ($col as $val) echo "<td>$val</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    echo "<p style='color: green; font-weight: bold;'>Все таблицы найдены и доступны.</p>";

} catch (PDOException $e) {
    echo "<p style='color: red; font-weight: bold;'>ОШИБКА: " . $e->getMessage() . "</p>";
    echo "<p>Убедитесь, что вы загрузили <b>database.sql</b> через phpMyAdmin или другой инструмент.</p>";
}
?>
<hr>
<a href="dashboard.php">Перейти в дашборд</a>
