<?php
// pvz_dashboard.php — Складской учет ПВЗ (для работников)
ob_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
if (!$user || $user['role'] !== 'worker') {
    header("Location: dashboard.php");
    exit;
}

$page_title = "Склад ПВЗ — EHPST";

// Фильтр по полке
$filter_shelf = isset($_GET['shelf']) ? (int)$_GET['shelf'] : 0;
// Фильтр по ПВЗ (если их несколько, пока берем первый или по поиску)
$filter_pvz = $_GET['pvz'] ?? 'Минск_ЕН_Main';

try {
    $where = ["p.shelf IS NOT NULL", "p.pickup_point = :pvz"];
    $params = ['pvz' => $filter_pvz];

    if ($filter_shelf > 0) {
        $where[] = "p.shelf = :shelf";
        $params['shelf'] = $filter_shelf;
    }

    // Исключаем выданные
    $sql = "SELECT p.*, COALESCE(r.name, r.login, p.recipient_name_ext) as r_name,
            (SELECT status_text FROM parcel_status WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) as last_status
            FROM parcels p
            LEFT JOIN users r ON p.recipient_id = r.id
            WHERE " . implode(" AND ", $where) . "
            AND NOT EXISTS (SELECT 1 FROM parcel_status WHERE parcel_id = p.id AND status_text LIKE 'Выдана%')
            ORDER BY p.shelf ASC, p.stored_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inventory = $stmt->fetchAll();

    // Группировка по полкам для статистики
    $shelves_stat = [];
    for($i=1; $i<=6; $i++) $shelves_stat[$i] = 0;

    $stmt_stat = $pdo->prepare("SELECT shelf, COUNT(*) as cnt FROM parcels
                                WHERE shelf IS NOT NULL AND pickup_point = :pvz
                                AND NOT EXISTS (SELECT 1 FROM parcel_status WHERE parcel_id = parcels.id AND status_text LIKE 'Выдана%')
                                GROUP BY shelf");
    $stmt_stat->execute(['pvz' => $filter_pvz]);
    while($row = $stmt_stat->fetch()) {
        $shelves_stat[(int)$row['shelf']] = (int)$row['cnt'];
    }

} catch (PDOException $e) { $error = $e->getMessage(); }

include __DIR__ . '/header.php';
?>

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 bg-primary text-white p-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="fw-bold mb-1">Склад ПВЗ: <?php echo e($filter_pvz); ?></h3>
                    <p class="mb-0 opacity-75">Учет посылок по местам хранения (полкам)</p>
                </div>
                <i class="bi bi-box-seam display-4 opacity-25"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach($shelves_stat as $s_num => $count): ?>
    <div class="col-md-2 col-6">
        <a href="?shelf=<?php echo $s_num; ?>&pvz=<?php echo urlencode($filter_pvz); ?>" class="text-decoration-none">
            <div class="card border-0 shadow-sm rounded-4 text-center p-3 <?php echo $filter_shelf === $s_num ? 'bg-primary text-white' : 'bg-white text-dark'; ?> h-100 transition-up">
                <div class="small fw-bold text-uppercase opacity-75">Полка</div>
                <div class="display-5 fw-extrabold"><?php echo $s_num; ?></div>
                <div class="badge <?php echo $count >= 10 ? 'bg-danger' : 'bg-success'; ?> rounded-pill mt-2"><?php echo $count; ?> / 10</div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
    <div class="col-md-12 col-12">
        <?php if($filter_shelf > 0): ?>
            <a href="?shelf=0&pvz=<?php echo urlencode($filter_pvz); ?>" class="btn btn-light rounded-pill px-4 border">Показать все полки</a>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white py-3 px-4 border-bottom">
        <h5 class="mb-0 fw-bold">Содержимое склада <?php echo $filter_shelf ? "(Полка $filter_shelf)" : "(Все полки)"; ?></h5>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="bg-light">
                <tr class="x-small text-muted text-uppercase">
                    <th class="ps-4">Полка</th>
                    <th>Трек-код</th>
                    <th>Получатель</th>
                    <th>Срок хранения</th>
                    <th class="text-end pe-4">Действие</th>
                </tr>
            </thead>
            <tbody>
                <?php if($inventory): foreach($inventory as $item):
                    $days = floor((time() - strtotime($item['stored_at'])) / 86400);
                    $row_cls = ($days > 10) ? 'table-danger' : (($days > 7) ? 'table-warning' : '');
                ?>
                <tr class="<?php echo $row_cls; ?> parcel-row">
                    <td class="ps-4">
                        <span class="badge bg-dark rounded-pill px-3">№<?php echo $item['shelf']; ?></span>
                    </td>
                    <td>
                        <div class="fw-bold text-primary"><?php echo e($item['track_code']); ?></div>
                        <div class="x-small text-muted"><?php echo e($item['tariff']); ?> · <?php echo number_format($item['weight'], 3); ?> кг</div>
                    </td>
                    <td>
                        <div class="small fw-bold"><?php echo e($item['r_name']); ?></div>
                        <div class="x-small text-muted"><?php echo e($item['last_status']); ?></div>
                    </td>
                    <td>
                        <div class="small <?php echo $days > 7 ? 'fw-bold text-danger' : ''; ?>">
                            <?php echo $days; ?> дн.
                            <span class="x-small opacity-75">(с <?php echo date('d.m', strtotime($item['stored_at'])); ?>)</span>
                        </div>
                    </td>
                    <td class="text-end pe-4">
                        <div class="btn-group">
                            <a href="parcel_issue.php?track=<?php echo $item['track_code']; ?>" class="btn btn-success btn-sm rounded-pill px-3 fw-bold">ВЫДАТЬ</a>
                            <button class="btn btn-light btn-sm rounded-pill ms-2 border" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 p-2">
                                <li><a class="dropdown-item py-2" href="parcel_history.php?id=<?php echo $item['id']; ?>"><i class="bi bi-clock-history me-2 text-primary"></i>История</a></li>
                                <li><a class="dropdown-item py-2" href="parcel_edit.php?id=<?php echo $item['id']; ?>"><i class="bi bi-pencil me-2 text-warning"></i>Переложить</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2 text-danger fw-bold" href="parcel_return.php?id=<?php echo $item['id']; ?>"><i class="bi bi-arrow-return-left me-2"></i>Оформить возврат</a></li>
                            </ul>
                        </div>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="5" class="text-center py-5 text-muted">Склад пуст или ничего не найдено по фильтрам.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.transition-up { transition: 0.3s; }
.transition-up:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
.parcel-row:hover { background-color: rgba(67, 97, 238, 0.02); }
</style>

<?php include __DIR__ . '/footer.php'; ?>
