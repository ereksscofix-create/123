<?php
// pvz_dashboard.php — УЛЬТИМАТИВНЫЙ СКЛАДСКОЙ ХАБ (v2.0)
ob_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
if (!$user || $user['role'] !== 'worker') {
    header("Location: dashboard.php");
    exit;
}

$page_title = "Складской Хаб ПВЗ — EHPST";

// Параметры фильтрации
$filter_shelf = isset($_GET['shelf']) ? (int)$_GET['shelf'] : 0;
$filter_pvz = $_GET['pvz'] ?? 'Минск_ЕН_Main';
$q = trim($_GET['q'] ?? '');

try {
    // 1. СТАТИСТИКА СКЛАДА
    $stmt_stat = $pdo->prepare("
        SELECT shelf, COUNT(*) as cnt, AVG(DATEDIFF(NOW(), stored_at)) as avg_days
        FROM parcels
        WHERE shelf IS NOT NULL AND pickup_point = :pvz
        AND NOT EXISTS (SELECT 1 FROM parcel_status WHERE parcel_id = parcels.id AND (status_text LIKE 'Выдана%' OR status_text LIKE 'Возврат выдан%'))
        GROUP BY shelf
    ");
    $stmt_stat->execute(['pvz' => $filter_pvz]);
    $shelf_data = $stmt_stat->fetchAll(PDO::FETCH_UNIQUE);

    $total_on_stock = 0;
    foreach($shelf_data as $sd) $total_on_stock += $sd['cnt'];

    // 2. ПОИСК И СПИСОК
    $where = ["p.shelf IS NOT NULL", "p.pickup_point = :pvz"];
    $params = ['pvz' => $filter_pvz];

    if ($filter_shelf > 0) {
        $where[] = "p.shelf = :shelf";
        $params['shelf'] = $filter_shelf;
    }

    if ($q !== '') {
        $where[] = "(p.track_code LIKE :q OR r.name LIKE :q OR r.login LIKE :q OR p.recipient_name_ext LIKE :q)";
        $params['q'] = "%$q%";
    }

    $sql = "SELECT p.*, COALESCE(r.name, r.login, p.recipient_name_ext) as r_name,
            (SELECT status_text FROM parcel_status WHERE parcel_id = p.id ORDER BY id DESC LIMIT 1) as last_status
            FROM parcels p
            LEFT JOIN users r ON p.recipient_id = r.id
            WHERE " . implode(" AND ", $where) . "
            AND NOT EXISTS (SELECT 1 FROM parcel_status WHERE parcel_id = p.id AND (status_text LIKE 'Выдана%' OR status_text LIKE 'Возврат выдан%'))
            ORDER BY p.shelf ASC, p.stored_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inventory = $stmt->fetchAll();

    // 3. АНАЛИТИКА: ТОП КЛИЕНТОВ (кто больше всего не забирает)
    $stmt_top = $pdo->prepare("
        SELECT COALESCE(r.name, r.login, p.recipient_name_ext) as name, COUNT(*) as cnt
        FROM parcels p
        LEFT JOIN users r ON p.recipient_id = r.id
        WHERE p.shelf IS NOT NULL AND p.pickup_point = :pvz
        AND NOT EXISTS (SELECT 1 FROM parcel_status WHERE parcel_id = p.id AND (status_text LIKE 'Выдана%' OR status_text LIKE 'Возврат выдан%'))
        GROUP BY name ORDER BY cnt DESC LIMIT 5
    ");
    $stmt_top->execute(['pvz' => $filter_pvz]);
    $top_clients = $stmt_top->fetchAll();

} catch (PDOException $e) { $error = $e->getMessage(); }

include __DIR__ . '/header.php';
?>

<style>
    .shelf-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; border: 2px solid transparent; }
    .shelf-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); border-color: var(--primary); }
    .shelf-card.active { border-color: var(--primary); background: rgba(67, 97, 238, 0.05); }
    .progress-thin { height: 6px; border-radius: 10px; }
    .stats-icon { width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 12px; }
    .bg-soft-primary { background: rgba(67, 97, 238, 0.1); color: var(--primary); }
    .bg-soft-success { background: rgba(6, 199, 85, 0.1); color: var(--success); }
    .bg-soft-warning { background: rgba(255, 149, 0, 0.1); color: var(--warning); }
    .btn-soft-success { background: rgba(6, 199, 85, 0.1); border: 1px solid rgba(6, 199, 85, 0.2); }
    .btn-soft-dark { background: rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.1); }
    .btn-soft-danger { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); }
    .heat-map { display: flex; gap: 4px; height: 40px; align-items: flex-end; }
    .heat-bar { flex: 1; border-radius: 2px; }
    .animate-slide-up { animation: slideUp 0.3s ease-out; }
    @keyframes slideUp { from { transform: translate(-50%, 100%); } to { transform: translate(-50%, 0); } }
</style>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 p-4 h-100" style="background: linear-gradient(135deg, #4361ee, #3f37c9); color: #fff;">
            <div class="row align-items-center h-100">
                <div class="col-md-7">
                    <h2 class="fw-extrabold mb-1">Складской Хаб v2.0</h2>
                    <p class="opacity-75 mb-4">Пункт выдачи: <span class="fw-bold text-info"><?php echo e($filter_pvz); ?></span></p>
                    <div class="d-flex gap-4">
                        <div>
                            <div class="x-small text-uppercase opacity-50 fw-bold">На остатке</div>
                            <div class="h3 fw-bold mb-0 text-info"><?php echo $total_on_stock; ?></div>
                        </div>
                        <div class="border-start border-white border-opacity-10 ps-4">
                            <div class="x-small text-uppercase opacity-50 fw-bold">Загрузка</div>
                            <div class="h3 fw-bold mb-0 text-success"><?php echo round(($total_on_stock / 60) * 100); ?>%</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-5 d-none d-md-block text-end">
                    <div class="heat-map px-3">
                        <?php for($i=1;$i<=6;$i++):
                            $h = ($shelf_data[$i]['cnt'] ?? 0) * 10;
                            $c = $h > 80 ? '#ef4444' : ($h > 50 ? '#f59e0b' : '#10b981');
                        ?>
                            <div class="heat-bar" style="height: <?php echo max(5, $h); ?>%; background: <?php echo $c; ?>;" title="Полка <?php echo $i; ?>: <?php echo ($shelf_data[$i]['cnt'] ?? 0); ?> шт"></div>
                        <?php endfor; ?>
                    </div>
                    <div class="x-small text-uppercase opacity-50 fw-bold mt-2">Карта загрузки полок</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold text-uppercase small mb-0">Быстрые действия</h6>
                <span class="badge bg-primary rounded-pill x-small">HOTKEYS</span>
            </div>
            <div class="d-grid gap-2">
                <button class="btn btn-outline-primary btn-sm rounded-pill text-start py-2" onclick="document.querySelector('input[name=q]').focus()">
                    <i class="bi bi-search me-2"></i> Быстрый поиск (Alt+F)
                </button>
                <?php if($filter_shelf > 0 && !empty($inventory)): ?>
                    <a href="shelf_labels_print.php?shelf=<?php echo $filter_shelf; ?>&pvz=<?php echo urlencode($filter_pvz); ?>" class="btn btn-soft-primary btn-sm rounded-pill text-start py-2 text-primary">
                        <i class="bi bi-printer me-2"></i> Печать всех ярлыков полки
                    </a>
                <?php endif; ?>
                <a href="parcel_issue.php" class="btn btn-soft-success btn-sm rounded-pill text-start py-2 text-success">
                    <i class="bi bi-qr-code-scan me-2"></i> Открыть сканер
                </a>
                <a href="shift_manage.php" class="btn btn-soft-dark btn-sm rounded-pill text-start py-2 text-dark">
                    <i class="bi bi-calculator me-2"></i> Работа с кассой
                </a>
            </div>
        </div>
        <div class="card border-0 shadow-sm rounded-4 p-4">
            <h6 class="fw-bold text-uppercase small mb-3">Ожидание выдачи (ТОП)</h6>
            <?php foreach($top_clients as $tc): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-medium text-truncate" style="max-width: 150px;"><?php echo e($tc['name']); ?></span>
                    <span class="badge bg-soft-primary rounded-pill"><?php echo $tc['cnt']; ?> шт.</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Shelf Selector -->
<div class="row g-3 mb-4">
    <?php for($i=1; $i<=6; $i++):
        $cnt = $shelf_data[$i]['cnt'] ?? 0;
        $pct = ($cnt / 10) * 100;
        $color = $cnt >= 9 ? 'danger' : ($cnt >= 6 ? 'warning' : 'success');
    ?>
    <div class="col-md-2 col-6">
        <div class="card shelf-card shadow-sm rounded-4 p-3 <?php echo $filter_shelf === $i ? 'active' : ''; ?>" onclick="location.href='?shelf=<?php echo $i; ?>&pvz=<?php echo urlencode($filter_pvz); ?>'">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="stats-icon bg-soft-<?php echo $color; ?>"><i class="bi bi-layers-half"></i></div>
                <div class="fw-extrabold fs-4">#<?php echo $i; ?></div>
            </div>
            <div class="small fw-bold text-muted text-uppercase mb-1">Полка</div>
            <div class="progress progress-thin mb-1">
                <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $pct; ?>%"></div>
            </div>
            <div class="d-flex justify-content-between x-small fw-bold">
                <span class="text-<?php echo $color; ?>"><?php echo $cnt; ?>/10</span>
                <span class="text-muted"><?php echo round($shelf_data[$i]['avg_days'] ?? 0, 1); ?> дн.</span>
            </div>
        </div>
    </div>
    <?php endfor; ?>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-5">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3">
        <h5 class="mb-0 fw-bold">Инвентаризация склада <?php echo $filter_shelf ? "(Полка $filter_shelf)" : "(Все полки)"; ?></h5>
        <form method="get" class="d-flex gap-2">
            <input type="hidden" name="shelf" value="<?php echo $filter_shelf; ?>">
            <input type="hidden" name="pvz" value="<?php echo e($filter_pvz); ?>">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 rounded-start-pill"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control border-start-0 rounded-end-pill px-3" placeholder="Трек, имя..." value="<?php echo e($q); ?>">
            </div>
            <button class="btn btn-primary rounded-pill px-4">Найти</button>
        </form>
    </div>

    <div class="table-responsive">
        <form id="bulkForm" method="post" action="parcel_bulk_action.php">
        <table class="table align-middle mb-0">
            <thead class="bg-light">
                <tr class="x-small text-muted text-uppercase">
                    <th class="ps-4" style="width: 40px;"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                    <th>Полка</th>
                    <th>Отправление</th>
                    <th>Получатель</th>
                    <th>Хранение</th>
                    <th class="text-end pe-4">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php if($inventory): foreach($inventory as $item):
                    $days = floor((time() - strtotime($item['stored_at'])) / 86400);
                    $row_cls = ($days > 10) ? 'table-danger' : (($days > 7) ? 'table-warning' : '');
                ?>
                <tr class="<?php echo $row_cls; ?> parcel-row border-bottom">
                    <td class="ps-4">
                        <input type="checkbox" name="parcel_ids[]" value="<?php echo $item['id']; ?>" class="form-check-input parcel-check">
                    </td>
                    <td>
                        <span class="badge bg-dark rounded-pill px-3">№<?php echo $item['shelf']; ?></span>
                    </td>
                    <td>
                        <div class="fw-bold text-primary mb-0"><?php echo e($item['track_code']); ?></div>
                        <div class="x-small fw-bold text-muted"><?php echo e($item['tariff']); ?> · <?php echo number_format($item['weight'], 3); ?> кг</div>
                        <?php if($item['is_paid']): ?>
                            <span class="badge bg-soft-success x-small mt-1">ОПЛАЧЕНО</span>
                        <?php else: ?>
                            <span class="badge bg-soft-warning x-small mt-1 text-danger">ЖДЕТ ОПЛАТЫ</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="small fw-bold text-dark"><?php echo e($item['r_name']); ?></div>
                        <div class="x-small text-muted text-truncate" style="max-width: 200px;"><?php echo e($item['last_status']); ?></div>
                    </td>
                    <td>
                        <div class="small fw-bold <?php echo $days > 7 ? 'text-danger' : 'text-dark'; ?>">
                            <?php echo $days; ?> дн.
                            <div class="progress progress-thin mt-1" style="width: 40px;">
                                <div class="progress-bar <?php echo $days > 7 ? 'bg-danger' : 'bg-primary'; ?>" style="width: <?php echo min(100, ($days/14)*100); ?>%"></div>
                            </div>
                        </div>
                    </td>
                    <td class="text-end pe-4">
                        <div class="d-flex justify-content-end gap-2">
                            <a href="parcel_issue.php?track=<?php echo $item['track_code']; ?>" class="btn btn-success btn-sm rounded-pill px-3 fw-bold">ВЫДАТЬ</a>
                            <div class="dropdown">
                                <button class="btn btn-light btn-sm rounded-circle border shadow-none" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 p-2">
                                    <li><a class="dropdown-item py-2" href="parcel_history.php?id=<?php echo $item['id']; ?>"><i class="bi bi-clock-history me-2 text-primary"></i>История</a></li>
                                    <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="moveShelf(<?php echo $item['id']; ?>, <?php echo $item['shelf']; ?>)"><i class="bi bi-arrow-left-right me-2 text-warning"></i>Переложить</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item py-2 text-danger fw-bold" href="parcel_return.php?id=<?php echo $item['id']; ?>"><i class="bi bi-arrow-return-left me-2"></i>Оформить возврат</a></li>
                                </ul>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="6" class="text-center py-5 text-muted fw-bold">Склад пуст или ничего не найдено</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        </form>
    </div>
</div>

<!-- Floating Bulk Action Bar -->
<div id="bulkBar" class="position-fixed bottom-0 start-50 translate-middle-x mb-4 shadow-lg rounded-pill bg-dark p-2 px-4 animate-slide-up" style="display:none; z-index: 1060; min-width: 320px;">
    <div class="d-flex align-items-center justify-content-between gap-3">
        <div class="text-white small fw-bold"><span id="selectedCount">0</span> выбрано</div>
        <div class="vr bg-white opacity-25"></div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm rounded-pill" onclick="bulkMove()"><i class="bi bi-arrow-left-right me-1"></i> Переложить</button>
            <button class="btn btn-warning btn-sm rounded-pill" onclick="bulkReturn()"><i class="bi bi-arrow-return-left me-1"></i> Возврат</button>
            <button class="btn btn-danger btn-sm rounded-pill" onclick="bulkDelete()"><i class="bi bi-trash3"></i></button>
        </div>
    </div>
</div>

<!-- Quick Audit / Move Modal -->
<div class="modal fade" id="moveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Место хранения</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="moveForm" method="post">
                    <input type="hidden" name="parcel_id" id="modalParcelId">
                    <input type="hidden" name="action" value="move_shelf">
                    <div class="mb-4">
                        <label class="form-label small text-muted text-uppercase fw-bold">Новая полка (1-6)</label>
                        <div class="d-flex gap-2">
                            <?php for($i=1;$i<=6;$i++): ?>
                                <input type="radio" class="btn-check" name="new_shelf" id="ns_<?php echo $i; ?>" value="<?php echo $i; ?>">
                                <label class="btn btn-outline-primary flex-grow-1 fw-extrabold" for="ns_<?php echo $i; ?>"><?php echo $i; ?></label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-bold">СОХРАНИТЬ</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('keydown', function(e) {
    if (e.altKey && (e.key === 'f' || e.key === 'а')) {
        e.preventDefault();
        document.querySelector('input[name=q]').focus();
    }
});

function moveShelf(id, current) {
    document.getElementById('modalParcelId').value = id;
    document.getElementById('moveForm').action = 'parcel_edit_internal.php';
    const radio = document.getElementById('ns_' + current);
    if(radio) radio.checked = true;
    new bootstrap.Modal(document.getElementById('moveModal')).show();
}

// Bulk Logic
const bulkBar = document.getElementById('bulkBar');
const selectAll = document.getElementById('selectAll');
const checks = document.querySelectorAll('.parcel-check');
const selectedCount = document.getElementById('selectedCount');

function updateBulkBar() {
    const checkedCount = document.querySelectorAll('.parcel-check:checked').length;
    bulkBar.style.display = checkedCount > 0 ? 'block' : 'none';
    selectedCount.textContent = checkedCount;
}

if(selectAll) {
    selectAll.addEventListener('change', () => {
        checks.forEach(c => c.checked = selectAll.checked);
        updateBulkBar();
    });
}

checks.forEach(c => c.addEventListener('change', updateBulkBar));

function bulkMove() {
    document.getElementById('modalParcelId').value = 'bulk';
    document.getElementById('moveForm').action = 'parcel_bulk_action.php';
    new bootstrap.Modal(document.getElementById('moveModal')).show();
}

function bulkReturn() {
    if(confirm('Оформить возврат для всех выбранных посылок?')) {
        const form = document.getElementById('bulkForm');
        const action = document.createElement('input');
        action.type = 'hidden'; action.name = 'action'; action.value = 'bulk_return';
        form.appendChild(action);
        form.submit();
    }
}

function bulkDelete() {
    if(confirm('ВНИМАНИЕ! Удалить все выбранные посылки из системы БЕЗВОЗВРАТНО?')) {
        const form = document.getElementById('bulkForm');
        const action = document.createElement('input');
        action.type = 'hidden'; action.name = 'action'; action.value = 'bulk_delete';
        form.appendChild(action);
        form.submit();
    }
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
