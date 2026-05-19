<?php
/**
 * dashboard.php — УЛЬТИМАТИВНЫЙ РАБОЧИЙ ДАШБОРД EHPST (v5.0)
 * Полная реставрация всех функций, оптимизация под мобильные и десктоп.
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

try {
    // АВТО-УВЕДОМЛЕНИЯ О СРОКАХ (для работников)
    if ($role === 'worker') {
        $stmt = $pdo->query("SELECT id, track_code, pickup_point FROM parcels
                             WHERE shelf IS NOT NULL AND stored_at IS NOT NULL
                             AND stored_at < DATE_SUB(NOW(), INTERVAL 14 DAY)
                             AND storage_notified = 0 AND is_return = 0");
        $expired = $stmt->fetchAll();
        foreach ($expired as $ex) {
            $msg = "Срок хранения посылки {$ex['track_code']} истёк ({$ex['pickup_point']}). Перемещена на полку возврата.";
            notifyUser(null, $msg, 'warning');
            $stmt = $pdo->prepare("UPDATE parcels SET storage_notified = 1, is_return = 1, shelf = 6 WHERE id = :id");
            $stmt->execute(['id' => $ex['id']]);
        }
    }

    // Уведомления
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
    $stmt->execute(['uid' => $user_id]);
    $unreadCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id, message, is_read FROM notifications WHERE user_id = :uid ORDER BY id DESC LIMIT 10");
    $stmt->execute(['uid' => $user_id]);
    $notifications = $stmt->fetchAll();

    // Статистика
    if ($role === 'worker') {
        $total_parcels = (int)$pdo->query("SELECT COUNT(*) FROM parcels")->fetchColumn();
        $total_revenue = (float)$pdo->query("SELECT IFNULL(SUM(cost),0) FROM parcels")->fetchColumn();
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM parcels WHERE sender_id = :sid OR recipient_id = :rid");
        $stmt->execute(['sid' => $user_id, 'rid' => $user_id]);
        $total_parcels = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT IFNULL(SUM(cost),0) FROM parcels WHERE sender_id = :sid");
        $stmt->execute(['sid' => $user_id]);
        $total_revenue = (float)$stmt->fetchColumn();
    }

    // СПИСОК ПОСЫЛОК
    $params = [];
    $where = [];
    if ($role !== 'worker') {
        $where[] = "((p.sender_id = :u_sid AND (p.is_deleted_by_sender = 0 OR p.is_deleted_by_sender IS NULL))
                     OR (p.recipient_id = :u_rid AND (p.is_deleted_by_recipient = 0 OR p.is_deleted_by_recipient IS NULL)))";
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

    $sql = "SELECT p.*, s.name AS s_name, s.login AS s_login, r.name AS r_name, r.login AS r_login,
            (SELECT status_text FROM parcel_status WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) AS last_status
            FROM parcels p
            LEFT JOIN users s ON p.sender_id = s.id
            LEFT JOIN users r ON p.recipient_id = r.id";
    if (!empty($where)) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY p.id DESC LIMIT 150";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $parcels = $stmt->fetchAll();
} catch (PDOException $e) {
    $db_error = $e->getMessage();
    $parcels = [];
}

try {
    // Посылки к выдаче (для кнопок QR)
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

    // Лояльность
    if (isset($_POST['issue_card'])) {
        $card_num = '5000' . str_pad(rand(0, 999999999999), 12, '0', STR_PAD_LEFT);
        try {
            $stmt = $pdo->prepare("INSERT INTO loyalty_cards (user_id, card_number, level, balance) VALUES (:uid, :cn, 'classic', 0)");
            $stmt->execute(['uid' => $user_id, 'cn' => $card_num]);
            header("Location: dashboard.php");
            exit;
        } catch (Exception $e) { $db_error = "Ошибка выпуска карты: " . $e->getMessage(); }
    }

    $stmt = $pdo->prepare("SELECT * FROM loyalty_cards WHERE user_id = :uid");
    $stmt->execute(['uid' => $user_id]);
    $loyalty_card = $stmt->fetch();

} catch (PDOException $e) { $db_error = $e->getMessage(); }

include __DIR__ . '/header.php';
?>

<style>
    :root { --purple: #6f42c1; --purple-dark: #59359a; }
    .dashboard-card { border-radius: 1.25rem; border: none; box-shadow: 0 8px 30px rgba(0,0,0,0.04); transition: 0.3s; }
    .btn-purple { background: var(--purple); color: #fff; border-radius: 50px; font-weight: 700; padding: 0.7rem 1.6rem; border: none; }
    .btn-purple:hover { background: var(--purple-dark); color: #fff; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(111, 66, 193, 0.3); }
    .status-badge { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; padding: 0.4rem 0.8rem; border-radius: 50px; }
    .parcel-row:hover { background: rgba(67, 97, 238, 0.02); }
    .qr-fab { position: fixed; bottom: 30px; right: 30px; z-index: 1050; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 25px rgba(25, 135, 84, 0.4); }
    .card-mobile { border-radius: 1rem; border: 1px solid rgba(0,0,0,0.05); margin-bottom: 1rem; }
</style>

<!-- Prominent Master QR Alert -->
<?php if ($has_awaiting && count($ready_to_pickup) > 1): ?>
<div class="alert alert-primary rounded-4 shadow-sm border-0 mb-4 animate-fade-in d-flex align-items-center justify-content-between p-4" style="background: linear-gradient(135deg, #4361ee, #4895ef); color: white;">
    <div class="d-flex align-items-center">
        <div class="bg-white bg-opacity-20 p-3 rounded-circle me-3">
            <i class="bi bi-qr-code-scan fs-3"></i>
        </div>
        <div>
            <h5 class="fw-bold mb-1">Готовы к выдаче: <?php echo count($ready_to_pickup); ?> посылки</h5>
            <p class="mb-0 opacity-75 small">Используйте один Мастер QR-код для получения всех отправлений сразу.</p>
        </div>
    </div>
    <a href="my_qr_codes.php" class="btn btn-white text-primary rounded-pill fw-bold px-4">ПОКАЗАТЬ QR</a>
</div>
<?php endif; ?>

<!-- Floating QR -->
<?php if (isset($db_error)): ?>
    <div class="alert alert-danger rounded-4 shadow-sm p-4 mb-4">
        <h4 class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Ошибка базы данных</h4>
        <p class="mb-0"><?php echo e($db_error); ?></p>
        <hr>
        <p class="small mb-0">Попробуйте запустить <a href="db_fix.php" class="fw-bold">скрипт исправления БД</a></p>
    </div>
<?php endif; ?>

<?php if ($has_awaiting): ?>
<a href="my_qr_codes.php" class="qr-fab btn btn-success animate-pulse"><i class="bi bi-qr-code fs-3"></i></a>
<?php endif; ?>

<div class="row g-4 animate-fade-in">
    <div class="col-lg-8">
        <!-- WELCOME -->
        <div class="card dashboard-card bg-primary text-white p-4 mb-4 position-relative overflow-hidden">
            <div class="position-relative z-index-2">
                <h3 class="fw-bold mb-1">Привет, <?php echo e($name); ?>! 👋</h3>
                <p class="opacity-75 mb-4">Система EHPST. Ваш ID: <span class="fw-bold">#<?php echo $user_id; ?></span></p>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="parcel_add.php" class="btn btn-purple shadow-sm"><i class="bi bi-plus-lg me-2"></i>Оформить</a>
                    <?php if($role === 'worker'): ?>
                        <a href="parcel_issue.php" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm border-0"><i class="bi bi-box-arrow-right me-2"></i>Выдача</a>
                        <a href="pvz_dashboard.php" class="btn btn-info text-white rounded-pill px-4 fw-bold shadow-sm border-0"><i class="bi bi-shop me-2"></i>Склад ПВЗ</a>
                        <a href="shift_manage.php" class="btn btn-dark rounded-pill px-4 fw-bold shadow-sm border-0"><i class="bi bi-calculator me-2"></i>Касса</a>
                    <?php endif; ?>
                    <?php if($has_awaiting): ?>
                        <a href="my_qr_codes.php" class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm text-dark border-0"><i class="bi bi-qr-code-scan me-2"></i>QR-КОДЫ</a>
                    <?php endif; ?>
                </div>
            </div>
            <i class="bi bi-lightning-charge position-absolute end-0 bottom-0 mb-n5 me-n4 opacity-10" style="font-size: 12rem;"></i>
        </div>

        <?php if($role === 'worker'): ?>
        <!-- WORKER TOOLS -->
        <div class="card dashboard-card bg-white p-4 mb-4 border-start border-4 border-success shadow-sm">
            <h6 class="fw-bold text-success text-uppercase small mb-3"><i class="bi bi-gear-fill me-2"></i>Инструменты сотрудника</h6>
            <div class="d-flex gap-2 flex-wrap">
                <a href="transfer_add.php" class="btn btn-outline-primary fw-bold rounded-pill px-3 btn-sm"><i class="bi bi-send-fill me-1"></i> Оформить перевод</a>
                <a href="transfer_list.php" class="btn btn-outline-primary fw-bold rounded-pill px-3 btn-sm"><i class="bi bi-cash-stack me-1"></i> Упр. переводами</a>
                <a href="parcel_cod_refund.php" class="btn btn-outline-danger fw-bold rounded-pill px-3 btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i> Возврат нал.плат.</a>
                <a href="transfer_refund_issue.php" class="btn btn-outline-danger fw-bold rounded-pill px-3 btn-sm"><i class="bi bi-arrow-return-left me-1"></i> Выплата возвр. пер.</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- PARCEL LIST -->
        <div class="card dashboard-card bg-white shadow-sm">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Список отправлений</h5>
                <form method="get" class="d-flex gap-2">
                    <input type="text" name="q" class="form-control form-control-sm rounded-pill px-3" placeholder="Поиск..." value="<?php echo e($q); ?>">
                    <select name="filter" class="form-select form-select-sm rounded-pill shadow-none" onchange="this.form.submit()">
                        <option value="all" <?php echo $filter==='all'?'selected':'';?>>Все</option>
                        <option value="in_transit" <?php echo $filter==='in_transit'?'selected':'';?>>В пути</option>
                        <option value="awaiting" <?php echo $filter==='awaiting'?'selected':'';?>>Ожидают</option>
                        <option value="delivered" <?php echo $filter==='delivered'?'selected':'';?>>Выданы</option>
                    </select>
                </form>
            </div>

            <!-- Desktop View -->
            <div class="table-responsive d-none d-md-block">
                <table class="table align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="x-small text-muted text-uppercase">
                            <th class="ps-4">Посылка</th>
                            <th>Маршрут</th>
                            <th>Статус / Оплата</th>
                            <th class="text-end pe-4">Действие</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($parcels): foreach($parcels as $p): ?>
                        <tr class="parcel-row border-bottom">
                            <td class="ps-4 py-3">
                                <div class="fw-bold text-dark mb-1"><?php echo e($p['track_code']); ?></div>
                                <div class="x-small text-muted fw-bold"><?php echo e($p['tariff']); ?> · <?php echo number_format($p['weight'], 3); ?> кг</div>
                                <?php if($p['delivery_partner']): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info x-small mt-1 fw-bold"><?php echo strtoupper(e($p['delivery_partner'])); ?></span>
                                <?php endif; ?>
                                <?php if ((int)$p['sender_id'] === $user_id && $p['is_cod_paid'] && !$p['is_cod_issued']): ?>
                                    <div class="mt-1"><span class="badge bg-primary px-2 py-1" style="font-size:0.6rem;">КОД ВЫПЛАТЫ: <?php echo e($p['cod_payout_code']); ?></span></div>
                                <?php endif; ?>
                                <?php if ((int)$p['sender_id'] === $user_id && $p['cod_return_required']): ?>
                                    <div class="mt-1"><span class="badge bg-danger px-2 py-1" style="font-size:0.6rem;">ДОЛГ ПО НАЛ.ПЛАТ: <?php echo number_format($p['cod'], 2); ?></span></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="small fw-bold text-dark mb-1"><?php echo e($p['s_name'] ?: $p['s_login']); ?> → <?php echo e($p['r_name'] ?: $p['r_login'] ?: $p['recipient_name_ext']); ?></div>
                                <div class="x-small text-muted text-truncate" style="max-width: 180px;"><i class="bi bi-geo-alt me-1"></i><?php echo e($p['address']); ?></div>
                            </td>
                            <td>
                                <div class="mb-1">
                                    <?php if ($p['is_paid']): ?><span class="badge bg-success bg-opacity-10 text-success x-small fw-bold">ОПЛАЧЕНО</span>
                                    <?php else: ?><span class="badge bg-danger bg-opacity-10 text-danger x-small fw-bold">ЖДЕТ ОПЛАТЫ</span><?php endif; ?>

                                    <?php if ($p['cod'] > 0): ?>
                                        <span class="badge <?php echo $p['is_cod_paid'] ? 'bg-success' : 'bg-warning'; ?> bg-opacity-10 text-<?php echo $p['is_cod_paid'] ? 'success' : 'dark'; ?> x-small fw-bold ms-1">НАЛ.ПЛ: <?php echo $p['is_cod_paid'] ? 'ОК' : 'ЖДЕТ'; ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php
                                    $st = $p['last_status'] ?: 'Оформлена';
                                    $cls = 'bg-primary';
                                    if(mb_stripos($st, 'ожидает')!==false || mb_stripos($st, 'прибыло')!==false) $cls='bg-warning text-dark';
                                    if(mb_stripos($st, 'выдана')!==false || mb_stripos($st, 'доставлено')!==false) $cls='bg-success text-white';
                                ?>
                                <span class="status-badge badge <?php echo $cls; ?> bg-opacity-10 text-<?php echo strpos($cls,'white')!==false?'success':str_replace('bg-','',$cls); ?>"><?php echo e($st); ?></span>
                            </td>
                            <td class="text-end pe-4">
                                <?php renderActionMenu($p, $user_id, $role); ?>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted fw-bold">Список посылок пуст</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile View -->
            <div class="d-md-none p-3">
                <?php if($parcels): foreach($parcels as $p): ?>
                    <div class="card card-mobile shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="fw-bold text-primary"><?php echo e($p['track_code']); ?></div>
                                <?php
                                    $st = $p['last_status'] ?: 'Оформлена';
                                    $cls = 'bg-primary';
                                    if(mb_stripos($st, 'ожидает')!==false || mb_stripos($st, 'прибыло')!==false) $cls='bg-warning text-dark';
                                    if(mb_stripos($st, 'выдана')!==false || mb_stripos($st, 'доставлено')!==false) $cls='bg-success text-white';
                                ?>
                                <span class="badge <?php echo $cls; ?> x-small fw-bold"><?php echo e($st); ?></span>
                            </div>
                            <div class="small mb-3">
                                <div><?php echo e($p['s_name'] ?: $p['s_login']); ?> → <?php echo e($p['r_name'] ?: $p['r_login'] ?: $p['recipient_name_ext']); ?></div>
                                <div class="text-muted x-small"><?php echo e($p['address']); ?></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="x-small">
                                    <?php if ($p['is_paid']): ?><span class="badge bg-success bg-opacity-10 text-success fw-bold">ОПЛАЧЕНО</span>
                                    <?php else: ?><span class="badge bg-danger bg-opacity-10 text-danger fw-bold">ЖДЕТ ОПЛАТЫ</span><?php endif; ?>
                                </div>
                                <div class="x-small text-muted fw-bold"><?php echo e($p['tariff']); ?></div>
                            </div>
                            <?php renderActionMenu($p, $user_id, $role, true); ?>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                    <div class="text-center py-4 text-muted">Нет посылок</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="col-lg-4">
        <!-- LOYALTY CARD -->
        <div class="card dashboard-card bg-dark text-white p-4 mb-4 shadow-lg border-top border-warning border-4">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h6 class="x-small text-uppercase opacity-50 fw-bold">Бонусный баланс</h6>
                    <h2 class="fw-bold mb-0 text-warning"><?php echo number_format($loyalty_card['balance']??0, 2); ?> <span class="fs-6 opacity-50">BYN</span></h2>
                </div>
                <i class="bi bi-credit-card-2-front fs-1 opacity-25"></i>
            </div>
            <?php if($loyalty_card): ?>
                <div class="small mb-1">Уровень: <span class="badge bg-primary"><?php echo strtoupper($loyalty_card['level']); ?></span></div>
                <div class="small fw-bold mb-3" style="letter-spacing: 2px; font-family: monospace;"><?php echo implode(' ', str_split($loyalty_card['card_number'], 4)); ?></div>
                <div class="p-2 bg-white rounded-3 d-inline-block">
                    <div id="card-qr"></div>
                </div>
            <?php else: ?>
                <p class="small opacity-75">Вы еще не активировали карту лояльности. Получайте кэшбэк до 20% с каждой посылки!</p>
                <form method="post"><button name="issue_card" class="btn btn-outline-warning btn-sm rounded-pill w-100 fw-bold">АКТИВИРОВАТЬ КАРТУ</button></form>
            <?php endif; ?>
        </div>

        <!-- NOTIFICATIONS -->
        <div class="card dashboard-card bg-white p-0 overflow-hidden shadow-sm">
            <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">Уведомления</h6>
                <?php if($unreadCount>0): ?><span class="badge bg-danger rounded-pill"><?php echo $unreadCount; ?></span><?php endif; ?>
            </div>
            <div class="list-group list-group-flush">
                <?php if($notifications): foreach($notifications as $n): ?>
                    <div class="list-group-item p-3 border-0 border-bottom <?php echo $n['is_read']?'':'bg-light'; ?>">
                        <div class="small mb-2 fw-bold text-dark" style="line-height:1.4"><?php echo e($n['message']); ?></div>
                        <div class="d-flex gap-3">
                            <?php if(!$n['is_read']): ?><a href="notifications.php?action=read&id=<?php echo $n['id']; ?>" class="x-small text-primary fw-bold text-decoration-none">ПРОЧИТАНО</a><?php endif; ?>
                            <a href="notifications.php?action=delete&id=<?php echo $n['id']; ?>" class="x-small text-danger fw-bold text-decoration-none">УДАЛИТЬ</a>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                    <div class="p-5 text-center text-muted small">Уведомлений нет</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
function renderActionMenu($p, $user_id, $role, $is_mobile = false) {
    $st = $p['last_status'] ?? '';
    $is_p_recip = ((int)$p['recipient_id'] === $user_id && (int)($p['is_return']??0) === 0);
    $is_p_sender_ret = ((int)$p['sender_id'] === $user_id && (int)($p['is_return']??0) === 1);
    ?>
    <div class="dropdown <?php echo $is_mobile ? 'd-grid' : ''; ?>">
        <button class="btn btn-light <?php echo $is_mobile ? 'btn-md' : 'btn-sm'; ?> rounded-pill px-3 shadow-none border fw-bold" data-bs-toggle="dropdown" data-bs-boundary="viewport">
            <i class="bi bi-three-dots<?php echo $is_mobile ? '-vertical' : ''; ?> me-1"></i> Опции
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 p-3" style="min-width: 280px; max-height: 80vh; overflow-y: auto; -webkit-overflow-scrolling: touch;">

            <?php if (($is_p_recip || $is_p_sender_ret) && (mb_stripos($st, 'ожидает') !== false || mb_stripos($st, 'прибыло') !== false)): ?>
                <li><a class="dropdown-item py-2 fw-bold text-success" href="my_qr_codes.php"><i class="bi bi-qr-code-scan me-2"></i>МАСТЕР QR (ВСЕ ПОСЫЛКИ)</a></li>
                <li><a class="dropdown-item py-2 fw-bold text-success" href="parcel_pickup_qr.php?id=<?php echo $p['id']; ?>"><i class="bi bi-qr-code me-2"></i>QR-КОД ЭТОЙ ПОСЫЛКИ</a></li>
                <li><hr class="dropdown-divider"></li>
            <?php endif; ?>

            <li><a class="dropdown-item py-2" href="parcel_history.php?id=<?php echo $p['id']; ?>"><i class="bi bi-clock-history me-2 text-primary"></i>История</a></li>
            <li><a class="dropdown-item py-2" href="label_print.php?id=<?php echo $p['id']; ?>"><i class="bi bi-printer me-2 text-primary"></i>Печать ярлыка</a></li>

            <?php if ($p['is_paid']): ?>
                <li><a class="dropdown-item py-2 fw-bold text-success" href="parcel_receipt.php?id=<?php echo $p['id']; ?>"><i class="bi bi-receipt me-2"></i>ЧЕК ОБ ОПЛАТЕ</a></li>
            <?php elseif ($role === 'worker' || (int)$p['sender_id'] === $user_id): ?>
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

            <?php if($role === 'worker'): ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item py-2 fw-bold text-success" href="parcel_status.php?id=<?php echo $p['id']; ?>"><i class="bi bi-plus-circle me-2"></i>Новый статус</a></li>
                <li><a class="dropdown-item py-2" href="parcel_edit.php?id=<?php echo $p['id']; ?>"><i class="bi bi-pencil me-2 text-warning"></i>Правка данных</a></li>
                <?php if($p['is_paid'] && !$p['is_return']): ?>
                    <li><a class="dropdown-item py-2 text-danger" href="javascript:void(0)" onclick="confReturn(<?php echo $p['id']; ?>, '<?php echo e($p['track_code']); ?>')"><i class="bi bi-arrow-return-left me-2"></i>Оформить возврат</a></li>
                <?php endif; ?>
                <?php if ($p['cod_return_required']): ?>
                    <li><a class="dropdown-item py-2 fw-bold text-danger" href="parcel_cod_repay.php?id=<?php echo $p['id']; ?>"><i class="bi bi-cash me-2"></i>ПРИНЯТЬ ДОЛГ ПО НАЛ.ПЛ.</a></li>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (($is_p_recip || $is_p_sender_ret) && (mb_stripos($st, 'ожидает') !== false || mb_stripos($st, 'прибыло') !== false)): ?>
                <?php if ($is_p_recip): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2 text-danger fw-bold" href="javascript:void(0)" onclick="confRefuse(<?php echo $p['id']; ?>, '<?php echo e($p['track_code']); ?>')"><i class="bi bi-x-circle me-2"></i>ОТКАЗ ОТ ПОСЫЛКИ</a></li>
                <?php endif; ?>
            <?php endif; ?>

            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item py-2 text-danger fw-bold" href="javascript:void(0)" onclick="confDel(<?php echo $p['id']; ?>, '<?php echo e($p['track_code']); ?>')"><i class="bi bi-trash3 me-2"></i>Удалить из списка</a></li>
        </ul>
    </div>
    <?php
}
?>

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
    new QRCode(document.getElementById("card-qr"), {
        text: "<?php echo $loyalty_card['card_number']; ?>",
        width: 100, height: 100
    });
<?php endif; ?>
</script>

<?php include __DIR__ . '/footer.php'; ob_end_flush(); ?>
