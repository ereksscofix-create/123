<?php
// transfer_list.php — Список и управление денежными переводами
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
if (!$user || $user['role'] !== 'worker') die("Доступ запрещен.");

$q = trim($_GET['q'] ?? '');
$status_filter = $_GET['status'] ?? 'all';

try {
    $params = [];
    $where = [];

    if ($q !== '') {
        $where[] = "(mt.transfer_code LIKE :q OR s.name LIKE :q OR s.login LIKE :q OR r.name LIKE :q OR r.login LIKE :q)";
        $params['q'] = "%$q%";
    }

    if ($status_filter !== 'all') {
        $where[] = "mt.status = :status";
        $params['status'] = $status_filter;
    }

    $sql = "SELECT mt.*,
            s.name as s_name, s.login as s_login,
            r.name as r_name, r.login as r_login
            FROM money_transfers mt
            LEFT JOIN users s ON mt.sender_id = s.id
            LEFT JOIN users r ON mt.recipient_id = r.id";

    if (!empty($where)) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY mt.id DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transfers = $stmt->fetchAll();

} catch (PDOException $e) { die("Ошибка БД: " . $e->getMessage()); }

$page_title = "Управление переводами — EHPST";
include __DIR__ . '/header.php';
?>

<div class="mb-4 d-flex justify-content-between align-items-center">
    <h2 class="fw-bold mb-0">Денежные переводы</h2>
    <a href="transfer_add.php" class="btn btn-primary rounded-pill"><i class="bi bi-plus-lg me-2"></i>Новый перевод</a>
</div>

<div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-3">
        <form method="get" class="row g-2">
            <div class="col-md-6">
                <input type="text" name="q" class="form-control border-0 bg-light rounded-pill px-4" placeholder="Поиск по коду, имени..." value="<?php echo e($q); ?>">
            </div>
            <div class="col-md-4">
                <select name="status" class="form-select border-0 bg-light rounded-pill px-4">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Все статусы</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Ожидает оплаты</option>
                    <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Оплачен (готов к выдаче)</option>
                    <option value="issued" <?php echo $status_filter === 'issued' ? 'selected' : ''; ?>>Выдан</option>
                    <option value="refund_pending" <?php echo $status_filter === 'refund_pending' ? 'selected' : ''; ?>>Возврат (ожидает выплаты)</option>
                    <option value="refunded" <?php echo $status_filter === 'refunded' ? 'selected' : ''; ?>>Возвращен</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-dark w-100 rounded-pill">Найти</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr class="x-small text-uppercase text-muted">
                    <th class="ps-4 py-3">Код / Дата</th>
                    <th class="py-3">Отправитель → Получатель</th>
                    <th class="py-3 text-center">Сумма</th>
                    <th class="py-3">Статус</th>
                    <th class="pe-4 py-3 text-end">Действие</th>
                </tr>
            </thead>
            <tbody>
                <?php if($transfers): ?>
                    <?php foreach($transfers as $t): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?php echo e($t['transfer_code']); ?></div>
                                <div class="x-small text-muted"><?php echo date('d.m.Y H:i', strtotime($t['created_at'])); ?></div>
                            </td>
                            <td>
                                <div class="small fw-bold"><?php echo e($t['s_name'] ?: $t['s_login']); ?></div>
                                <div class="small text-muted">↓ <?php echo e($t['r_name'] ?: $t['r_login']); ?></div>
                            </td>
                            <td class="text-center">
                                <div class="fw-bold text-success"><?php echo number_format($t['amount'], 2); ?> BYN</div>
                                <div class="x-small text-muted">Комиссия: <?php echo number_format($t['fee'], 2); ?></div>
                            </td>
                            <td>
                                <?php
                                    $st = $t['status'];
                                    $class = "bg-secondary";
                                    $label = $st;
                                    if ($st === 'pending') { $class = "bg-warning text-dark"; $label = "ОЖИДАЕТ ОПЛАТЫ"; }
                                    if ($st === 'paid') { $class = "bg-primary"; $label = "ОПЛАЧЕН"; }
                                    if ($st === 'issued') { $class = "bg-success"; $label = "ВЫДАН"; }
                                    if ($st === 'refund_pending') { $class = "bg-danger"; $label = "ВОЗВРАТ"; }
                                    if ($st === 'refunded') { $class = "bg-dark"; $label = "ВОЗВРАЩЕН"; }
                                ?>
                                <span class="badge <?php echo $class; ?> rounded-pill px-3 py-2 fw-bold" style="font-size: 0.65rem;">
                                    <?php echo $label; ?>
                                </span>
                            </td>
                            <td class="pe-4 text-end">
                                <?php if($st === 'paid'): ?>
                                    <a href="transfer_refund.php?id=<?php echo $t['id']; ?>" class="btn btn-outline-danger btn-sm rounded-pill fw-bold">
                                        <i class="bi bi-arrow-return-left me-1"></i>ВЕРНУТЬ
                                    </a>
                                <?php endif; ?>
                                <?php if($st === 'refund_pending'): ?>
                                    <a href="transfer_refund_issue.php?q=<?php echo $t['refund_code']; ?>" class="btn btn-danger btn-sm rounded-pill fw-bold">
                                        ВЫПЛАТИТЬ ВОЗВРАТ
                                    </a>
                                <?php endif; ?>
                                <?php if($st === 'paid'): ?>
                                    <a href="transfer_issue.php" class="btn btn-success btn-sm rounded-pill fw-bold ms-1">ВЫДАТЬ</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted">Переводов не найдено</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="text-center mt-4">
    <a href="dashboard.php" class="btn btn-link text-muted">Назад в дашборд</a>
</div>

<?php include __DIR__ . '/footer.php'; ?>
