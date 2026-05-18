<?php
// functions_finance.php — Вспомогательные функции для финансов и смен

/**
 * Получить текущую открытую смену работника
 */
function getOpenShift($worker_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM shifts WHERE worker_id = :wid AND is_closed = 0 LIMIT 1");
    $stmt->execute(['wid' => $worker_id]);
    return $stmt->fetch();
}

/**
 * Логировать транзакцию
 */
function logTransaction($shift_id, $worker_id, $type, $category, $amount, $related_id = null) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO transactions (shift_id, worker_id, type, category, amount, related_id)
                           VALUES (:sid, :wid, :type, :cat, :amt, :rel)");
    $stmt->execute([
        'sid' => $shift_id,
        'wid' => $worker_id,
        'type' => $type,
        'cat' => $category,
        'amt' => $amount,
        'rel' => $related_id
    ]);
}

/**
 * Получить статистику по смене
 */
function getShiftStats($shift_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT type, category, SUM(amount) as total FROM transactions WHERE shift_id = :sid GROUP BY type, category");
    $stmt->execute(['sid' => $shift_id]);
    return $stmt->fetchAll();
}

/**
 * Получить детальный список транзакций по смене
 */
function getShiftTransactions($shift_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE shift_id = :sid ORDER BY id ASC");
    $stmt->execute(['sid' => $shift_id]);
    return $stmt->fetchAll();
}

/**
 * Получить карту лояльности по номеру
 */
function getLoyaltyCard($number) {
    global $pdo;
    // Очищаем номер от лишних символов
    $number = preg_replace('/[^0-9]/', '', $number);
    $stmt = $pdo->prepare("SELECT * FROM loyalty_cards WHERE card_number = :n LIMIT 1");
    $stmt->execute(['n' => $number]);
    return $stmt->fetch();
}

/**
 * Процент кэшбэка по уровню
 */
function getLoyaltyPercent($level) {
    $lvls = [
        'classic' => 0.03,
        'bronze'  => 0.06,
        'silver'  => 0.08,
        'gold'    => 0.10,
        'premium' => 0.20
    ];
    return $lvls[$level] ?? 0.03;
}

/**
 * Пересчитать уровень карты на основе количества оплат
 */
function updateLoyaltyLevel($card_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT payments_count, level, user_id FROM loyalty_cards WHERE id = :id");
    $stmt->execute(['id' => $card_id]);
    $card = $stmt->fetch();
    if (!$card) return;

    $count = (int)$card['payments_count'];
    $new_level = 'classic';
    if ($count >= 100) $new_level = 'premium';
    elseif ($count >= 40) $new_level = 'gold';
    elseif ($count >= 20) $new_level = 'bronze';

    if ($new_level !== $card['level']) {
        $pdo->prepare("UPDATE loyalty_cards SET level = :lvl WHERE id = :cid")
            ->execute(['lvl' => $new_level, 'cid' => $card_id]);
        notifyUser($card['user_id'], "Поздравляем! Ваш уровень лояльности повышен до " . strtoupper($new_level), 'success', true);
    }
}
