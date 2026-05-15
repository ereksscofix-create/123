<?php
// label_print.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

if (!isset($_GET['id'])) die("Посылка не выбрана");
$parcel_id = (int)$_GET['id'];
$with_secret = isset($_GET['with_secret']) && $_GET['with_secret'] == '1';

try {
    $stmt = $pdo->prepare("SELECT p.*, s.name AS sender_name, r.name AS recipient_name,
                                  (SELECT secret_code FROM parcel_codes WHERE parcel_id = p.id AND code_date = DATE(NOW()) LIMIT 1) AS secret_code
                           FROM parcels p
                           JOIN users s ON p.sender_id = s.id
                           JOIN users r ON p.recipient_id = r.id
                           WHERE p.id = :pid
                           LIMIT 1");
    $stmt->execute(['pid' => $parcel_id]);
    $parcel = $stmt->fetch();
    if (!$parcel) die("Посылка не найдена");
} catch (PDOException $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Ярлык: <?php echo htmlspecialchars($parcel['track_code']); ?></title>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<style>
  body{font-family: Arial,Helvetica,sans-serif;background:#fff;margin:0;padding:20px;display:flex;flex-direction:column;align-items:center}
  .label{width:400px;padding:20px;border:2px solid #000;box-sizing:border-box;background:#fff;margin-top:10px}
  .hdr{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #000;padding-bottom:10px;margin-bottom:10px}
  .brand{font-weight:700;font-size:24px}
  .small{font-size:12px;color:#000}
  .section{margin-top:12px}
  .bold{font-weight:700}
  .barcode-container{margin:15px 0;text-align:center}
  .secret{font-family:'Courier New',monospace;font-size:28px;font-weight:900;letter-spacing:4px;text-align:center;margin:10px 0;border:2px dashed #000;padding:10px}
  .sig{display:flex;justify-content:space-between;align-items:center;margin-top:20px}
  @media print{ .no-print{display:none} body{padding:0} .label{margin-top:0;border:1px solid #000} }
  .btn{padding:10px 20px;cursor:pointer;background:#4361ee;color:#fff;border:none;border-radius:5px;font-size:16px;text-decoration:none}
  .btn-secondary{background:#6c757d}
</style>
</head>
<body>
<div class="no-print" style="margin-bottom:20px;display:flex;gap:10px">
  <button class="btn" onclick="window.print()">Печать</button>
  <a href="dashboard.php" class="btn btn-secondary">Назад к дашборду</a>
</div>

<div class="label">
  <div class="hdr">
    <div class="brand">EHPST</div>
    <div class="small text-end"><?php echo date('d.m.Y'); ?><br><?php echo date('H:i'); ?></div>
  </div>

  <div class="section">
    <div class="small bold">Трек-код / Track Code</div>
    <div class="bold" style="font-size:22px"><?php echo htmlspecialchars($parcel['track_code']); ?></div>
  </div>

  <div class="barcode-container">
    <svg id="barcode"></svg>
    <div class="small"><?php echo htmlspecialchars($parcel['track_code']); ?></div>
  </div>

  <div class="section">
    <table style="width:100%; font-size:13px">
        <tr><td class="bold" style="width:100px">Отправитель:</td><td><?php echo htmlspecialchars($parcel['sender_name']); ?></td></tr>
        <tr><td class="bold">Получатель:</td><td><?php echo htmlspecialchars($parcel['recipient_name']); ?></td></tr>
        <tr><td class="bold">Адрес:</td><td><?php echo nl2br(htmlspecialchars($parcel['address'])); ?></td></tr>
    </table>
  </div>

  <div class="section" style="border-top:1px solid #eee; padding-top:10px; font-size:13px">
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:5px">
        <div>Вес: <span class="bold"><?php echo htmlspecialchars($parcel['weight']); ?> кг</span></div>
        <div>Тариф: <span class="bold"><?php echo htmlspecialchars($parcel['tariff']); ?></span></div>
        <div>Стоимость: <span class="bold"><?php echo number_format($parcel['cost'],2); ?> BYN</span></div>
        <div>Налож. плат: <span class="bold"><?php echo ($parcel['cod'] > 0) ? number_format($parcel['cod'],2).' BYN' : 'Нет'; ?></span></div>
    </div>
  </div>

  <?php if ($with_secret && !empty($parcel['secret_code'])): ?>
    <div class="section" style="margin-top:20px">
      <div class="small bold text-center">СЕКРЕТНЫЙ КОД ВЫДАЧИ</div>
      <div class="secret"><?php echo htmlspecialchars($parcel['secret_code']); ?></div>
      <div class="small text-center">Действителен только в день печати ярлыка.</div>
    </div>
  <?php endif; ?>

  <div class="sig">
    <div style="flex:1;border-top:1px solid #000;padding-top:6px;font-size:11px">Подпись отправителя / Signature</div>
  </div>
</div>

<script>
  JsBarcode("#barcode", "<?php echo htmlspecialchars($parcel['track_code']); ?>", {
    format: "CODE128", width:2.5, height:60, displayValue:false
  });
</script>
</body>
</html>
