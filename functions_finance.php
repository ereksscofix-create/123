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
 * Получить карту лояльности по номеру
 */
function getLoyaltyCard($number) {
    global $pdo;
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
