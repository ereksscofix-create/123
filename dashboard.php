<?php
/**
 * dashboard.php — ОПТИМИЗИРОВАННЫЙ ДАШБОРД EHPST (v3.0)
 */
ob_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/functions_finance.php';

checkLogin();
$user = currentUser();
if (!$user) {
    session_unset(); session_destroy();
    header('Location: login.php'); exit;
}

$user_id = (int)$user['id'];
$role = $user['role'] ?? 'recipient';
$name = $user['name'] ?: ($user['login'] ?: 'Пользователь');

// Параметры
$filter = $_GET['filter'] ?? 'all';
$q = trim((string)($_GET['q'] ?? ''));
$page_title = "Дашборд — EHPST";

try {
    // 1. УВЕДОМЛЕНИЯ
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
    $stmt->execute(['uid' => $user_id]);
    $unreadCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id, message, is_read FROM notifications WHERE user_id = :uid ORDER BY id DESC LIMIT 10");
    $stmt->execute(['uid' => $user_id]);
    $notifications = $stmt->fetchAll();

    // 2. СТАТИСТИКА
    if ($role === 'worker') {
        $total_parcels = (int)$pdo->query("SELECT COUNT(*) FROM parcels")->fetchColumn();
        $total_revenue = (float)$pdo->query("SELECT IFNULL(SUM(cost),0) FROM parcels")->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM parcels WHERE sender_id = :uid OR recipient_id = :uid");
        $stmt->execute(['uid' => $user_id]);
        $total_parcels = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT IFNULL(SUM(cost),0) FROM parcels WHERE sender_id = :uid");
        $stmt->execute(['uid' => $user_id]);
        $total_revenue = (float)$stmt->fetchColumn();
    }

    // 3. СПИСОК ПОСЫЛОК (Оптимизированный запрос)
    $params = [];
    $where = [];

    if ($role !== 'worker') {
        $where[] = "((p.sender_id = :u_sid AND p.is_deleted_by_sender = 0)
                     OR (p.recipient_id = :u_rid AND p.is_deleted_by_recipient = 0))";
        $params['u_sid'] = $user_id;
        $params['u_rid'] = $user_id;
    }

    if ($filter === 'in_transit') {
        $where[] = "EXISTS (SELECT 1 FROM parcel_status WHERE parcel_id = p.id AND status_text REGEXP 'пути|доставке|центр' ORDER BY id DESC LIMIT 1)";
    } elseif ($filter === 'awaiting') {
        $where[] = "EXISTS (SELECT 1 FROM parcel_status WHERE parcel_id = p.id AND status_text LIKE '%ожидает%' ORDER BY id DESC LIMIT 1)";
    } elseif ($filter === 'delivered') {
        $where[] = "EXISTS (SELECT 1 FROM parcel_status WHERE parcel_id = p.id AND status_text REGEXP 'выдана|доставлено' ORDER BY id DESC LIMIT 1)";
    }

    if ($q !== '') {
        $where[] = "(p.track_code LIKE :q OR p.address LIKE :q OR p.recipient_name_ext LIKE :q OR s.name LIKE :q OR r.name LIKE :q)";
        $params['q'] = "%$q%";
    }

    $sql = "SELECT p.*,
            s.name AS s_name, s.login AS s_login,
            r.name AS r_name, r.login AS r_login,
            (SELECT status_text FROM parcel_status WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) AS last_status
            FROM parcels p
            LEFT JOIN users s ON p.sender_id = s.id
            LEFT JOIN users r ON p.recipient_id = r.id";

    if (!empty($where)) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY p.id DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $parcels = $stmt->fetchAll();

    // 4. ПОСЫЛКИ К ВЫДАЧЕ
    $ready_to_pickup = [];
    foreach ($parcels as $p) {
        $st = $p['last_status'] ?? '';
        $is_r = ((int)$p['recipient_id'] === $user_id && (int)($p['is_return']??0) === 0);
        $is_s = ((int)$p['sender_id'] === $user_id && (int)($p['is_return']??0) === 1);
        if (($is_r || $is_s) && (mb_stripos($st, 'ожидает') !== false || mb_stripos($st, 'прибыло') !== false)) {
            $ready_to_pickup[] = $p;
        }
    }
    $has_awaiting = !empty($ready_to_pickup);

    // 5. КАРТА ЛОЯЛЬНОСТИ
    $stmt = $pdo->prepare("SELECT * FROM loyalty_cards WHERE user_id = :uid");
    $stmt->execute(['uid' => $user_id]);
    $loyalty_card = $stmt->fetch();

} catch (PDOException $e) { $db_error = $e->getMessage(); }

include __DIR__ . '/header.php';
?>

<style>
    .dashboard-card { border-radius: 1.2rem; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.05); transition: 0.3s; }
    .btn-purple { background: #6f42c1; color: #fff; border-radius: 50px; font-weight: 700; padding: 0.6rem 1.5rem; }
    .btn-purple:hover { background: #59359a; color: #fff; transform: translateY(-2px); }
    .status-badge { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; padding: 0.5rem 1rem; border-radius: 50px; }
    .qr-fab { position: fixed; bottom: 30px; right: 30px; z-index: 1000; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 25px rgba(25, 135, 84, 0.4); }
    .parcel-row:hover { background: rgba(67, 97, 238, 0.03); }
</style>

<!-- Floating QR Button -->
<?php if ($has_awaiting): ?>
<a href="my_qr_codes.php" class="qr-fab btn btn-success pulse-animation">
    <i class="bi bi-qr-code fs-3"></i>
</a>
<?php endif; ?>

<div class="row g-4">
    <!-- ЛЕВАЯ КОЛОНКА -->
    <div class="col-lg-8">
        <!-- ВЕЛКАМ-БЛОК -->
        <div class="card dashboard-card bg-primary text-white p-4 mb-4 position-relative overflow-hidden">
            <div class="position-relative z-index-2">
                <h3 class="fw-bold mb-1">Привет, <?php echo e($name); ?>!</h3>
                <p class="opacity-75">Рады видеть вас в системе EHPST. Ваш ID: <span class="fw-bold">#<?php echo $user_id; ?></span></p>
                <div class="d-flex gap-2 mt-4 flex-wrap">
                    <a href="parcel_add.php" class="btn btn-purple shadow-sm"><i class="bi bi-plus-lg me-2"></i>Оформить</a>
                    <?php if($role === 'worker'): ?>
                        <a href="parcel_issue.php" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm border-0"><i class="bi bi-box-arrow-right me-2"></i>Выдача</a>
                        <a href="shift_manage.php" class="btn btn-dark rounded-pill px-4 fw-bold shadow-sm border-0"><i class="bi bi-calculator me-2"></i>Касса</a>
                    <?php endif; ?>
                    <?php if($has_awaiting): ?>
                        <a href="my_qr_codes.php" class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm text-dark border-0"><i class="bi bi-qr-code-scan me-2"></i>QR-коды</a>
                    <?php endif; ?>
                </div>
            </div>
            <i class="bi bi-box-seam position-absolute end-0 bottom-0 mb-n4 me-n3 opacity-10" style="font-size: 10rem;"></i>
        </div>

        <?php if($role === 'worker'): ?>
        <!-- ПАНЕЛЬ УПРАВЛЕНИЯ ФИНАНСАМИ ДЛЯ РАБОТНИКА -->
        <div class="card dashboard-card bg-white p-4 mb-4 border-start border-4 border-success">
            <h6 class="fw-bold text-success text-uppercase small mb-3"><i class="bi bi-gear-fill me-2"></i>Инструменты сотрудника</h6>
            <div class="d-flex gap-2 flex-wrap">
                <a href="transfer_add.php" class="btn btn-outline-primary fw-bold rounded-pill px-3">
                    <i class="bi bi-send-fill me-1"></i> Оформить перевод
                </a>
                <a href="transfer_list.php" class="btn btn-outline-primary fw-bold rounded-pill px-3">
                    <i class="bi bi-cash-stack me-1"></i> Список переводов
                </a>
                <a href="parcel_cod_refund.php" class="btn btn-outline-danger fw-bold rounded-pill px-3">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Возврат нал.плат.
                </a>
                <a href="transfer_refund_issue.php" class="btn btn-outline-danger fw-bold rounded-pill px-3">
                    <i class="bi bi-arrow-return-left me-1"></i> Выплата возвр. перевода
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- ПОСЫЛКИ К ВЫДАЧЕ -->
        <?php if ($has_awaiting): ?>
        <div class="alert alert-warning dashboard-card border-0 p-4 mb-4 d-flex align-items-center justify-content-between">
            <div>
                <h5 class="fw-bold text-dark mb-1">📦 Готовы к получению: <?php echo count($ready_to_pickup); ?></h5>
                <p class="mb-0 small text-muted">Покажите QR-код сотруднику почты для быстрого получения.</p>
            </div>
            <a href="my_qr_codes.php" class="btn btn-dark rounded-pill fw-bold">СМОТРЕТЬ ВСЕ</a>
        </div>
        <?php endif; ?>

        <!-- СПИСОК ПОСЫЛОК -->
        <div class="card dashboard-card bg-white overflow-hidden">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Мои отправления</h5>
                <form method="get" class="d-flex gap-2">
                    <select name="filter" class="form-select form-select-sm rounded-pill shadow-none" onchange="this.form.submit()">
                        <option value="all" <?php echo $filter==='all'?'selected':'';?>>Все</option>
                        <option value="in_transit" <?php echo $filter==='in_transit'?'selected':'';?>>В пути</option>
                        <option value="awaiting" <?php echo $filter==='awaiting'?'selected':'';?>>Ожидают</option>
                        <option value="delivered" <?php echo $filter==='delivered'?'selected':'';?>>Выданы</option>
                    </select>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="x-small text-muted text-uppercase">
                            <th class="ps-4">Трек-код</th>
                            <th>Маршрут</th>
                            <th>Статус</th>
                            <th class="text-end pe-4">Действие</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($parcels): foreach($parcels as $p): ?>
                        <tr class="parcel-row">
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?php echo e($p['track_code']); ?></div>
                                <div class="x-small text-muted"><?php echo e($p['tariff']); ?> · <?php echo number_format($p['weight'],2); ?> кг</div>
                                <?php if($p['delivery_partner']): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info x-small mt-1"><?php echo e($p['delivery_partner']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="small fw-bold"><?php echo e($p['s_name']?:$p['s_login']); ?> → <?php echo e($p['r_name']?:$p['r_login']?:$p['recipient_name_ext']); ?></div>
                                <div class="x-small text-muted text-truncate" style="max-width: 150px;"><?php echo e($p['address']); ?></div>
                            </td>
                            <td>
                                <?php
                                    $st = $p['last_status'] ?: 'Оформлена';
                                    $cls = 'bg-primary';
                                    if(mb_stripos($st, 'ожидает')!==false) $cls='bg-warning text-dark';
                                    if(mb_stripos($st, 'выдана')!==false || mb_stripos($st, 'доставлено')!==false) $cls='bg-success';
                                ?>
                                <span class="status-badge badge <?php echo $cls; ?> bg-opacity-10 text-<?php echo str_replace('bg-','',$cls); ?>"><?php echo e($st); ?></span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm rounded-pill px-3" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4">
                                        <li><a class="dropdown-item py-2" href="parcel_history.php?id=<?php echo $p['id']; ?>"><i class="bi bi-clock-history me-2 text-primary"></i>История</a></li>
                                        <?php if($role === 'worker'): ?>
                                            <li><a class="dropdown-item py-2 fw-bold text-success" href="parcel_status.php?id=<?php echo $p['id']; ?>"><i class="bi bi-plus-circle me-2"></i>Новый статус</a></li>
                                            <li><a class="dropdown-item py-2" href="parcel_edit.php?id=<?php echo $p['id']; ?>"><i class="bi bi-pencil me-2 text-warning"></i>Правка</a></li>
                                            <?php if($p['pickup_point'] && !$p['shelf']): ?>
                                                <li><a class="dropdown-item py-2 fw-bold text-primary" href="parcel_pvz_receive.php?id=<?php echo $p['id']; ?>"><i class="bi bi-download me-2"></i>ПРИНЯТЬ В ПВЗ</a></li>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php
                                            $is_r = ((int)$p['recipient_id'] === $user_id && (int)($p['is_return']??0) === 0);
                                            $is_s = ((int)$p['sender_id'] === $user_id && (int)($p['is_return']??0) === 1);
                                            if (($is_r || $is_s) && (mb_stripos($st, 'ожидает') !== false || mb_stripos($st, 'прибыло') !== false)):
                                        ?>
                                            <li><a class="dropdown-item py-2 fw-bold text-success" href="parcel_pickup_qr.php?id=<?php echo $p['id']; ?>"><i class="bi bi-qr-code me-2"></i>QR-КОД</a></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">Ничего не найдено</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ПРАВАЯ КОЛОНКА -->
    <div class="col-lg-4">
        <!-- БАЛАНС / ЛОЯЛЬНОСТЬ -->
        <div class="card dashboard-card bg-dark text-white p-4 mb-4">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h6 class="x-small text-uppercase opacity-50 fw-bold">Бонусный баланс</h6>
                    <h2 class="fw-bold mb-0"><?php echo number_format($loyalty_card['balance']??0, 2); ?> <span class="fs-6 opacity-50">BYN</span></h2>
                </div>
                <i class="bi bi-credit-card fs-1 opacity-25"></i>
            </div>
            <?php if($loyalty_card): ?>
                <div class="small mb-1">Уровень: <span class="badge bg-primary"><?php echo strtoupper($loyalty_card['level']); ?></span></div>
                <div class="small fw-bold mb-3" style="letter-spacing: 1.5px;"><?php echo implode(' ', str_split($loyalty_card['card_number'], 4)); ?></div>
                <div class="p-2 bg-white rounded-3 d-inline-block">
                    <div id="mini-qr"></div>
                </div>
            <?php else: ?>
                <p class="small opacity-75">Вы еще не участвуете в программе лояльности.</p>
                <form method="post"><button name="issue_card" class="btn btn-outline-light btn-sm rounded-pill w-100 fw-bold">АКТИВИРОВАТЬ КАРТУ</button></form>
            <?php endif; ?>
        </div>

        <!-- УВЕДОМЛЕНИЯ -->
        <div class="card dashboard-card bg-white p-0 overflow-hidden">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between">
                <h6 class="mb-0 fw-bold">Уведомления</h6>
                <?php if($unreadCount>0): ?><span class="badge bg-danger rounded-pill"><?php echo $unreadCount; ?></span><?php endif; ?>
            </div>
            <div class="list-group list-group-flush">
                <?php if($notifications): foreach($notifications as $n): ?>
                    <div class="list-group-item p-3 border-0 border-bottom <?php echo $n['is_read']?'':'bg-light'; ?>">
                        <div class="small mb-1"><?php echo e($n['message']); ?></div>
                        <div class="d-flex gap-2">
                            <?php if(!$n['is_read']): ?><a href="notifications.php?action=read&id=<?php echo $n['id']; ?>" class="x-small text-primary fw-bold text-decoration-none">ПРОЧИТАНО</a><?php endif; ?>
                            <a href="notifications.php?action=delete&id=<?php echo $n['id']; ?>" class="x-small text-danger fw-bold text-decoration-none">УДАЛИТЬ</a>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                    <div class="p-4 text-center text-muted small">Уведомлений нет</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
function confDel(id, track) {
    if (confirm('ВНИМАНИЕ! Посылка ' + track + ' будет полностью удалена из системы. Продолжить?')) {
        window.location.href = 'parcel_delete.php?id=' + id;
    }
}
function confReturn(id, track) {
    if (confirm('Оформить возврат для посылки ' + track + '? Она будет отправлена обратно отправителю.')) {
        window.location.href = 'parcel_return.php?id=' + id;
    }
}
function confRefuse(id, track) {
    if (confirm('Вы действительно хотите ОТКАЗАТЬСЯ от получения посылки ' + track + '? Она будет немедленно отправлена обратно.')) {
        window.location.href = 'parcel_refuse.php?id=' + id;
    }
}

<?php if($loyalty_card): ?>
    new QRCode(document.getElementById("mini-qr"), {
        text: "<?php echo $loyalty_card['card_number']; ?>",
        width: 80, height: 80
    });
<?php endif; ?>
</script>

<?php include __DIR__ . '/footer.php'; ob_end_flush(); ?>
