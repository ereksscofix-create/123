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

try {
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
        // ВАЖНО: Видим свои (отправил/получил) И те, на которые подписались
        $where[] = "(p.sender_id = :uid_sender OR p.recipient_id = :uid_recipient OR EXISTS(SELECT 1 FROM parcel_followers WHERE user_id = :uid_follow AND parcel_id = p.id))";
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
                        <a href="shift_manage.php" class="btn btn-dark border-0 fw-bold shadow-sm rounded-pill px-4">Касса / Смены</a>
                        <a href="transfer_add.php" class="btn btn-warning border-0 fw-bold shadow-sm rounded-pill px-4">Отправить перевод</a>
                        <a href="transfer_issue.php" class="btn btn-info border-0 fw-bold shadow-sm rounded-pill px-4">Выдать перевод</a>
                    <?php endif; ?>
                </div>
            </div>
            <i class="bi bi-lightning-charge position-absolute end-0 bottom-0 mb-n4 me-n2 opacity-10" style="font-size: 15rem;"></i>
        </div>

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
