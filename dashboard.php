<?php
// dashboard.php — обновлённый рабочий дашборд
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Проверяем авторизацию
checkLogin();

// Получаем текущего пользователя
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

// GET параметры
$filter = $_GET['filter'] ?? 'all';
$q = trim((string)($_GET['q'] ?? ''));

// Уведомления (непрочитанные)
$unreadCount = 0;
$notifications = [];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
    $stmt->execute(['uid' => $user_id]);
    $unreadCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id, message, type, created_at, is_read FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 10");
    $stmt->execute(['uid' => $user_id]);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("dashboard notifications error: " . $e->getMessage());
}

// Статистика
$total_parcels = 0;
$total_revenue = 0.0;
try {
    if ($role === 'worker') {
        $total_parcels = (int)$pdo->query("SELECT COUNT(*) FROM parcels")->fetchColumn();
        $total_revenue = (float)($pdo->query("SELECT IFNULL(SUM(cost),0) FROM parcels")->fetchColumn());
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM parcels WHERE sender_id = :suid OR recipient_id = :ruid");
        $stmt->execute(['suid' => $user_id, 'ruid' => $user_id]);
        $total_parcels = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT IFNULL(SUM(cost),0) FROM parcels WHERE sender_id = :suid");
        $stmt->execute(['suid' => $user_id]);
        $total_revenue = (float)$stmt->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("dashboard stats error: " . $e->getMessage());
}

// Построение запроса списка посылок
$params = [];
$where = [];
$baseSql = "
    SELECT p.*, s.name AS sender_name, r.name AS recipient_name,
        (SELECT status_text FROM parcel_status WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) AS last_status,
        (SELECT created_at FROM parcel_status WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) AS last_update
    FROM parcels p
    JOIN users s ON p.sender_id = s.id
    JOIN users r ON p.recipient_id = r.id
";

if ($role !== 'worker') {
    $where[] = "(p.sender_id = :uid_sender OR p.recipient_id = :uid_recipient)";
    $params['uid_sender'] = $user_id;
    $params['uid_recipient'] = $user_id;
}

// Фильтры по статусам
if ($filter === 'in_transit') {
    $where[] = "(SELECT status_text FROM parcel_status WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) REGEXP 'пути|доставке|сортировочный'";
} elseif ($filter === 'awaiting') {
    $where[] = "(SELECT status_text FROM parcel_status WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) LIKE '%ожидает%'";
} elseif ($filter === 'delivered') {
    $where[] = "(SELECT status_text FROM parcel_status WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) REGEXP 'выдана|доставлено'";
}

// Текстовый поиск
if ($q !== '') {
    $where[] = "(p.track_code LIKE :q OR p.address LIKE :q OR s.name LIKE :q OR r.name LIKE :q)";
    $params['q'] = '%' . $q . '%';
}

$sql = $baseSql;
if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY p.id DESC LIMIT 100";

$parcels = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $parcels = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("dashboard parcels error: " . $e->getMessage());
}

// Коды получения для текущего пользователя
$codes = [];
if ($role !== 'worker') {
    try {
        $stmt = $pdo->prepare("
            SELECT pc.*, p.track_code, s.name as sender_name
            FROM parcel_codes pc
            JOIN parcels p ON pc.parcel_id = p.id
            JOIN users s ON p.sender_id = s.id
            WHERE p.recipient_id = :uid AND pc.code_date = DATE(NOW())
            ORDER BY pc.id DESC
        ");
        $stmt->execute(['uid' => $user_id]);
        $codes = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("dashboard codes error: " . $e->getMessage());
    }
}

$page_title = "Дашборд - EHPST";
include __DIR__ . '/header.php';
?>

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card bg-primary text-white p-4 border-0 shadow-sm overflow-hidden position-relative rounded-4">
            <div class="position-relative z-1">
                <h1 class="h2 mb-1 fw-bold">Привет, <?php echo e($name); ?>! 👋</h1>
                <p class="mb-0 opacity-75">Ваш уникальный ID: <span class="fw-bold"><?php echo $user_id; ?></span>. Используйте его для отправки.</p>
            </div>
            <i class="bi bi-box-seam position-absolute end-0 bottom-0 mb-n4 me-n2 opacity-25" style="font-size: 8rem;"></i>
        </div>
    </div>

    <div class="col-12 d-flex gap-3 flex-wrap">
        <?php if ($role === 'worker'): ?>
            <a href="parcel_issue.php" class="btn btn-success rounded-pill px-4 shadow-sm"><i class="bi bi-box-arrow-right me-2"></i>Выдать посылку</a>
        <?php endif; ?>
        <a href="parcel_add.php" class="btn btn-primary rounded-pill px-4 shadow-sm"><i class="bi bi-plus-lg me-2"></i>Новая посылка</a>
        <button class="btn btn-outline-secondary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#notifModal">
            <i class="bi bi-bell me-2"></i>Уведомления
            <?php if ($unreadCount > 0): ?>
                <span class="badge bg-danger rounded-pill ms-1"><?php echo $unreadCount; ?></span>
            <?php endif; ?>
        </button>
    </div>

    <div class="col-md-6">
        <div class="card p-4 border-0 shadow-sm h-100 border-start border-primary border-5 rounded-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="small text-muted fw-bold text-uppercase mb-1" style="font-size: 0.7rem;">Всего отправлений</div>
                    <div class="h2 mb-0 fw-bold"><?php echo $total_parcels; ?></div>
                </div>
                <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary"><i class="bi bi-box fs-3"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card p-4 border-0 shadow-sm h-100 border-start border-success border-5 rounded-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="small text-muted fw-bold text-uppercase mb-1" style="font-size: 0.7rem;"><?php echo $role === 'worker' ? 'Общая выручка' : 'Мои расходы'; ?></div>
                    <div class="h2 mb-0 fw-bold text-success"><?php echo number_format($total_revenue, 2); ?> <span class="small">BYN</span></div>
                </div>
                <div class="bg-success bg-opacity-10 p-3 rounded-circle text-success"><i class="bi bi-wallet2 fs-3"></i></div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($codes)): ?>
<div class="card border-0 shadow-sm mb-4 bg-warning bg-opacity-10 rounded-4">
    <div class="card-body p-4">
        <div class="d-flex align-items-center mb-3">
            <i class="bi bi-shield-lock-fill fs-3 text-warning me-3"></i>
            <h5 class="mb-0 fw-bold">Коды выдачи (на сегодня):</h5>
        </div>
        <div class="row g-3">
            <?php foreach ($codes as $code): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="bg-white p-3 rounded-4 shadow-sm text-center border">
                        <div class="small text-muted mb-2 fw-bold x-small"><?php echo e($code['track_code']); ?></div>
                        <div class="h2 mb-0 fw-bold text-primary font-monospace"><?php echo e($code['code']); ?></div>
                        <?php if (!empty($code['secret_code'])): ?>
                            <div class="small text-danger mt-1 fw-bold">СЕКРЕТ: <?php echo e($code['secret_code']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4 rounded-4">
    <div class="card-body p-3">
        <form method="get" class="row g-2">
            <div class="col-lg-6">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0 rounded-pill-start ps-3"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control border-start-0 rounded-pill-end bg-light" placeholder="Трек, адрес или имя..." value="<?php echo e($q); ?>">
                </div>
            </div>
            <div class="col-lg-4">
                <select name="filter" class="form-select rounded-pill bg-light" onchange="this.form.submit()">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Все статусы</option>
                    <option value="in_transit" <?php echo $filter === 'in_transit' ? 'selected' : ''; ?>>В пути</option>
                    <option value="awaiting" <?php echo $filter === 'awaiting' ? 'selected' : ''; ?>>Ожидают</option>
                    <option value="delivered" <?php echo $filter === 'delivered' ? 'selected' : ''; ?>>Выданы</option>
                </select>
            </div>
            <div class="col-lg-2">
                <button class="btn btn-primary w-100 rounded-pill fw-bold">Найти</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Отправление</th>
                    <th>Участники</th>
                    <th>Параметры</th>
                    <th>Стоимость</th>
                    <th>Статус</th>
                    <th class="text-end pe-4">Управление</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($parcels): ?>
                    <?php foreach ($parcels as $parcel): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-primary"><?php echo e($parcel['track_code']); ?></div>
                                <div class="x-small text-muted fw-bold"><?php echo e(date('d.m.Y', strtotime($parcel['created_at']))); ?></div>
                            </td>
                            <td>
                                <div class="small fw-bold text-dark mb-1"><?php echo e($parcel['sender_name']); ?> → <?php echo e($parcel['recipient_name']); ?></div>
                                <div class="x-small text-muted text-truncate" style="max-width: 150px;"><?php echo e($parcel['address']); ?></div>
                            </td>
                            <td>
                                <div class="small fw-bold"><?php echo number_format($parcel['weight'], 3); ?> кг</div>
                                <div class="badge bg-info bg-opacity-10 text-info x-small mt-1"><?php echo e($parcel['tariff']); ?></div>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?php echo number_format($parcel['cost'], 2); ?> BYN</div>
                                <?php if($parcel['cod'] > 0): ?>
                                    <div class="x-small text-danger fw-bold mt-1">Н/П: <?php echo number_format($parcel['cod'], 2); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                    $status = $parcel['last_status'] ?: 'Оформлена';
                                    $badge_class = 'bg-primary';
                                    if (mb_stripos($status, 'ожидает') !== false) $badge_class = 'bg-warning text-dark';
                                    if (mb_stripos($status, 'выдана') !== false || mb_stripos($status, 'доставлено') !== false) $badge_class = 'bg-success';
                                ?>
                                <span class="badge <?php echo $badge_class; ?> bg-opacity-10 text-<?php echo str_replace('bg-', '', explode(' ', $badge_class)[0]); ?> px-3 py-2 fw-bold" style="font-size: 0.7rem;">
                                    <?php echo e($status); ?>
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-toggle="dropdown">Действия</button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 p-2">
                                        <li><a class="dropdown-item" href="parcel_history.php?id=<?php echo $parcel['id']; ?>"><i class="bi bi-clock-history me-2"></i>История</a></li>
                                        <li><a class="dropdown-item" href="label_print.php?id=<?php echo $parcel['id']; ?>"><i class="bi bi-printer me-2"></i>Печать</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="parcel_edit.php?id=<?php echo $parcel['id']; ?>"><i class="bi bi-pencil me-2"></i>Изменить</a></li>
                                        <?php if ($role === 'worker'): ?>
                                            <li><a class="dropdown-item text-success fw-bold" href="parcel_status.php?id=<?php echo $parcel['id']; ?>"><i class="bi bi-check-all me-2"></i>Статус</a></li>
                                        <?php endif; ?>
                                        <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="confirmDelete(<?php echo $parcel['id']; ?>, '<?php echo e($parcel['track_code']); ?>')"><i class="bi bi-trash me-2"></i>Удалить</a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted fw-bold">Список отправлений пуст</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Уведомления -->
<div class="modal fade" id="notifModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-light p-4 rounded-top-4">
                <h5 class="modal-title fw-bold text-dark">Уведомления</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="list-group list-group-flush">
                    <?php if ($notifications): ?>
                        <?php foreach ($notifications as $n): ?>
                            <div class="list-group-item p-4 border-0 border-bottom <?php echo $n['is_read'] ? '' : 'bg-primary bg-opacity-5'; ?>">
                                <div class="small text-muted mb-2 fw-bold x-small"><?php echo date('d.m.Y H:i', strtotime($n['created_at'])); ?></div>
                                <div class="small fw-bold mb-3"><?php echo e($n['message']); ?></div>
                                <div class="d-flex gap-3">
                                    <?php if (!$n['is_read']): ?>
                                        <a href="notifications.php?action=read&id=<?php echo $n['id']; ?>" class="x-small text-primary text-decoration-none fw-bold">ПРОЧИТАНО</a>
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

<script>
function confirmDelete(id, track) {
    if (confirm('Внимание! Вы собираетесь удалить посылку ' + track + '. Это действие нельзя отменить.')) {
        window.location.href = 'parcel_delete.php?id=' + id;
    }
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
