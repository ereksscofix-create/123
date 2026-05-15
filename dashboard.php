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
        (SELECT status_text FROM parcel_status WHERE parcel_id = p.id ORDER BY created_at DESC LIMIT 1) AS last_status,
        (SELECT created_at FROM parcel_status WHERE parcel_id = p.id ORDER BY created_at DESC LIMIT 1) AS last_update
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
    $where[] = "(SELECT status_text FROM parcel_status WHERE parcel_id = p.id ORDER BY created_at DESC LIMIT 1) REGEXP 'пути|доставке|сортировочный'";
} elseif ($filter === 'awaiting') {
    $where[] = "(SELECT status_text FROM parcel_status WHERE parcel_id = p.id ORDER BY created_at DESC LIMIT 1) LIKE '%ожидает%'";
} elseif ($filter === 'delivered') {
    $where[] = "(SELECT status_text FROM parcel_status WHERE parcel_id = p.id ORDER BY created_at DESC LIMIT 1) REGEXP 'выдана|доставлено'";
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
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 bg-white p-3 rounded shadow-sm">
    <div>
        <h1 class="h3 mb-0 fw-bold text-dark">Привет, <?php echo e($name); ?>! 👋</h1>
        <p class="text-muted mb-0 small">Ваш статус: <span class="badge bg-light text-primary"><?php echo $role === 'worker' ? 'Работник' : 'Пользователь'; ?></span></p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($role === 'worker'): ?>
            <a href="parcel_issue.php" class="btn btn-success px-3"><i class="bi bi-box-arrow-right me-2"></i>Выдать</a>
        <?php endif; ?>
        <a href="parcel_add.php" class="btn btn-primary px-3"><i class="bi bi-plus-lg me-2"></i>Оформить</a>

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
        <div class="card p-3 h-100 border-0 shadow-sm border-start border-primary border-4">
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
        <div class="card p-3 h-100 border-0 shadow-sm border-start border-success border-4">
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
<div class="alert alert-warning border-0 shadow-sm mb-4 py-3">
    <div class="d-flex align-items-center mb-2">
        <i class="bi bi-key-fill fs-4 me-2"></i>
        <h5 class="mb-0 fw-bold">Ваши коды получения на сегодня:</h5>
    </div>
    <div class="row g-2 mt-2">
        <?php foreach ($codes as $code): ?>
            <div class="col-md-4">
                <div class="bg-white p-3 rounded shadow-sm">
                    <div class="small text-muted mb-1 text-uppercase fw-bold" style="font-size: 0.7rem;">Трек: <?php echo e($code['track_code']); ?></div>
                    <div class="h3 mb-0 text-center text-primary font-monospace fw-bold"><?php echo e($code['code']); ?></div>
                    <?php if (!empty($code['secret_code'])): ?>
                        <div class="text-center small text-danger mt-1">Секрет: <strong><?php echo e($code['secret_code']); ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ФИЛЬТР И ПОИСК -->
<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body p-3">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-lg-5 col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0 bg-light" placeholder="Трек, адрес или имя..." value="<?php echo e($q); ?>">
                </div>
            </div>
            <div class="col-lg-5 col-md-6">
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="filter" id="f_all" value="all" <?php echo $filter === 'all' ? 'checked' : ''; ?> onchange="this.form.submit()">
                    <label class="btn btn-outline-primary" for="f_all">Все</label>

                    <input type="radio" class="btn-check" name="filter" id="f_transit" value="in_transit" <?php echo $filter === 'in_transit' ? 'checked' : ''; ?> onchange="this.form.submit()">
                    <label class="btn btn-outline-primary" for="f_transit">В пути</label>

                    <input type="radio" class="btn-check" name="filter" id="f_await" value="awaiting" <?php echo $filter === 'awaiting' ? 'checked' : ''; ?> onchange="this.form.submit()">
                    <label class="btn btn-outline-primary" for="f_await">Ожидают</label>

                    <input type="radio" class="btn-check" name="filter" id="f_delivered" value="delivered" <?php echo $filter === 'delivered' ? 'checked' : ''; ?> onchange="this.form.submit()">
                    <label class="btn btn-outline-primary" for="f_delivered">Выданы</label>
                </div>
            </div>
            <div class="col-lg-2">
                <button class="btn btn-primary w-100">Поиск</button>
            </div>
        </form>
    </div>
</div>

<!-- СПИСОК ПОСЫЛОК -->
<div class="card border-0 shadow-sm overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4 border-0">Отправление</th>
                    <th class="border-0">Маршрут</th>
                    <th class="border-0">Статус</th>
                    <th class="text-end pe-4 border-0">Управление</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($parcels): ?>
                    <?php foreach ($parcels as $parcel): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-primary"><?php echo e($parcel['track_code']); ?></div>
                                <div class="x-small text-muted"><?php echo date('d.m.Y', strtotime($parcel['created_at'])); ?></div>
                            </td>
                            <td>
                                <div class="small fw-semibold"><?php echo e($parcel['sender_name']); ?> → <?php echo e($parcel['recipient_name']); ?></div>
                                <div class="x-small text-muted text-truncate" style="max-width: 180px;"><?php echo e($parcel['address']); ?></div>
                            </td>
                            <td>
                                <?php
                                    $status = $parcel['last_status'] ?: 'Оформлена';
                                    $badge_class = 'bg-primary';
                                    if (mb_stripos($status, 'ожидает') !== false) $badge_class = 'bg-warning text-dark';
                                    if (mb_stripos($status, 'выдана') !== false || mb_stripos($status, 'доставлено') !== false) $badge_class = 'bg-success';
                                ?>
                                <span class="badge <?php echo $badge_class; ?> bg-opacity-10 text-<?php echo str_replace('bg-', '', explode(' ', $badge_class)[0]); ?> px-2 py-1" style="font-weight: 600;">
                                    <?php echo e($status); ?>
                                </span>
                                <div class="x-small text-muted mt-1"><?php echo $parcel['last_update'] ? date('d.m H:i', strtotime($parcel['last_update'])) : ''; ?></div>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group">
                                    <a href="parcel_history.php?id=<?php echo $parcel['id']; ?>" class="btn btn-sm btn-outline-light text-dark border" title="История"><i class="bi bi-clock-history"></i></a>
                                    <a href="label_print.php?id=<?php echo $parcel['id']; ?>" class="btn btn-sm btn-outline-light text-dark border" title="Печать"><i class="bi bi-printer"></i></a>
                                    <a href="parcel_edit.php?id=<?php echo $parcel['id']; ?>" class="btn btn-sm btn-outline-light text-dark border" title="Изменить"><i class="bi bi-pencil"></i></a>
                                    <?php if ($role === 'worker'): ?>
                                        <a href="parcel_status.php?id=<?php echo $parcel['id']; ?>" class="btn btn-sm btn-outline-success border" title="Сменить статус"><i class="bi bi-check-all"></i></a>
                                    <?php endif; ?>
                                    <button onclick="confirmDelete(<?php echo $parcel['id']; ?>, '<?php echo e($parcel['track_code']); ?>')" class="btn btn-sm btn-outline-danger border" title="Удалить"><i class="bi bi-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center py-5">
                            <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                            <div class="text-muted">Ничего не найдено</div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Уведомления -->
<div class="modal fade" id="notifModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">Уведомления</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="list-group list-group-flush">
                    <?php if ($notifications): ?>
                        <?php foreach ($notifications as $n): ?>
                            <div class="list-group-item p-3 <?php echo $n['is_read'] ? '' : 'bg-light'; ?>">
                                <div class="d-flex justify-content-between">
                                    <div class="small text-muted mb-1"><?php echo date('d.m.Y H:i', strtotime($n['created_at'])); ?></div>
                                    <?php if (!$n['is_read']): ?>
                                        <span class="badge bg-primary rounded-pill" style="width: 8px; height: 8px; padding: 0;"> </span>
                                    <?php endif; ?>
                                </div>
                                <div class="small <?php echo $n['is_read'] ? '' : 'fw-bold'; ?>"><?php echo e($n['message']); ?></div>
                                <div class="mt-2">
                                    <?php if (!$n['is_read']): ?>
                                        <a href="notifications.php?action=read&id=<?php echo $n['id']; ?>" class="x-small text-primary text-decoration-none me-2">Прочитано</a>
                                    <?php endif; ?>
                                    <a href="notifications.php?action=delete&id=<?php echo $n['id']; ?>" class="x-small text-danger text-decoration-none">Удалить</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">У вас нет новых уведомлений</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer bg-light p-2">
                <a href="notifications.php?action=read_all" class="btn btn-sm btn-link text-decoration-none">Прочитать все</a>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, track) {
    if (confirm('Вы действительно хотите безвозвратно удалить посылку ' + track + '?')) {
        window.location.href = 'parcel_delete.php?id=' + id;
    }
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
