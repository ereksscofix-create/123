<?php
// shift_manage.php — Управление сменами и отчеты
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/functions_finance.php';
checkLogin();

$user = currentUser();
if (!$user || $user['role'] !== 'worker') die("Доступ запрещен.");

$worker_id = (int)$user['id'];
$current_shift = getOpenShift($worker_id);

$action = $_GET['action'] ?? '';
$report_type = $_GET['report'] ?? ''; // x or z

// 1. ОТКРЫТИЕ СМЕНЫ
if ($action === 'open' && !$current_shift) {
    $stmt = $pdo->prepare("INSERT INTO shifts (worker_id) VALUES (:wid)");
    $stmt->execute(['wid' => $worker_id]);
    header("Location: shift_manage.php"); exit;
}

// 1.1 ВНЕСЕНИЕ / ИЗЪЯТИЕ
if (isset($_POST['cash_op']) && $current_shift) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) die("CSRF validation failed.");
    $amount = (float)$_POST['amount'];
    $op_type = $_POST['op_type']; // income (внесение) / expense (изъятие)
    $cat = ($op_type === 'income') ? 'Внесение наличных' : 'Изъятие наличных';
    if ($amount > 0) {
        logTransaction($current_shift['id'], $worker_id, $op_type, $cat, $amount);
        header("Location: shift_manage.php"); exit;
    }
}

// 2. ЗАКРЫТИЕ СМЕНЫ (Z-ОТЧЕТ)
if ($action === 'close' && $current_shift) {
    $stmt = $pdo->prepare("UPDATE shifts SET is_closed = 1, closed_at = NOW() WHERE id = :sid");
    $stmt->execute(['sid' => $current_shift['id']]);
    header("Location: shift_manage.php?report=z&shift_id=" . $current_shift['id']); exit;
}

// 3. ПРОСМОТР ОТЧЕТА (X или Z)
$report_shift_id = (int)($_GET['shift_id'] ?? ($current_shift['id'] ?? 0));
$stats = [];
$detailed_logs = [];
$shift_data = null;
if ($report_shift_id) {
    $stats = getShiftStats($report_shift_id);
    $detailed_logs = getShiftTransactions($report_shift_id);
    $stmt = $pdo->prepare("SELECT * FROM shifts WHERE id = :sid");
    $stmt->execute(['sid' => $report_shift_id]);
    $shift_data = $stmt->fetch();
}

$page_title = "Управление сменой — EHPST";
include __DIR__ . '/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-md-8">

        <?php if (!$current_shift && $report_type !== 'z'): ?>
            <div class="card shadow-lg border-0 rounded-4 text-center p-5">
                <div class="mb-4"><i class="bi bi-door-closed fs-1 text-muted"></i></div>
                <h3>Смена закрыта</h3>
                <p class="text-muted">Для проведения финансовых операций необходимо открыть смену.</p>
                <a href="shift_manage.php?action=open" class="btn btn-primary btn-lg rounded-pill px-5">ОТКРЫТЬ СМЕНУ</a>
            </div>
        <?php endif; ?>

        <?php if ($current_shift && $report_type !== 'z'): ?>
            <div class="card shadow-lg border-0 rounded-4 mb-4">
                <div class="card-header bg-primary text-white p-4 d-flex justify-content-between align-items-center">
                    <h4 class="mb-0 fw-bold">Текущая смена #<?php echo $current_shift['id']; ?></h4>
                    <span class="badge bg-white text-primary rounded-pill">ОТКРЫТА: <?php echo date('H:i', strtotime($current_shift['opened_at'])); ?></span>
                </div>
                <div class="card-body p-4 p-md-5">
                    <div class="row g-4 mb-5">
                        <div class="col-6">
                            <a href="shift_manage.php?report=x" class="btn btn-outline-primary w-100 py-3 rounded-4 fw-bold">
                                <i class="bi bi-file-earmark-bar-graph me-2"></i>X-ОТЧЕТ
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="shift_manage.php?action=close" class="btn btn-danger w-100 py-3 rounded-4 fw-bold" onclick="return confirm('Закрыть смену и распечатать Z-отчет?')">
                                <i class="bi bi-power me-2"></i>ЗАКРЫТЬ СМЕНУ
                            </a>
                        </div>
                    </div>

                    <h5 class="fw-bold mb-3">Денежные операции</h5>
                    <form method="post" class="row g-3 p-4 bg-light rounded-4 border">
                        <?php echo csrfInput(); ?>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold">Сумма (BYN)</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required placeholder="0.00">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Тип операции</label>
                            <select name="op_type" class="form-select">
                                <option value="income">Внесение (+)</option>
                                <option value="expense">Изъятие (-)</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" name="cash_op" class="btn btn-dark w-100 fw-bold">Выполнить</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- ОТОБРАЖЕНИЕ ОТЧЕТА -->
        <?php if ($report_type === 'x' || $report_type === 'z'): ?>
            <div class="report-container animate-fade-in">
                <div class="report shadow-lg p-5 bg-white rounded-4 border-top border-primary border-5" id="printableReport">
                    <div class="text-center mb-4">
                        <h2 class="fw-bold mb-0">EHPST POST</h2>
                        <div class="fw-bold"><?php echo ($report_type === 'z') ? 'Z-ОТЧЕТ (ИТОГОВЫЙ)' : 'X-ОТЧЕТ (ПРОВЕРОЧНЫЙ)'; ?></div>
                        <div class="small text-muted">Смена №<?php echo $report_shift_id; ?></div>
                    </div>

                    <div class="border-bottom pb-2 mb-3">
                        <div class="d-flex justify-content-between small"><span>ОПЕРАТОР:</span> <strong><?php echo e($user['name'] ?: $user['login']); ?></strong></div>
                        <div class="d-flex justify-content-between small"><span>ОТКРЫТА:</span> <span><?php echo $shift_data['opened_at']; ?></span></div>
                        <?php if($report_type === 'z'): ?>
                            <div class="d-flex justify-content-between small"><span>ЗАКРЫТА:</span> <span><?php echo $shift_data['closed_at']; ?></span></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <h6 class="fw-bold text-uppercase border-bottom pb-2">Выручка (Приход)</h6>
                        <?php
                        $total_income = 0;
                        foreach($stats as $s) {
                            if($s['type'] === 'income') {
                                echo "<div class='d-flex justify-content-between small'><span>{$s['category']}:</span> <strong>".number_format($s['total'], 2)." BYN</strong></div>";
                                $total_income += (float)$s['total'];
                            }
                        }
                        ?>
                        <div class="d-flex justify-content-between fw-bold mt-2 pt-2 border-top"><span>ИТОГО ПРИХОД:</span> <span><?php echo number_format($total_income, 2); ?> BYN</span></div>
                    </div>

                    <div class="mb-4">
                        <h6 class="fw-bold text-uppercase border-bottom pb-2">Выплаты (Расход)</h6>
                        <?php
                        $total_expense = 0;
                        foreach($stats as $s) {
                            if($s['type'] === 'expense') {
                                echo "<div class='d-flex justify-content-between small'><span>{$s['category']}:</span> <strong>".number_format($s['total'], 2)." BYN</strong></div>";
                                $total_expense += (float)$s['total'];
                            }
                        }
                        ?>
                        <div class="d-flex justify-content-between fw-bold mt-2 pt-2 border-top"><span>ИТОГО РАСХОД:</span> <span><?php echo number_format($total_expense, 2); ?> BYN</span></div>
                    </div>

                    <div class="bg-light p-3 rounded-3 text-center mb-4">
                        <div class="small fw-bold">НАЛИЧНОСТЬ В КАССЕ:</div>
                        <div class="h3 mb-0 fw-extrabold text-primary"><?php echo number_format($total_income - $total_expense, 2); ?> BYN</div>
                    </div>

                    <div class="detailed-report mt-5">
                        <h6 class="fw-bold text-uppercase border-bottom pb-2 mb-3">Детальный журнал операций</h6>
                        <div class="table-responsive">
                            <table class="table table-sm x-small" style="font-size: 11px;">
                                <thead>
                                    <tr class="text-muted">
                                        <th>Время</th>
                                        <th>Тип</th>
                                        <th>Категория</th>
                                        <th class="text-end">Сумма</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($detailed_logs as $log): ?>
                                        <tr>
                                            <td><?php echo date('H:i:s', strtotime($log['created_at'])); ?></td>
                                            <td class="fw-bold <?php echo $log['type'] === 'income' ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $log['type'] === 'income' ? 'ПРИХОД' : 'РАСХОД'; ?>
                                            </td>
                                            <td><?php echo e($log['category']); ?></td>
                                            <td class="text-end fw-bold"><?php echo number_format($log['amount'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="text-center mt-5 d-print-none">
                        <button class="btn btn-primary rounded-pill px-4" onclick="window.print()"><i class="bi bi-printer me-2"></i>Печать отчета</button>
                        <a href="dashboard.php" class="btn btn-light rounded-pill px-4 ms-2">В дашборд</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<style>
@media print {
    body * { visibility: hidden; }
    #printableReport, #printableReport * { visibility: visible; }
    #printableReport { position: absolute; left: 0; top: 0; width: 100%; border: none; box-shadow: none; }
    .d-print-none { display: none !important; }
}
</style>

<?php include __DIR__ . '/footer.php'; ?>
