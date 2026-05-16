<?php
// label_print.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
checkLogin();

if (!isset($_GET['id'])) die("Посылка не выбрана");
$parcel_id = (int)$_GET['id'];
$is_return = isset($_GET['return']) && $_GET['return'] == 1;

try {
    $stmt = $pdo->prepare("SELECT p.*,
                           s.name AS sender_name, s.login AS sender_login,
                           r.name AS recipient_name, r.login AS recipient_login
                           FROM parcels p
                           LEFT JOIN users s ON p.sender_id = s.id
                           LEFT JOIN users r ON p.recipient_id = r.id
                           WHERE p.id = :pid LIMIT 1");
    $stmt->execute(['pid' => $parcel_id]);
    $parcel = $stmt->fetch();
    if (!$parcel) die("Посылка не найдена");

    // Проверяем, есть ли уже код для этой посылки (любая дата, или можно ограничить)
    $stmt = $pdo->prepare("SELECT secret_code FROM parcel_codes WHERE parcel_id = :pid ORDER BY id DESC LIMIT 1");
    $stmt->execute(['pid' => $parcel_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $secret_code = $existing['secret_code'];
    } else {
        // Генерируем ОДИН раз
        $secret_code = rand(100000, 999999);
        $code = rand(1000, 9999);
        $stmt = $pdo->prepare("INSERT INTO parcel_codes (parcel_id, code, secret_code, code_date) VALUES (:pid, :code, :secret, DATE(NOW()))");
        $stmt->execute(['pid' => $parcel_id, 'code' => $code, 'secret' => $secret_code]);
    }

} catch (PDOException $e) {
    if (strpos($e->getMessage(), '1062') !== false) {
        die("<h3>Ошибка: База данных настроена неверно.</h3>
             <p>Пожалуйста, запустите скрипт исправления: <a href='db_fix.php'><b>ИСПРАВИТЬ БД</b></a></p>
             <p>Техническая инфо: " . htmlspecialchars($e->getMessage()) . "</p>");
    }
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
  body{font-family: 'Inter', Arial, sans-serif;background:#fff;margin:0;padding:20px;display:flex;flex-direction:column;align-items:center}
  .label{width:400px;padding:25px;border:3px solid #000;box-sizing:border-box;background:#fff;position:relative}
  .hdr{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #000;padding-bottom:10px;margin-bottom:15px}
  .brand{font-weight:900;font-size:28px;letter-spacing:-1px}
  .date{font-size:12px;text-align:right;font-weight:bold}
  .section{margin-top:15px}
  .bold{font-weight:800}
  .barcode-container{margin:20px 0;text-align:center}
  .secret-box{margin-top:20px;border:2px dashed #000;padding:15px;text-align:center;background:#f9f9f9}
  .secret-label{font-size:10px;font-weight:bold;text-transform:uppercase;margin-bottom:5px;display:block}
  .secret-value{font-family:'Courier New',monospace;font-size:32px;font-weight:900;letter-spacing:5px}
  .info-table{width:100%;font-size:13px;border-collapse:collapse}
  .info-table td{padding:4px 0;vertical-align:top}
  .sig{display:flex;justify-content:space-between;align-items:flex-end;margin-top:25px;border-top:1px solid #000;padding-top:10px}
  .stamp-place{width:80px;height:80px;border:1px dashed #ccc;display:flex;align-items:center;justify-content:center;font-size:8px;color:#999;text-align:center;border-radius:50%}
  @media print{ .no-print{display:none} body{padding:0} .label{margin-top:0;border:2px solid #000} }
  .btn{padding:12px 24px;cursor:pointer;background:#4361ee;color:#fff;border:none;border-radius:10px;font-weight:700;text-decoration:none;box-shadow:0 4px 10px rgba(67,97,238,0.3)}
</style>
</head>
<body>
<div class="no-print" style="margin-bottom:30px;display:flex;gap:15px;flex-wrap:wrap">
  <button class="btn" onclick="window.print()"><i class="bi bi-printer"></i> ПЕЧАТЬ</button>
  <?php if ($is_return): ?>
    <a href="label_print.php?id=<?php echo $parcel_id; ?>" class="btn" style="background:var(--primary)"><i class="bi bi-arrow-left-right"></i> ОБЫЧНЫЙ ЯРЛЫК</a>
  <?php else: ?>
    <a href="label_print.php?id=<?php echo $parcel_id; ?>&return=1" class="btn" style="background:#f59e0b"><i class="bi bi-arrow-return-left"></i> ВОЗВРАТНЫЙ ЯРЛЫК</a>
  <?php endif; ?>
  <a href="dashboard.php" class="btn" style="background:#6b7280">ВЕРНУТЬСЯ</a>
</div>

<div class="label">
  <div class="hdr">
    <div class="brand">EHPST <?php echo $is_return ? '<span style="color:#dc2626;font-size:14px;vertical-align:middle">(ВОЗВРАТ)</span>' : ''; ?></div>
    <div class="date"><?php echo date('d.m.Y'); ?><br><?php echo date('H:i'); ?></div>
  </div>

  <div class="section">
    <div class="small bold text-uppercase" style="font-size:10px;color:#666">Трек-код отправления</div>
    <div class="bold" style="font-size:24px"><?php echo htmlspecialchars($parcel['track_code']); ?></div>
  </div>

  <div class="barcode-container">
    <svg id="barcode"></svg>
  </div>

  <div class="section">
    <table class="info-table">
        <?php
            $s_display = htmlspecialchars($parcel['sender_name'] ?: $parcel['sender_login'] ?: 'ID '.$parcel['sender_id']);
            $r_display = htmlspecialchars($parcel['recipient_name'] ?: $parcel['recipient_login'] ?: 'ID '.$parcel['recipient_id']);

            if ($is_return) {
                $from = $r_display;
                $to = $s_display;
            } else {
                $from = $s_display;
                $to = $r_display;
            }
        ?>
        <tr><td class="bold" style="width:110px">ОТПРАВИТЕЛЬ:</td><td><?php echo $from; ?></td></tr>
        <tr><td class="bold">ПОЛУЧАТЕЛЬ:</td><td><?php echo $to; ?></td></tr>
        <tr><td class="bold">АДРЕС:</td><td style="line-height:1.3"><?php echo nl2br(htmlspecialchars($parcel['address'])); ?></td></tr>
    </table>
  </div>

  <div class="section" style="border-top:1px solid #eee; padding-top:10px">
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; font-size:12px">
        <div>ВЕС: <span class="bold"><?php echo number_format($parcel['weight'], 3); ?> КГ</span></div>
        <div>ТАРИФ: <span class="bold"><?php echo htmlspecialchars($parcel['tariff']); ?></span></div>
        <div>СУММА: <span class="bold"><?php echo number_format($parcel['cost'], 2); ?> BYN</span></div>
        <div>ЦЕННОСТЬ: <span class="bold"><?php echo number_format($parcel['declared_value'], 2); ?> BYN</span></div>
        <div>НАЛ.ПЛ: <span class="bold"><?php echo ($parcel['cod'] > 0) ? number_format($parcel['cod'], 2).' BYN' : 'НЕТ'; ?></span></div>
    </div>
  </div>

  <div class="secret-box">
    <span class="secret-label">Секретный код выдачи</span>
    <div class="secret-value"><?php echo $secret_code; ?></div>
    <div style="font-size:9px;margin-top:5px;line-height:1.2">Код действителен только в день печати. Сообщите его работнику почты для получения.</div>
  </div>

  <div class="sig">
    <div>
      <div style="font-size:10px;font-weight:bold;margin-bottom:15px">ПОДПИСЬ ОТПРАВИТЕЛЯ:</div>
      <div style="width:120px;border-bottom:1px solid #000;height:1px"></div>
    </div>
    <div class="stamp-place">МЕСТО ДЛЯ<br>ПЕЧАТИ</div>
  </div>
</div>

<script>
  JsBarcode("#barcode", "<?php echo htmlspecialchars($parcel['track_code']); ?>", {
    format: "CODE128", width:2.5, height:70, displayValue:true, fontSize:14, fontOptions:"bold"
  });
</script>
</body>
</html>
