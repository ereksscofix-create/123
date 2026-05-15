<?php
// dashboard.php — обновлённый рабочий дашборд
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Проверяем авторизацию
checkLogin();

// Получаем текущего пользователя
$user = currentUser();

// Если currentUser() вернул null (например, сессия есть, но в БД юзер удален)
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
        (SELECT status_text FROM parcel_status WHERE parcel_id = p.id ORDER BY created_at DESC LIMIT 1) AS last_status,
        (SELECT created_at FROM parcel_status WHERE parcel_id = p.id ORDER BY created_at DESC LIMIT 1) AS last_update
    FROM parcels p
    JOIN users s ON p.sender_id = s.id
    JOIN users r ON p.recipient_id = r.id
";

// Ограничение по пользователю, если не worker
if ($role !== 'worker') {
    $where[] = "(p.sender_id = :uid_sender OR p.recipient_id = :uid_recipient)";
    $params['uid_sender'] = $user_id;
    $params['uid_recipient'] = $user_id;
}

// Фильтры по статусам
if ($filter === 'in_transit') {
    $where[] = "EXISTS (SELECT 1 FROM parcel_status ps WHERE ps.parcel_id = p.id AND (ps.status_text LIKE '%пути%' OR ps.status_text LIKE '%доставке%') ORDER BY ps.created_at DESC LIMIT 1)";
} elseif ($filter === 'awaiting') {
    $where[] = "EXISTS (SELECT 1 FROM parcel_status ps WHERE ps.parcel_id = p.id AND ps.status_text LIKE '%ожидает%' ORDER BY ps.created_at DESC LIMIT 1)";
} elseif ($filter === 'delivered') {
    $where[] = "EXISTS (SELECT 1 FROM parcel_status ps WHERE ps.parcel_id = p.id AND (ps.status_text LIKE '%выдана%' OR ps.status_text LIKE '%доставлено%') ORDER BY ps.created_at DESC LIMIT 1)";
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
$sql .= " ORDER BY p.created_at DESC LIMIT 100";

$parcels = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $parcels = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("dashboard parcels error: " . $e->getMessage());
}

// Коды получения для текущего пользователя (только если он получатель)
$codes = [];
if ($role !== 'worker') {
    try {
        $stmt = $pdo->prepare("
            SELECT pc.*, p.track_code, s.name as sender_name
            FROM parcel_codes pc
            JOIN parcels p ON pc.parcel_id = p.id
            JOIN users s ON p.sender_id = s.id
            WHERE p.recipient_id = :uid AND pc.code_date = DATE(NOW())
            ORDER BY pc.created_at DESC
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

<!-- Верхняя панель действий -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h1 class="h3 mb-0">Привет, <?php echo e($name); ?>! 👋</h1>
        <p class="text-muted mb-0 small">Сегодня <?php echo date('d.m.Y'); ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($role === 'worker'): ?>
            <a href="parcel_issue.php" class="btn btn-success"><i class="bi bi-box-arrow-right"></i> Выдать посылку</a>
        <?php endif; ?>
        <a href="parcel_add.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Оформить посылку</a>

        <!-- Кнопка уведомлений -->
        <button class="btn btn-outline-secondary position-relative" data-bs-toggle="modal" data-bs-target="#notifModal">
            <i class="bi bi-bell"></i>
            <?php if ($unreadCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    <?php echo $unreadCount; ?>
                </span>
            <?php endif; ?>
        </button>
    </div>
</div>

<!-- СТАТИСТИКА -->
<div class="row g-3 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="card p-3 h-100">
            <div class="d-flex align-items-center">
                <div class="bg-primary bg-opacity-10 p-3 rounded-3 me-3">
                    <i class="bi bi-box-seam text-primary fs-4"></i>
                </div>
                <div>
                    <div class="small text-muted">Посылок</div>
                    <div class="h4 mb-0 fw-bold"><?php echo $total_parcels; ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card p-3 h-100">
            <div class="d-flex align-items-center">
                <div class="bg-success bg-opacity-10 p-3 rounded-3 me-3">
                    <i class="bi bi-wallet2 text-success fs-4"></i>
                </div>
                <div>
                    <div class="small text-muted"><?php echo $role === 'worker' ? 'Выручка' : 'Сумма'; ?></div>
                    <div class="h4 mb-0 fw-bold text-success"><?php echo number_format($total_revenue, 2); ?> <span class="small fs-6">BYN</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Коды получения -->
<?php if (!empty($codes)): ?>
<div class="alert alert-info border-0 shadow-sm mb-4">
    <h5 class="alert-heading fw-bold"><i class="bi bi-key-fill me-2"></i>Ваши коды получения на сегодня:</h5>
    <div class="row g-2 mt-2">
        <?php foreach ($codes as $code): ?>
            <div class="col-md-4">
                <div class="bg-white p-2 rounded border border-info border-opacity-25">
                    <div class="small text-muted mb-1">Трек: <strong><?php echo e($code['track_code']); ?></strong></div>
                    <div class="h4 mb-0 text-center text-primary font-monospace"><?php echo e($code['code']); ?></div>
                    <?php if (!empty($code['secret_code'])): ?>
                        <div class="text-center small text-danger mt-1">Секрет: <?php echo e($code['secret_code']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ФИЛЬТР И ПОИСК -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-lg-7 col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Поиск по треку, адресу или имени..." value="<?php echo e($q); ?>">
                </div>
            </div>
            <div class="col-lg-3 col-md-4">
                <select name="filter" class="form-select">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Все отправления</option>
                    <option value="in_transit" <?php echo $filter === 'in_transit' ? 'selected' : ''; ?>>В пути / доставке</option>
                    <option value="awaiting" <?php echo $filter === 'awaiting' ? 'selected' : ''; ?>>Ожидает вручения</option>
                    <option value="delivered" <?php echo $filter === 'delivered' ? 'selected' : ''; ?>>Выдано / доставлено</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-2">
                <button class="btn btn-primary w-100">Найти</button>
            </div>
        </form>
    </div>
</div>

<!-- СПИСОК ПОСЫЛОК -->
<div class="card border-0 shadow-sm overflow-hidden">
    <div class="card-header bg-white border-bottom py-3">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">Список отправлений</h5>
            <span class="badge bg-light text-dark fw-normal"><?php echo count($parcels); ?> записей</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Трек-код</th>
                    <th>Участники</th>
                    <th>Адрес</th>
                    <th>Статус</th>
                    <th class="text-end pe-4">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($parcels): ?>
                    <?php foreach ($parcels as $parcel): ?>
                        <tr>
                            <td class="ps-4">
                                <span class="fw-bold text-primary"><?php echo e($parcel['track_code']); ?></span><br>
                                <small class="text-muted"><?php echo date('d.m.Y', strtotime($parcel['created_at'])); ?></small>
                            </td>
                            <td>
                                <div class="small">
                                    <span class="text-muted">От:</span> <?php echo e($parcel['sender_name']); ?><br>
                                    <span class="text-muted">Кому:</span> <?php echo e($parcel['recipient_name']); ?>
                                </div>
                            </td>
                            <td>
                                <div class="small text-truncate" style="max-width: 200px;" title="<?php echo e($parcel['address']); ?>">
                                    <?php echo e($parcel['address']); ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-opacity-10 text-primary bg-primary badge-status">
                                    <?php echo e($parcel['last_status'] ?: 'Оформлена'); ?>
                                </span><br>
                                <small class="text-muted x-small"><?php echo $parcel['last_update'] ? date('d.m H:i', strtotime($parcel['last_update'])) : ''; ?></small>
                            </td>
                            <td class="text-end pe-4">
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        Действия
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                        <li><a class="dropdown-item" href="parcel_history.php?id=<?php echo $parcel['id']; ?>"><i class="bi bi-clock-history me-2"></i>История</a></li>
                                        <li><a class="dropdown-item" href="label_print.php?id=<?php echo $parcel['id']; ?>"><i class="bi bi-printer me-2"></i>Ярлык (A4)</a></li>
                                        <li><a class="dropdown-item" href="label_print.php?id=<?php echo $parcel['id']; ?>&with_secret=1"><i class="bi bi-printer-fill me-2"></i>Ярлык + Код</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="parcel_edit.php?id=<?php echo $parcel['id']; ?>"><i class="bi bi-pencil me-2"></i>Редактировать</a></li>
                                        <?php if ($role === 'worker'): ?>
                                            <li><a class="dropdown-item text-success" href="parcel_status.php?id=<?php echo $parcel['id']; ?>"><i class="bi bi-check2-circle me-2"></i>Сменить статус</a></li>
                                        <?php endif; ?>
                                        <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="confirmDelete(<?php echo $parcel['id']; ?>, '<?php echo e($parcel['track_code']); ?>')"><i class="bi bi-trash me-2"></i>Удалить</a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Посылок не найдено
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Уведомления -->
<div class="modal fade" id="notifModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Уведомления</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="list-group list-group-flush">
                    <?php if ($notifications): ?>
                        <?php foreach ($notifications as $n): ?>
                            <div class="list-group-item p-3 <?php echo $n['is_read'] ? '' : 'bg-light'; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="me-3">
                                        <div class="small text-muted mb-1"><?php echo date('d.m.Y H:i', strtotime($n['created_at'])); ?></div>
                                        <div class="<?php echo $n['is_read'] ? '' : 'fw-bold'; ?>"><?php echo $n['message']; ?></div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <?php if (!$n['is_read']): ?>
                                            <a href="notifications.php?action=read&id=<?php echo $n['id']; ?>" class="btn btn-sm btn-outline-primary" title="Прочитано"><i class="bi bi-check2"></i></a>
                                        <?php endif; ?>
                                        <a href="notifications.php?action=delete&id=<?php echo $n['id']; ?>" class="btn btn-sm btn-outline-danger" title="Удалить"><i class="bi bi-x-lg"></i></a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">Уведомлений нет</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <a href="notifications.php?action=read_all" class="btn btn-sm btn-link text-decoration-none">Отметить все как прочитанные</a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, track) {
    if (confirm('Вы уверены, что хотите удалить посылку ' + track + '?')) {
        window.location.href = 'parcel_delete.php?id=' + id;
    }
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
