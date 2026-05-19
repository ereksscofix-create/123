<?php
// shelf_labels_print.php — Массовая печать ярлыков для полки
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

$user = currentUser();
if (!$user || $user['role'] !== 'worker') die("Access denied");

$shelf = (int)($_GET['shelf'] ?? 0);
$pvz = $_GET['pvz'] ?? '';

if (!$shelf || !$pvz) die("Invalid parameters");

try {
    $stmt = $pdo->prepare("SELECT * FROM parcels WHERE shelf = :s AND pickup_point = :pvz AND NOT EXISTS (SELECT 1 FROM parcel_status WHERE parcel_id = parcels.id AND status_text LIKE 'Выдана%')");
    $stmt->execute(['s' => $shelf, 'pvz' => $pvz]);
    $parcels = $stmt->fetchAll();
} catch (Exception $e) { die($e->getMessage()); }

$page_title = "Печать ярлыков полки №$shelf";
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?></title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 0; }
        .label { width: 100mm; height: 100mm; border: 1px dashed #ccc; padding: 10mm; box-sizing: border-box; page-break-after: always; position: relative; }
        .track { font-size: 24pt; font-weight: bold; margin-bottom: 5mm; }
        .shelf { position: absolute; top: 10mm; right: 10mm; font-size: 40pt; font-weight: 900; background: #000; color: #fff; padding: 2mm 5mm; border-radius: 5mm; }
        .info { font-size: 12pt; line-height: 1.4; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="padding: 20px; background: #eee;">
        <button onclick="window.print()">ПЕЧАТЬ</button>
        <a href="pvz_dashboard.php">Назад на склад</a>
    </div>

    <?php foreach($parcels as $p): ?>
        <div class="label">
            <div class="shelf">#<?php echo $p['shelf']; ?></div>
            <div class="track"><?php echo e($p['track_code']); ?></div>
            <div class="info">
                <strong>ПОЛУЧАТЕЛЬ:</strong> <?php echo e($p['recipient_name_ext'] ?: 'ID ' . $p['recipient_id']); ?><br>
                <strong>ТАРИФ:</strong> <?php echo e($p['tariff']); ?><br>
                <strong>ВЕС:</strong> <?php echo number_format($p['weight'], 3); ?> кг<br>
                <hr>
                <strong>АДРЕС:</strong> <?php echo e($p['address']); ?>
            </div>
        </div>
    <?php endforeach; ?>
</body>
</html>
