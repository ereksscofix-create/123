<?php
/**
 * dashboard.php — Главная панель управления EHPST
 * Финальная версия: Исправлены все кнопки, модальные окна и дизайн.
 */
ob_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// 1. Авторизация
checkLogin();
$user = currentUser();
if (!$user) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

$user_id = (int)$user['id'];
$role = $user['role'] ?? 'recipient';
$name = $user['name'] ?: ($user['login'] ?: 'Пользователь');

// 2. Параметры страницы
$filter = $_GET['filter'] ?? 'all';
$q = trim((string)($_GET['q'] ?? ''));
$page_title = "Дашборд - EHPST";

// 3. Загрузка данных
$unreadCount = 0;
$notifications = [];
$total_parcels = 0;
$total_revenue = 0.0;
$parcels = [];
$codes = [];

try {
    // Уведомления
    $stmt = $pdo->prepare("SELECT id, message, is_read FROM notifications WHERE user_id = :uid ORDER BY id DESC LIMIT 10");
    $stmt->execute(['uid' => $user_id]);
    $notifications = $stmt->fetchAll();
    foreach($notifications as $n) { if(!$n['is_read']) $unreadCount++; }

    // Статистика
    if ($role === 'worker') {
        $total_parcels = (int)$pdo->query("SELECT COUNT(*) FROM parcels")->fetchColumn();
        $total_revenue = (float)$pdo->query("SELECT IFNULL(SUM(cost),0) FROM parcels")->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM parcels WHERE sender_id = :suid OR recipient_id = :ruid");
        $stmt->execute(['suid' => $user_id, 'ruid' => $user_id]);
        $total_parcels = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT IFNULL(SUM(cost),0) FROM parcels WHERE sender_id = :suid");
        $stmt->execute(['suid' => $user_id]);
        $total_revenue = (float)$stmt->fetchColumn();
    }

    // Список посылок (ФИЛЬТРАЦИЯ)
    $params = [];
    $where = [];
    $baseSql = "SELECT p.*, s.name AS s_name, s.login AS s_login, r.name AS r_name, r.login AS r_login,
                (SELECT status_text FROM parcel_status WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) AS last_status,
                (SELECT created_at FROM parcel_status WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) AS last_update
                FROM parcels p
                LEFT JOIN users s ON p.sender_id = s.id
                LEFT JOIN users r ON p.recipient_id = r.id";

    if ($role !== 'worker') {
        $where[] = "(p.sender_id = :uid_sender OR p.recipient_id = :uid_recipient)";
        $params['uid_sender'] = $user_id;
        $params['uid_recipient'] = $user_id;
    }

    if ($filter === 'in_transit') {
        $where[] = "(SELECT status_text FROM parcel_status WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) REGEXP 'пути|доставке|сортировочный'";
    } elseif ($filter === 'awaiting') {
        $where[] = "(SELECT status_text FROM parcel_status WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) LIKE '%ожидает%'";
    } elseif ($filter === 'delivered') {
        $where[] = "(SELECT status_text FROM parcel_status WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) REGEXP 'выдана|доставлено'";
    }

    if ($q !== '') {
        $where[] = "(p.track_code LIKE :q OR p.address LIKE :q OR s.name LIKE :q OR r.name LIKE :q OR s.login LIKE :q OR r.login LIKE :q)";
        $params['q'] = '%' . $q . '%';
    }

    $sql = $baseSql . (!empty($where) ? " WHERE " . implode(' AND ', $where) : "") . " ORDER BY p.id DESC LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $parcels = $stmt->fetchAll();

    // Коды для получателей
    if ($role !== 'worker') {
        $stmt = $pdo->prepare("SELECT pc.*, p.track_code FROM parcel_codes pc JOIN parcels p ON pc.parcel_id = p.id WHERE p.recipient_id = :uid AND pc.code_date = DATE(NOW()) ORDER BY pc.id DESC");
        $stmt->execute(['uid' => $user_id]);
        $codes = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $db_error = $e->getMessage();
}

include __DIR__ . '/header.php';
?>

<div class="row g-4">
    <!-- 1. ПРИВЕТСТВИЕ -->
    <div class="col-12">
        <div class="card bg-primary text-white p-4 border-0 shadow-lg rounded-4 position-relative overflow-hidden">
            <div class="position-relative z-3">
                <h1 class="h2 mb-1 fw-bold">Добро пожаловать, <?php echo e($name); ?>! 👋</h1>
                <p class="mb-0 opacity-75">Ваш ID в системе: <span class="badge bg-white text-primary px-2">#<?php echo $user_id; ?></span></p>
            </div>
            <i class="bi bi-lightning-charge-fill position-absolute end-0 bottom-0 mb-n4 me-n2 opacity-10" style="font-size: 10rem;"></i>
        </div>
    </div>

    <!-- 2. БЫСТРЫЕ ДЕЙСТВИЯ -->
    <div class="col-12">
        <div class="d-flex gap-2 flex-wrap">
            <a href="parcel_add.php" class="btn btn-primary px-4 shadow-sm border-0"><i class="bi bi-plus-lg me-2"></i>Новая посылка</a>
            <?php if ($role === 'worker'): ?>
                <a href="parcel_issue.php" class="btn btn-success px-4 shadow-sm border-0"><i class="bi bi-box-arrow-right me-2"></i>Выдать</a>
            <?php endif; ?>
            <!-- КНОПКА УВЕДОМЛЕНИЙ -->
            <button type="button" class="btn btn-white border bg-white px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#notifModal">
                <i class="bi bi-bell me-2"></i>Уведомления
                <?php if ($unreadCount > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-1"><?php echo $unreadCount; ?></span>
                <?php endif; ?>
            </button>
        </div>
    </div>

    <!-- 3. СТАТИСТИКА -->
    <div class="col-md-6 col-xl-3">
        <div class="card p-4 border-0 shadow-sm border-start border-primary border-5 rounded-4 h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-bold text-uppercase mb-1" style="font-size:0.7rem">Всего отправлений</div>
                    <div class="h2 mb-0 fw-bold"><?php echo $total_parcels; ?></div>
                </div>
                <div class="bg-primary bg-opacity-10 p-3 rounded-4 text-primary"><i class="bi bi-box-seam fs-3"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card p-4 border-0 shadow-sm border-start border-success border-5 rounded-4 h-100">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small fw-bold text-uppercase mb-1" style="font-size:0.7rem"><?php echo $role === 'worker' ? 'Выручка' : 'Мои расходы'; ?></div>
                    <div class="h2 mb-0 fw-bold text-success"><?php echo number_format($total_revenue, 2); ?> <span class="small">BYN</span></div>
                </div>
                <div class="bg-success bg-opacity-10 p-3 rounded-4 text-success"><i class="bi bi-wallet2 fs-3"></i></div>
            </div>
        </div>
    </div>

    <!-- 4. КОДЫ ВЫДАЧИ -->
    <?php if (!empty($codes)): ?>
    <div class="col-12">
        <div class="card border-0 shadow-sm bg-warning bg-opacity-10 rounded-4 overflow-hidden border-start border-warning border-5">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2"></i>Ваши коды получения на сегодня:</h5>
                <div class="row g-3">
                    <?php foreach ($codes as $c): ?>
                        <div class="col-md-4">
                            <div class="bg-white p-3 rounded-4 shadow-sm text-center">
                                <div class="small text-muted mb-1 fw-bold"><?php echo e($c['track_code']); ?></div>
                                <div class="h2 mb-0 text-primary font-monospace fw-bold"><?php echo e($c['code']); ?></div>
                                <?php if($c['secret_code']): ?>
                                    <div class="small text-danger mt-1 fw-bold">СЕКРЕТ: <?php echo e($c['secret_code']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 5. ТАБЛИЦА ПОСЫЛОК -->
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white border-bottom p-3">
                <form method="get" class="row g-2">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0 ps-3 rounded-pill-start"><i class="bi bi-search"></i></span>
                            <input type="text" name="q" class="form-control border-0 bg-light rounded-pill-end shadow-none" placeholder="Поиск по треку, адресу или имени..." value="<?php echo e($q); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select name="filter" class="form-select border-0 bg-light rounded-pill shadow-none" onchange="this.form.submit()">
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Все отправления</option>
                            <option value="in_transit" <?php echo $filter === 'in_transit' ? 'selected' : ''; ?>>В пути</option>
                            <option value="awaiting" <?php echo $filter === 'awaiting' ? 'selected' : ''; ?>>Ожидают выдачи</option>
                            <option value="delivered" <?php echo $filter === 'delivered' ? 'selected' : ''; ?>>Выданы / Получены</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100 rounded-pill fw-bold border-0" style="padding: 0.6rem">Найти</button>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 py-3 border-0">Трек-код</th>
                            <th class="py-3 border-0">Маршрут</th>
                            <th class="py-3 border-0 text-center">Статус</th>
                            <th class="py-3 border-0 text-end pe-4">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="border-0">
                        <?php if ($parcels): ?>
                            <?php foreach ($parcels as $p): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark mb-1"><?php echo e($p['track_code']); ?></div>
                                        <div class="badge bg-light text-muted x-small border fw-bold"><?php echo e($p['tariff']); ?> · <?php echo number_format($p['weight'], 3); ?> кг</div>
                                    </td>
                                    <td>
                                        <div class="small fw-bold text-dark mb-1">
                                            <?php echo e($p['s_name'] ?: $p['s_login']); ?> → <?php echo e($p['r_name'] ?: $p['r_login']); ?>
                                        </div>
                                        <div class="x-small text-muted text-truncate" style="max-width: 180px;"><?php echo e($p['address']); ?></div>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                            $st = $p['last_status'] ?: 'Оформлена';
                                            $bc = 'bg-primary';
                                            if (mb_stripos($st, 'ожидает') !== false) $bc = 'bg-warning text-dark';
                                            if (mb_stripos($st, 'выдана') !== false || mb_stripos($st, 'доставлено') !== false) $bc = 'bg-success';
                                        ?>
                                        <span class="badge <?php echo $bc; ?> bg-opacity-10 text-<?php echo str_replace('bg-', '', explode(' ', $bc)[0]); ?> px-3 py-2 fw-bold" style="font-size: 0.7rem; letter-spacing: 0.2px">
                                            <?php echo e(mb_strtoupper($st)); ?>
                                        </span>
                                        <div class="x-small text-muted mt-1" style="font-size:0.6rem"><?php echo $p['last_update'] ? date('d.m H:i', strtotime($p['last_update'])) : ''; ?></div>
                                    </td>
                                    <td class="text-end pe-4">
                                        <!-- КНОПКА ДЕЙСТВИЙ (ВЫПАДАЮЩИЙ СПИСОК) -->
                                        <div class="dropdown">
                                            <button class="btn btn-light btn-sm rounded-pill px-3 border shadow-none" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 p-2">
                                                <li><a class="dropdown-item py-2" href="parcel_history.php?id=<?php echo $p['id']; ?>"><i class="bi bi-clock-history me-2 text-primary"></i>История перемещений</a></li>
                                                <li><a class="dropdown-item py-2" href="label_print.php?id=<?php echo $p['id']; ?>"><i class="bi bi-printer me-2 text-primary"></i>Печать ярлыка</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item py-2" href="parcel_edit.php?id=<?php echo $p['id']; ?>"><i class="bi bi-pencil-square me-2 text-warning"></i>Редактировать данные</a></li>
                                                <?php if ($role === 'worker'): ?>
                                                    <li><a class="dropdown-item py-2 fw-bold text-success" href="parcel_status.php?id=<?php echo $p['id']; ?>"><i class="bi bi-plus-circle me-2"></i>Добавить новый статус</a></li>
                                                <?php endif; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item py-2 text-danger fw-bold" href="javascript:void(0)" onclick="confDel(<?php echo $p['id']; ?>, '<?php echo e($p['track_code']); ?>')"><i class="bi bi-trash3 me-2"></i>Удалить из системы</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center py-5 text-muted fw-bold">Список отправлений пуст</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- 6. МОДАЛЬНОЕ ОКНО УВЕДОМЛЕНИЙ -->
<div class="modal fade" id="notifModal" tabindex="-1" aria-labelledby="notifModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-light p-4 rounded-top-4">
                <h5 class="modal-title fw-bold" id="notifModalLabel">Центр уведомлений</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body p-0">
                <div class="list-group list-group-flush">
                    <?php if ($notifications): ?>
                        <?php foreach ($notifications as $n): ?>
                            <div class="list-group-item p-4 border-0 border-bottom <?php echo $n['is_read'] ? '' : 'bg-primary bg-opacity-5'; ?>">
                                <div class="small fw-bold mb-2 text-dark" style="line-height:1.4"><?php echo e($n['message']); ?></div>
                                <div class="d-flex gap-3 mt-2">
                                    <?php if (!$n['is_read']): ?>
                                        <a href="notifications.php?action=read&id=<?php echo $n['id']; ?>" class="x-small text-primary text-decoration-none fw-bold">ОТМЕТИТЬ ПРОЧИТАННЫМ</a>
                                    <?php endif; ?>
                                    <a href="notifications.php?action=delete&id=<?php echo $n['id']; ?>" class="x-small text-danger text-decoration-none fw-bold">УДАЛИТЬ</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-5 text-center text-muted fw-bold">Новых уведомлений нет</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 7. СКРИПТ УДАЛЕНИЯ -->
<script>
function confDel(id, track) {
    if (confirm('ВНИМАНИЕ! Посылка ' + track + ' будет безвозвратно удалена из базы данных. Продолжить?')) {
        window.location.href = 'parcel_delete.php?id=' + id;
    }
}
</script>

<?php
include __DIR__ . '/footer.php';
ob_end_flush();
?>
