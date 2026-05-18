<?php
/**
 * dashboard.php — УЛЬТИМАТИВНЫЙ РАБОЧИЙ ДАШБОРД EHPST
 * Оптимизирован под мобильные устройства и десктоп.
 */
ob_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/functions_finance.php';

// 1. ПРОВЕРКА ВХОДА
checkLogin();
$user = currentUser();
if (!$user) {
    session_unset(); session_destroy();
    header('Location: login.php'); exit;
}

$user_id = (int)$user['id'];
$role = $user['role'] ?? 'recipient';
$name = $user['name'] ?: ($user['login'] ?: 'Пользователь');

// 2. ПАРАМЕТРЫ
$filter = $_GET['filter'] ?? 'all';
$q = trim((string)($_GET['q'] ?? ''));
$page_title = "Дашборд — EHPST";

// 3. ЗАГРУЗКА ДАННЫХ
$unreadCount = 0;
$notifications = [];
$total_parcels = 0;
$total_revenue = 0.0;
$parcels = [];
$codes = [];
$loyalty_card = null;

try {
    // ПРОВЕРКА СРОКОВ ХРАНЕНИЯ (14 дней)
    if ($role === 'worker') {
        $stmt = $pdo->query("SELECT id, track_code, pickup_point FROM parcels
                             WHERE shelf IS NOT NULL AND stored_at IS NOT NULL
                             AND stored_at < DATE_SUB(NOW(), INTERVAL 14 DAY)
                             AND storage_notified = 0 AND is_return = 0");
        $expired = $stmt->fetchAll();
        foreach ($expired as $ex) {
            // Уведомляем работника
            $msg = "Срок хранения посылки {$ex['track_code']} истёк ({$ex['pickup_point']}). Необходимо подготовить возврат.";
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type)
                                   SELECT id, :msg, 'warning' FROM users WHERE role = 'worker'");
            $stmt->execute(['msg' => $msg]);

            // Авто-возврат (меняем статус и полку)
            $stmt = $pdo->prepare("UPDATE parcels SET storage_notified = 1, is_return = 1, shelf = 6 WHERE id = :id");
            $stmt->execute(['id' => $ex['id']]);

            $status_text = "Срок хранения истёк. Автоматический возврат на полку возврата (6) [" . date('d.m.Y H:i') . "]";
            $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
            $stmt->execute(['pid' => $ex['id'], 'txt' => $status_text]);
        }
    }

    // Уведомления: Сначала считаем ВСЕ непрочитанные
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
    $stmt->execute(['uid' => $user_id]);
    $unreadCount = (int)$stmt->fetchColumn();

    // Загружаем последние 15 уведомлений для списка
    $stmt = $pdo->prepare("SELECT id, message, is_read FROM notifications WHERE user_id = :uid ORDER BY id DESC LIMIT 15");
    $stmt->execute(['uid' => $user_id]);
    $notifications = $stmt->fetchAll();

    // Статистика (всегда видим реальные цифры)
    if ($role === 'worker') {
        $total_parcels = (int)$pdo->query("SELECT COUNT(*) FROM parcels")->fetchColumn();
        $total_revenue = (float)$pdo->query("SELECT IFNULL(SUM(cost),0) FROM parcels")->fetchColumn();
    } else {
        // Статистика: Используем уникальные плейсхолдеры для надежности
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM parcels WHERE sender_id = :sid_stats OR recipient_id = :rid_stats");
        $stmt->execute(['sid_stats' => $user_id, 'rid_stats' => $user_id]);
        $total_parcels = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT IFNULL(SUM(cost),0) FROM parcels WHERE sender_id = :sid_rev");
        $stmt->execute(['sid_rev' => $user_id]);
        $total_revenue = (float)$stmt->fetchColumn();
    }

    // ПОИСК И СПИСОК
    $params = [];
    $where = [];

    if ($role !== 'worker') {
        // ВАЖНО: Видим свои (отправил/получил) И те, на которые подписались. Прячем удаленные.
        $where[] = "((p.sender_id = :uid_sender AND p.is_deleted_by_sender = 0)
                     OR (p.recipient_id = :uid_recipient AND p.is_deleted_by_recipient = 0)
                     OR EXISTS(SELECT 1 FROM parcel_followers WHERE user_id = :uid_follow AND parcel_id = p.id))";
        $params['uid_sender'] = $user_id;
        $params['uid_recipient'] = $user_id;
        $params['uid_follow'] = $user_id;
    }

    if ($filter === 'in_transit') {
        $where[] = "EXISTS (SELECT 1 FROM parcel_status WHERE parcel_id = p.id AND status_text REGEXP 'пути|доставке|центр' ORDER BY id DESC LIMIT 1)";
    } elseif ($filter === 'awaiting') {
        $where[] = "EXISTS (SELECT 1 FROM parcel_status WHERE parcel_id = p.id AND status_text LIKE '%ожидает%' ORDER BY id DESC LIMIT 1)";
    } elseif ($filter === 'delivered') {
        $where[] = "EXISTS (SELECT 1 FROM parcel_status WHERE parcel_id = p.id AND status_text REGEXP 'выдана|доставлено' ORDER BY id DESC LIMIT 1)";
    }

    if ($q !== '') {
        $where[] = "(p.track_code LIKE :q OR p.address LIKE :q OR s.name LIKE :q OR r.name LIKE :q OR s.login LIKE :q OR r.login LIKE :q)";
        $params['q'] = "%$q%";
    }

    $sql = "SELECT p.*,
            s.name AS s_name, s.login AS s_login,
            r.name AS r_name, r.login AS r_login,
            (SELECT status_text FROM parcel_status WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) AS last_status,
            (SELECT id FROM parcel_status WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) AS last_status_id
            FROM parcels p
            LEFT JOIN users s ON p.sender_id = s.id
            LEFT JOIN users r ON p.recipient_id = r.id";

    if (!empty($where)) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY p.id DESC LIMIT 200";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $parcels = $stmt->fetchAll();

    // Программа лояльности
    $stmt = $pdo->prepare("SELECT * FROM loyalty_cards WHERE user_id = :uid");
    $stmt->execute(['uid' => $user_id]);
    $loyalty_card = $stmt->fetch();

    // Обработка выпуска/перевыпуска карты
    if (isset($_POST['issue_card'])) {
        $card_number = "5000" . str_pad(rand(0, 999999999999), 12, '0', STR_PAD_LEFT);
        if ($loyalty_card) {
            // Перевыпуск (номер меняется, баланс и уровень остаются)
            $stmt = $pdo->prepare("UPDATE loyalty_cards SET card_number = :cn WHERE user_id = :uid");
            $stmt->execute(['uid' => $user_id, 'cn' => $card_number]);
            notifyUser($user_id, "Ваша карта лояльности успешно перевыпущена. Новый номер: $card_number");
        } else {
            // Новый выпуск
            $stmt = $pdo->prepare("INSERT INTO loyalty_cards (user_id, card_number, level, balance) VALUES (:uid, :cn, 'classic', 0)");
            $stmt->execute(['uid' => $user_id, 'cn' => $card_number]);
        }
        header("Location: dashboard.php"); exit;
    }

    // Коды выдачи
    if ($role !== 'worker') {
        $stmt = $pdo->prepare("SELECT pc.*, p.track_code FROM parcel_codes pc JOIN parcels p ON pc.parcel_id = p.id WHERE p.recipient_id = :uid AND pc.code_date = DATE(NOW())");
        $stmt->execute(['uid' => $user_id]);
        $codes = $stmt->fetchAll();
    }

} catch (PDOException $e) { $db_error = $e->getMessage(); }

include __DIR__ . '/header.php';
?>

<style>
/* Loyalty Card Style */
.loyalty-card {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    color: #fff;
    border-radius: 20px;
    padding: 25px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 15px 35px rgba(0,0,0,0.2);
}
.loyalty-card.bronze { background: linear-gradient(135deg, #804a00 0%, #cd7f32 100%); }
.loyalty-card.silver { background: linear-gradient(135deg, #757575 0%, #c0c0c0 100%); color: #333; }
.loyalty-card.gold { background: linear-gradient(135deg, #d4af37 0%, #f9d71c 100%); color: #333; }
.loyalty-card.premium { background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%); border: 2px solid #f9d71c; }
.loyalty-barcode-bg { background: #fff; padding: 10px; border-radius: 10px; display: inline-block; margin-top: 15px; }

/* Custom Styles for Super Cool Optimization */
.btn-create { background: #6f42c1; color: #fff; border: none; }
.btn-create:hover { background: #59359a; color: #fff; }
.card-parcel { transition: 0.3s; border: 1px solid rgba(0,0,0,0.05); }
.card-parcel:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important; }
.mobile-fab { position: fixed; bottom: 20px; right: 20px; z-index: 1000; width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 5px 15px rgba(111, 66, 193, 0.4); }
@media (min-width: 992px) { .mobile-fab { display: none; } }
@media (max-width: 768px) {
    .h3-mobile { font-size: 1.15rem; }
    .card-body { padding: 0.85rem !important; }
    .p-4 { padding: 0.85rem !important; }
    .mb-3, .mb-4 { margin-bottom: 0.6rem !important; }
    .btn { padding: 0.6rem 1.2rem; font-size: 0.85rem; }
}
</style>

<!-- Floating Action Button for Mobile -->
<a href="parcel_add.php" class="mobile-fab btn-create d-lg-none">
    <i class="bi bi-plus-lg fs-3"></i>
</a>

<?php if (isset($_GET['pay_success'])): ?>
    <div class="alert alert-success border-0 shadow-lg rounded-4 p-4 mb-4 animate-fade-in">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="bg-white bg-opacity-25 p-3 rounded-circle me-3"><i class="bi bi-check-circle-fill fs-2 text-success"></i></div>
                <div>
                    <h5 class="mb-0 fw-bold">Оплата успешно принята!</h5>
                    <p class="mb-0 small opacity-75">Данные о посылке и бонусах обновлены.</p>
                </div>
            </div>
            <a href="parcel_receipt.php?id=<?php echo (int)$_GET['id']; ?>" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">Смотреть чек</a>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3 g-lg-4 animate-fade-in">

    <!-- 1. ПРИВЕТСТВИЕ И СТАТИСТИКА -->
    <div class="col-lg-8">
        <div class="card bg-primary text-white p-3 p-md-4 border-0 shadow-lg rounded-4 overflow-hidden position-relative mb-3 mb-md-4">
            <div class="position-relative z-index-2">
                <h2 class="fw-bold mb-1 h3-mobile">Привет, <?php echo e($name); ?>! 👋</h2>
                <p class="opacity-75 mb-3">Ваш системный номер: <strong>#<?php echo $user_id; ?></strong></p>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="parcel_add.php" class="btn btn-create fw-bold shadow-sm rounded-pill px-4"><i class="bi bi-plus-lg me-2"></i>Создать посылку</a>
                    <?php if($role === 'worker'): ?>
                        <a href="parcel_issue.php" class="btn btn-success border-0 fw-bold shadow-sm rounded-pill px-4">Выдача</a>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Иконка сдвинута, чтобы не мешать кнопкам -->
            <i class="bi bi-lightning-charge position-absolute end-0 bottom-0 mb-n5 me-n4 opacity-10" style="font-size: 12rem;"></i>
        </div>

        <?php if($role === 'worker'): ?>
        <!-- ПАНЕЛЬ УПРАВЛЕНИЯ ДЛЯ РАБОТНИКА (отдельно от карточки с молнией) -->
        <div class="card border-0 shadow-sm rounded-4 mb-3 mb-md-4 bg-white">
            <div class="card-body p-3">
                <div class="d-flex gap-2 flex-wrap">
                    <a href="shift_manage.php" class="btn btn-dark border-0 fw-bold shadow-sm rounded-pill px-4 flex-grow-1 flex-md-grow-0">
                        <i class="bi bi-calculator me-2"></i>Касса / Смены
                    </a>
                    <a href="transfer_add.php" class="btn btn-outline-primary border-2 fw-bold shadow-sm rounded-pill px-4 flex-grow-1 flex-md-grow-0">
                        <i class="bi bi-send-fill me-2"></i>Перевод (отправить)
                    </a>
                    <a href="transfer_list.php" class="btn btn-outline-primary border-2 fw-bold shadow-sm rounded-pill px-4 flex-grow-1 flex-md-grow-0">
                        <i class="bi bi-cash-stack me-2"></i>Переводы (упр.)
                    </a>
                    <a href="parcel_cod_refund.php" class="btn btn-outline-danger border-2 fw-bold shadow-sm rounded-pill px-4 flex-grow-1 flex-md-grow-0">
                        <i class="bi bi-arrow-counterclockwise me-2"></i>Возврат нал.плат.
                    </a>
                    <a href="transfer_refund_issue.php" class="btn btn-outline-danger border-2 fw-bold shadow-sm rounded-pill px-4 flex-grow-1 flex-md-grow-0">
                        <i class="bi bi-arrow-return-left me-2"></i>Выплата возврата пер.
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ФИЛЬТРЫ -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-3">
                <form method="get" class="row g-2">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0 ps-3 rounded-pill-start"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" name="q" class="form-control border-0 bg-light rounded-pill-end shadow-none" placeholder="Поиск..." value="<?php echo e($q); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select name="filter" class="form-select border-0 bg-light rounded-pill shadow-none" onchange="this.form.submit()">
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Все отправления</option>
                            <option value="in_transit" <?php echo $filter === 'in_transit' ? 'selected' : ''; ?>>В пути</option>
                            <option value="awaiting" <?php echo $filter === 'awaiting' ? 'selected' : ''; ?>>Ожидают выдачи</option>
                            <option value="delivered" <?php echo $filter === 'delivered' ? 'selected' : ''; ?>>Доставлены</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-none d-md-block">
                        <button class="btn btn-primary w-100 rounded-pill fw-bold border-0">Найти</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- СПИСОК ПОСЫЛОК -->
        <div class="mb-4">
            <!-- Desktop View (Table) -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden d-none d-md-block">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr class="text-muted x-small text-uppercase">
                                <th class="ps-4 border-0 py-3">Посылка</th>
                                <th class="border-0 py-3">Маршрут</th>
                                <th class="border-0 py-3">Статус</th>
                                <th class="text-end pe-4 border-0 py-3">Действие</th>
                            </tr>
                        </thead>
                        <tbody class="border-0">
                            <?php if ($parcels): ?>
                                <?php foreach ($parcels as $p): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark mb-1"><?php echo e($p['track_code']); ?></div>
                                            <div class="x-small text-muted fw-bold"><?php echo e($p['tariff']); ?> · <?php echo number_format($p['weight'], 3); ?> кг</div>
                                        </td>
                                        <td>
                                            <div class="small fw-bold text-dark mb-1"><?php echo e($p['s_name'] ?: $p['s_login']); ?> → <?php echo e($p['r_name'] ?: $p['r_login']); ?></div>
                                        <div class="x-small text-muted text-truncate" style="max-width: 200px;">
                                            <span title="Откуда"><i class="bi bi-geo"></i> <?php echo e($p['sender_address'] ?: '...'); ?></span><br>
                                            <span title="Куда"><i class="bi bi-geo-fill"></i> <?php echo e($p['address']); ?></span>
                                        </div>
                                        </td>
                                        <td>
                                        <div class="mb-1">
                                            <?php if ($p['is_paid']): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success x-small fw-bold">ОПЛАЧЕНО</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger x-small fw-bold">НЕ ОПЛАЧЕНО</span>
                                            <?php endif; ?>
                                            <?php if ($p['is_return']): ?>
                                                <span class="badge bg-warning bg-opacity-10 text-dark x-small fw-bold ms-1">ВОЗВРАТ</span>
                                            <?php endif; ?>
                                            <?php if ($p['pay_on_delivery']): ?>
                                                <span class="badge bg-info bg-opacity-10 text-info x-small fw-bold ms-1">ПРИ ПОЛУЧЕНИИ</span>
                                            <?php endif; ?>
                                            <?php if ($p['cod'] > 0): ?>
                                                <span class="badge <?php echo $p['is_cod_paid'] ? 'bg-success' : 'bg-warning'; ?> bg-opacity-10 text-<?php echo $p['is_cod_paid'] ? 'success' : 'dark'; ?> x-small fw-bold ms-1">НАЛ.ПЛ: <?php echo $p['is_cod_paid'] ? 'ОК' : 'ЖДЕТ'; ?></span>
                                            <?php endif; ?>
                                            <?php if ($p['is_refunded']): ?>
                                                <span class="badge bg-danger bg-opacity-10 text-danger x-small fw-bold ms-1">ВОЗВРАТ СРЕДСТВ</span>
                                            <?php endif; ?>
                                        </div>
                                            <?php
                                                $st = $p['last_status'] ?: 'Оформлена';
                                                $bc = 'bg-primary';
                                                if (mb_stripos($st, 'ожидает') !== false) $bc = 'bg-warning text-dark';
                                                if (mb_stripos($st, 'выдана') !== false || mb_stripos($st, 'доставлено') !== false) $bc = 'bg-success text-white';
                                            ?>
                                            <span class="badge <?php echo $bc; ?> bg-opacity-10 text-<?php echo strpos($bc, 'white') !== false ? 'success' : str_replace('bg-', '', explode(' ', $bc)[0]); ?> px-3 py-2 fw-bold" style="font-size: 0.7rem;">
                                                <?php echo e(mb_strtoupper($st)); ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="dropdown">
                                                <button class="btn btn-light btn-sm rounded-pill px-3 border shadow-none" type="button" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 p-2">
                                                    <li><a class="dropdown-item py-2" href="parcel_history.php?id=<?php echo $p['id']; ?>"><i class="bi bi-clock-history me-2 text-primary"></i>История</a></li>
                                                    <li><a class="dropdown-item py-2" href="label_print.php?id=<?php echo $p['id']; ?>"><i class="bi bi-printer me-2 text-primary"></i>Печать</a></li>
                                                <?php if ($p['is_paid']): ?>
                                                    <li><a class="dropdown-item py-2 fw-bold text-success" href="parcel_receipt.php?id=<?php echo $p['id']; ?>"><i class="bi bi-receipt me-2"></i>ЧЕК ОБ ОПЛАТЕ</a></li>
                                                <?php elseif ($role === 'worker'): ?>
                                                    <li><a class="dropdown-item py-2 fw-bold text-success" href="parcel_pay.php?id=<?php echo $p['id']; ?>"><i class="bi bi-cash-coin me-2"></i>ОПЛАТИТЬ УСЛУГИ</a></li>
                                                <?php endif; ?>

                                                <?php if ($role === 'worker' && $p['cod'] > 0 && !$p['is_cod_paid']): ?>
                                                    <li><a class="dropdown-item py-2 fw-bold text-primary" href="parcel_pay_cod.php?id=<?php echo $p['id']; ?>"><i class="bi bi-wallet2 me-2"></i>ПРИНЯТЬ НАЛ.ПЛ.</a></li>
                                                <?php endif; ?>
                                                <?php if ($role === 'worker' && $p['is_cod_paid'] && !$p['is_cod_issued']): ?>
                                                    <li><a class="dropdown-item py-2 fw-bold text-info" href="parcel_cod_issue.php?id=<?php echo $p['id']; ?>"><i class="bi bi-cash me-2"></i>ВЫДАТЬ НАЛ.ПЛ. ОТПРАВИТЕЛЮ</a></li>
                                                <?php endif; ?>
                                                <?php if ($role === 'worker' && $p['refund_code'] && $p['is_cod_paid'] && !$p['cod_refund_issued']): ?>
                                                    <li><a class="dropdown-item py-2 fw-bold text-danger" href="parcel_cod_refund.php?q=<?php echo $p['refund_code']; ?>"><i class="bi bi-arrow-counterclockwise me-2"></i>ВЕРНУТЬ НАЛ.ПЛ. ПОЛУЧАТЕЛЮ</a></li>
                                                <?php endif; ?>
                                                <?php if ($role === 'worker' && $p['pickup_point'] && !$p['shelf']): ?>
                                                    <li><a class="dropdown-item py-2 fw-bold text-primary" href="parcel_pvz_receive.php?id=<?php echo $p['id']; ?>"><i class="bi bi-download me-2"></i>ПРИНЯТЬ В ПВЗ</a></li>
                                                <?php endif; ?>
                                                <?php if ($role === 'worker' && $p['is_paid'] && !$p['is_refunded']): ?>
                                                    <li><a class="dropdown-item py-2 text-danger" href="parcel_refund.php?id=<?php echo $p['id']; ?>"><i class="bi bi-arrow-counterclockwise me-2"></i>ВОЗВРАТ ДЕНЕГ</a></li>
                                                <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item py-2" href="parcel_edit.php?id=<?php echo $p['id']; ?>"><i class="bi bi-pencil-square me-2 text-warning"></i>Изменить</a></li>
                                                    <?php if ($role === 'worker'): ?>
                                                        <li><a class="dropdown-item py-2 fw-bold text-success" href="parcel_status.php?id=<?php echo $p['id']; ?>"><i class="bi bi-plus-circle me-2"></i>Новый статус</a></li>
                                                    <?php if($p['is_paid'] && !$p['is_return']): ?>
                                                        <li><a class="dropdown-item py-2 text-danger" href="javascript:void(0)" onclick="confReturn(<?php echo $p['id']; ?>, '<?php echo e($p['track_code']); ?>')"><i class="bi bi-arrow-return-left me-2"></i>Оформить возврат</a></li>
                                                    <?php endif; ?>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item py-2 text-danger fw-bold" href="javascript:void(0)" onclick="confDel(<?php echo $p['id']; ?>, '<?php echo e($p['track_code']); ?>')"><i class="bi bi-trash3 me-2"></i>Удалить</a></li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center py-5 text-muted fw-bold">Список посылок пуст</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile View (Cards) -->
            <div class="d-md-none">
                <?php if ($parcels): ?>
                    <?php foreach ($parcels as $p): ?>
                        <div class="card card-parcel border-0 shadow-sm rounded-4 mb-3 overflow-hidden">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <div class="h5 fw-bold text-primary mb-0"><?php echo e($p['track_code']); ?></div>
                                        <div class="x-small text-muted fw-bold"><?php echo e($p['tariff']); ?> · <?php echo number_format($p['weight'], 3); ?> кг</div>
                                    </div>
                                    <?php
                                        $st = $p['last_status'] ?: 'Оформлена';
                                        $bc = 'bg-primary';
                                        if (mb_stripos($st, 'ожидает') !== false) $bc = 'bg-warning text-dark';
                                        if (mb_stripos($st, 'выдана') !== false || mb_stripos($st, 'доставлено') !== false) $bc = 'bg-success text-white';
                                    ?>
                                    <span class="badge <?php echo $bc; ?> py-2 px-3 rounded-pill fw-bold" style="font-size: 0.65rem;">
                                        <?php echo e(mb_strtoupper($st)); ?>
                                    </span>
                                </div>
                                <div class="mb-2">
                                    <?php if ($p['is_paid']): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success x-small fw-bold">ОПЛАЧЕНО</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger x-small fw-bold">НЕ ОПЛАЧЕНО</span>
                                    <?php endif; ?>
                                    <?php if ($p['is_return']): ?>
                                        <span class="badge bg-warning bg-opacity-10 text-dark x-small fw-bold ms-1">ВОЗВРАТ</span>
                                    <?php endif; ?>
                                    <?php if ($p['pay_on_delivery']): ?>
                                        <span class="badge bg-info bg-opacity-10 text-info x-small fw-bold ms-1">ПРИ ПОЛУЧЕНИИ</span>
                                    <?php endif; ?>
                                    <?php if ($p['cod'] > 0): ?>
                                        <span class="badge <?php echo $p['is_cod_paid'] ? 'bg-success' : 'bg-warning'; ?> bg-opacity-10 text-<?php echo $p['is_cod_paid'] ? 'success' : 'dark'; ?> x-small fw-bold ms-1">НАЛ.ПЛ: <?php echo $p['is_cod_paid'] ? 'ОК' : 'ЖДЕТ'; ?></span>
                                    <?php endif; ?>
                                    <?php if ($p['is_refunded']): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger x-small fw-bold ms-1">ВОЗВРАТ СРЕДСТВ</span>
                                    <?php endif; ?>
                                </div>
                                <div class="mb-3">
                                    <div class="small fw-bold text-dark mb-1"><i class="bi bi-person me-2 text-muted"></i><?php echo e($p['s_name'] ?: $p['s_login']); ?> → <?php echo e($p['r_name'] ?: $p['r_login']); ?></div>
                                    <div class="small text-muted mb-1"><i class="bi bi-geo me-2 text-muted"></i><?php echo e($p['sender_address'] ?: '...'); ?></div>
                                    <div class="small text-muted"><i class="bi bi-geo-fill me-2 text-muted"></i><?php echo e($p['address']); ?></div>
                                </div>
                                <div class="d-flex gap-2 mb-2">
                                    <a href="parcel_history.php?id=<?php echo $p['id']; ?>" class="btn btn-light btn-sm flex-grow-1 rounded-pill fw-bold">История</a>
                                    <a href="label_print.php?id=<?php echo $p['id']; ?>" class="btn btn-light btn-sm flex-grow-1 rounded-pill fw-bold">Печать</a>
                                </div>
                                <?php if ($p['is_paid']): ?>
                                    <a href="parcel_receipt.php?id=<?php echo $p['id']; ?>" class="btn btn-outline-success btn-sm w-100 rounded-pill fw-bold mb-2"><i class="bi bi-receipt me-1"></i>ПОСМОТРЕТЬ ЧЕК</a>
                                <?php elseif ($role === 'worker'): ?>
                                    <a href="parcel_pay.php?id=<?php echo $p['id']; ?>" class="btn btn-success btn-sm w-100 rounded-pill fw-bold mb-2"><i class="bi bi-cash-coin me-1"></i>ОПЛАТИТЬ УСЛУГИ</a>
                                <?php endif; ?>

                                <?php if ($role === 'worker' && $p['cod'] > 0 && !$p['is_cod_paid']): ?>
                                    <a href="parcel_pay_cod.php?id=<?php echo $p['id']; ?>" class="btn btn-primary btn-sm w-100 rounded-pill fw-bold mb-2"><i class="bi bi-wallet2 me-1"></i>ПРИНЯТЬ НАЛ.ПЛ.</a>
                                <?php endif; ?>
                                <?php if ($role === 'worker' && $p['refund_code'] && $p['is_cod_paid'] && !$p['cod_refund_issued']): ?>
                                    <a href="parcel_cod_refund.php?q=<?php echo $p['refund_code']; ?>" class="btn btn-danger btn-sm w-100 rounded-pill fw-bold mb-2"><i class="bi bi-arrow-counterclockwise me-1"></i>ВЕРНУТЬ НАЛ.ПЛ. ПОЛУЧАТЕЛЮ</a>
                                <?php endif; ?>
                                <?php if ($role === 'worker' && $p['pickup_point'] && !$p['shelf']): ?>
                                    <a href="parcel_pvz_receive.php?id=<?php echo $p['id']; ?>" class="btn btn-primary btn-sm w-100 rounded-pill fw-bold mb-2"><i class="bi bi-download me-1"></i>ПРИНЯТЬ В ПВЗ</a>
                                <?php endif; ?>
                                <div class="d-flex gap-2">
                                    <div class="dropdown">
                                        <button class="btn btn-light btn-sm rounded-pill px-3 shadow-none" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 p-2">
                                            <li><a class="dropdown-item py-2" href="parcel_edit.php?id=<?php echo $p['id']; ?>"><i class="bi bi-pencil-square me-2 text-warning"></i>Изменить</a></li>
                                            <?php if ($role === 'worker'): ?>
                                                <li><a class="dropdown-item py-2 fw-bold text-success" href="parcel_status.php?id=<?php echo $p['id']; ?>"><i class="bi bi-plus-circle me-2"></i>Статус</a></li>
                                            <?php endif; ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item py-2 text-danger fw-bold" href="javascript:void(0)" onclick="confDel(<?php echo $p['id']; ?>, '<?php echo e($p['track_code']); ?>')"><i class="bi bi-trash3 me-2"></i>Удалить</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5 text-muted fw-bold">Нет посылок</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 2. ПРАВАЯ ПАНЕЛЬ -->
    <div class="col-lg-4">
        <!-- КАРТА ЛОЯЛЬНОСТИ -->
        <div class="mb-4">
            <?php if($loyalty_card): ?>
                <div class="loyalty-card <?php echo $loyalty_card['level']; ?>">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <div class="x-small text-uppercase fw-bold opacity-75">Карта лояльности</div>
                            <div class="h5 fw-bold mb-0"><?php echo strtoupper($loyalty_card['level']); ?></div>
                        </div>
                        <i class="bi bi-cpu fs-3 opacity-50"></i>
                    </div>
                    <div class="mb-4">
                        <div class="h4 mb-0 fw-bold" style="letter-spacing: 2px;">
                            <?php echo implode(' ', str_split($loyalty_card['card_number'], 4)); ?>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-end">
                        <div>
                            <div class="x-small text-uppercase fw-bold opacity-75">Баланс бонусов</div>
                            <div class="h4 fw-bold mb-0"><?php echo number_format($loyalty_card['balance'], 0); ?> Б.</div>
                        </div>
                        <div class="loyalty-barcode-bg">
                            <svg id="card-barcode"></svg>
                        </div>
                    </div>
                </div>
                <form method="post" class="mt-2 text-end">
                    <button name="issue_card" class="btn btn-link btn-sm text-white opacity-50 p-0 text-decoration-none" onclick="return confirm('Перевыпустить карту? Старый номер станет недействителен.')">Перевыпустить карту</button>
                </form>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        JsBarcode("#card-barcode", "<?php echo $loyalty_card['card_number']; ?>", {
                            format: "CODE128", width: 1.5, height: 35, displayValue: false, margin: 0
                        });
                    });
                </script>
            <?php else: ?>
                <div class="card p-4 text-center border-dashed border-2">
                    <i class="bi bi-credit-card-2-front fs-1 text-muted mb-2"></i>
                    <h6 class="fw-bold">Программа лояльности</h6>
                    <p class="small text-muted mb-3">Копите бонусы и оплачивайте ими до 100% стоимости посылок!</p>
                    <form method="post">
                        <button name="issue_card" class="btn btn-primary btn-sm rounded-pill px-4">Выпустить карту</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm border-start border-success border-5 rounded-4 mb-3 mb-md-4">
            <div class="card-body p-3 p-md-4 d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-bold text-uppercase mb-1"><?php echo $role === 'worker' ? 'Выручка системы' : 'Мои расходы'; ?></div>
                    <div class="h4 mb-0 fw-extrabold text-success"><?php echo number_format($total_revenue, 2); ?> <span class="small">BYN</span></div>
                </div>
                <div class="bg-success bg-opacity-10 p-2 p-md-3 rounded-circle text-success"><i class="bi bi-wallet2 fs-4 fs-md-3"></i></div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Уведомления</h5>
                <?php if($unreadCount > 0): ?><span class="badge bg-danger rounded-pill"><?php echo $unreadCount; ?></span><?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if ($notifications): ?>
                        <?php foreach ($notifications as $n): ?>
                            <div class="list-group-item p-4 border-0 border-bottom <?php echo $n['is_read'] ? '' : 'bg-primary bg-opacity-5'; ?>">
                                <div class="small fw-bold mb-2 text-dark" style="line-height:1.4"><?php echo e($n['message']); ?></div>
                                <div class="d-flex gap-3">
                                    <?php if (!$n['is_read']): ?>
                                        <a href="notifications.php?action=read&id=<?php echo $n['id']; ?>" class="x-small text-primary text-decoration-none fw-bold">ПРОЧИТАНО</a>
                                    <?php endif; ?>
                                    <a href="notifications.php?action=delete&id=<?php echo $n['id']; ?>" class="x-small text-danger text-decoration-none fw-bold">УДАЛИТЬ</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="p-3 text-center"><a href="notifications.php?action=read_all" class="btn btn-light btn-sm w-100 rounded-pill fw-bold">Прочитать все</a></div>
                    <?php else: ?>
                        <div class="p-5 text-center text-muted fw-bold">Уведомлений нет</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confDel(id, track) {
    if (confirm('ВНИМАНИЕ! Посылка ' + track + ' будет полностью удалена. Продолжить?')) {
        window.location.href = 'parcel_delete.php?id=' + id;
    }
}
function confReturn(id, track) {
    if (confirm('Вы уверены, что хотите оформить возврат для оплаченной посылки ' + track + '?')) {
        window.location.href = 'parcel_return.php?id=' + id;
    }
}
</script>

<?php
include __DIR__ . '/footer.php';
ob_end_flush();
?>
