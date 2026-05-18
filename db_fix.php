<?php
// db_fix.php — Автоматическое исправление структуры БД (Версия 10.0)
require_once __DIR__ . '/config.php';

echo "<style>body{font-family:sans-serif;line-height:1.6;padding:20px;background:#f4f7f6;} .log{background:#fff;padding:15px;border-radius:8px;box-shadow:0 2px 5px rgba(0,0,0,0.1);} .ok{color:green;font-weight:bold;} .err{color:red;font-weight:bold;} .warn{color:orange;font-weight:bold;}</style>";
echo "<h2>Исправление структуры БД EHPST (Версия 10.0)</h2>";
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

    // 3. Добавление колонок в parcels
    echo "<li>Проверка колонок в parcels... ";
    $s = $pdo->query("DESCRIBE parcels");
    $cols = $s->fetchAll(PDO::FETCH_COLUMN);

    $to_add = [
        'sender_address' => "TEXT DEFAULT NULL AFTER recipient_id",
        'sender_pvz' => "VARCHAR(100) DEFAULT NULL AFTER sender_address",
        'inventory' => "TEXT DEFAULT NULL AFTER declared_value",
        'is_paid' => "TINYINT(1) DEFAULT 0 AFTER inventory",
        'is_cod_paid' => "TINYINT(1) DEFAULT 0 AFTER is_paid",
        'is_cod_issued' => "TINYINT(1) DEFAULT 0 AFTER is_cod_paid",
        'is_refunded' => "TINYINT(1) DEFAULT 0 AFTER is_cod_issued",
        'pay_on_delivery' => "TINYINT(1) DEFAULT 0 AFTER is_refunded",
        'payment_method' => "VARCHAR(20) DEFAULT NULL AFTER pay_on_delivery",
        'receipt_no' => "VARCHAR(50) DEFAULT NULL AFTER payment_method",
        'is_return' => "TINYINT(1) DEFAULT 0 AFTER receipt_no",
        'pickup_point' => "VARCHAR(100) DEFAULT NULL AFTER is_return",
        'shelf' => "INT(11) DEFAULT NULL AFTER pickup_point",
        'is_deleted_by_sender' => "TINYINT(1) DEFAULT 0",
        'is_deleted_by_recipient' => "TINYINT(1) DEFAULT 0",
        'refund_code' => "VARCHAR(20) DEFAULT NULL",
        'cod_refund_issued' => "TINYINT(1) DEFAULT 0",
        'base_cost' => "DECIMAL(10,2) DEFAULT 0.00",
        'cod_fee' => "DECIMAL(10,2) DEFAULT 0.00",
        'dv_fee' => "DECIMAL(10,2) DEFAULT 0.00",
        'inv_fee' => "DECIMAL(10,2) DEFAULT 0.00",
        'stored_at' => "TIMESTAMP NULL DEFAULT NULL",
        'storage_notified' => "TINYINT(1) DEFAULT 0",
        'return_fee' => "DECIMAL(10,2) DEFAULT 0.00",
        'edit_fee' => "DECIMAL(10,2) DEFAULT 0.00",
        'loyalty_earned' => "DECIMAL(10,2) DEFAULT 0.00",
        'loyalty_spent' => "DECIMAL(10,2) DEFAULT 0.00"
    ];

    foreach($to_add as $col => $def) {
        if (!in_array($col, $cols)) {
            $pdo->exec("ALTER TABLE parcels ADD COLUMN $col $def");
            echo "<span class='ok'>$col+ </span>";
        }
    }
    echo "<span class='ok'>ГОТОВО</span></li>";

    // 4. Проверка created_at в таблицах
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

    // 5. Создание новых таблиц (Денежные переводы, Смены)
    $new_tables = [
        'parcel_followers' => "id int(11) NOT NULL AUTO_INCREMENT, user_id int(11) NOT NULL, parcel_id int(11) NOT NULL, PRIMARY KEY (id), UNIQUE KEY user_parcel (user_id,parcel_id)",
        'money_transfers' => "id int(11) NOT NULL AUTO_INCREMENT, transfer_code varchar(20) NOT NULL, secret_code varchar(10) DEFAULT NULL, sender_id int(11) NOT NULL, recipient_id int(11) NOT NULL, amount decimal(10,2) NOT NULL, fee decimal(10,2) DEFAULT '0.00', status enum('pending','paid','issued','refunded') DEFAULT 'pending', payment_method varchar(20) DEFAULT NULL, receipt_no varchar(50) DEFAULT NULL, created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY (transfer_code)",
        'shifts' => "id int(11) NOT NULL AUTO_INCREMENT, worker_id int(11) NOT NULL, opened_at timestamp NULL DEFAULT CURRENT_TIMESTAMP, closed_at timestamp NULL DEFAULT NULL, is_closed tinyint(1) DEFAULT '0', PRIMARY KEY (id)",
        'transactions' => "id int(11) NOT NULL AUTO_INCREMENT, shift_id int(11) NOT NULL, worker_id int(11) NOT NULL, type enum('income','expense') NOT NULL, category varchar(50) NOT NULL, amount decimal(10,2) NOT NULL, related_id int(11) DEFAULT NULL, created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id)",
        'loyalty_cards' => "id int(11) NOT NULL AUTO_INCREMENT, user_id int(11) NOT NULL, card_number varchar(20) NOT NULL, level enum('classic','bronze','silver','gold','premium') DEFAULT 'classic', balance decimal(10,2) DEFAULT '0.00', payments_count int(11) DEFAULT 0, PRIMARY KEY (id), UNIQUE KEY (user_id), UNIQUE KEY (card_number)",
        'loyalty_confirm_codes' => "id int(11) NOT NULL AUTO_INCREMENT, card_id int(11) NOT NULL, code varchar(10) NOT NULL, amount decimal(10,2) NOT NULL, expires_at timestamp NULL, PRIMARY KEY (id)",
        'loyalty_transactions' => "id int(11) NOT NULL AUTO_INCREMENT, card_id int(11) NOT NULL, amount decimal(10,2) NOT NULL, type enum('earn','spend') NOT NULL, expires_at timestamp NULL, created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id)"
    ];

    foreach($new_tables as $tname => $tdef) {
        echo "<li>Таблица $tname... ";
        $pdo->exec("CREATE TABLE IF NOT EXISTS `$tname` ($tdef) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        if ($tname === 'money_transfers') {
            $s = $pdo->query("DESCRIBE money_transfers");
            $mt_cols = $s->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('issued_at', $mt_cols)) {
                $pdo->exec("ALTER TABLE money_transfers ADD COLUMN issued_at TIMESTAMP NULL DEFAULT NULL");
                echo "<span class='ok'>issued_at+ </span>";
            }
            if (!in_array('secret_code', $mt_cols)) {
                $pdo->exec("ALTER TABLE money_transfers ADD COLUMN secret_code VARCHAR(10) DEFAULT NULL AFTER transfer_code");
                echo "<span class='ok'>secret_code+ </span>";
            }
            if (!in_array('refund_code', $mt_cols)) {
                $pdo->exec("ALTER TABLE money_transfers ADD COLUMN refund_code VARCHAR(20) DEFAULT NULL AFTER status");
                echo "<span class='ok'>refund_code+ </span>";
            }

            // Расширяем enum статуса если нужно
            try {
                $pdo->exec("ALTER TABLE money_transfers MODIFY COLUMN status ENUM('pending','paid','issued','refunded','refund_pending') DEFAULT 'pending'");
                echo "<span class='ok'>status_enum_updated </span>";
            } catch(Exception $e) {}
        }

        if ($tname === 'loyalty_cards') {
            $s = $pdo->query("DESCRIBE loyalty_cards");
            $lc_cols = $s->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('payments_count', $lc_cols)) {
                $pdo->exec("ALTER TABLE loyalty_cards ADD COLUMN payments_count INT(11) DEFAULT 0 AFTER balance");
                echo "<span class='ok'>payments_count+ </span>";
            }
        }

        echo "<span class='ok'>OK</span></li>";
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<li>Проверки внешних ключей включены.</li>";

    echo "<h3 class='ok'>БАЗА ДАННЫХ ПРИВЕДЕНА В ПОРЯДОК!</h3>";

} catch (Exception $e) {
    echo "<h3 class='err'>ОШИБКА: " . $e->getMessage() . "</h3>";
}

echo "</div>";
echo "<hr><a href='dashboard.php' style='display:inline-block;padding:10px 20px;background:#4361ee;color:#fff;text-decoration:none;border-radius:5px;'>Вернуться в Дашборд</a>";
