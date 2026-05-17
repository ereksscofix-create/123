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

    // Автоматический переход в режим возврата при открытии возвратного ярлыка
    if ($is_return && (int)$parcel['is_return'] === 0) {
        $stmt = $pdo->prepare("UPDATE parcels SET is_return = 1 WHERE id = :id");
        $stmt->execute(['id' => $parcel_id]);
        $status_text = "Оформлен возврат [" . date('d.m.Y H:i') . "]";
        $stmt = $pdo->prepare("INSERT INTO parcel_status (parcel_id, status_text) VALUES (:pid, :txt)");
        $stmt->execute(['pid' => $parcel_id, 'txt' => $status_text]);
        $parcel['is_return'] = 1;
    }

    // Проверяем проверочный код
    $stmt = $pdo->prepare("SELECT secret_code FROM parcel_codes WHERE parcel_id = :pid ORDER BY id DESC LIMIT 1");
    $stmt->execute(['pid' => $parcel_id]);
    $existing = $stmt->fetch();
    if ($existing) {
        $secret_code = $existing['secret_code'];
    } else {
        $secret_code = rand(100000, 999999);
        $code = rand(1000, 9999);
        $stmt = $pdo->prepare("INSERT INTO parcel_codes (parcel_id, code, secret_code, code_date) VALUES (:pid, :code, :secret, DATE(NOW()))");
        $stmt->execute(['pid' => $parcel_id, 'code' => $code, 'secret' => $secret_code]);
    }
} catch (PDOException $e) { die("Ошибка БД: " . $e->getMessage()); }

// Расчет комиссий для отображения
$rates = [ 'EK'=>7, 'EKP'=>15, 'ST'=>22, 'EX'=>31, 'QQ'=>60, 'N'=>3, 'P'=>4 ];
$base_rate = $rates[$parcel['tariff']] ?? 10;
$base_cost = (float)$parcel['weight'] * $base_rate;
$cod_fee = (float)$parcel['cod'] * 0.015;
$dv_fee = (float)$parcel['declared_value'] * 0.017;
$inv_fee = ($parcel['inventory'] !== '' && $parcel['inventory'] !== null) ? $base_cost * 0.02 : 0;
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Ярлык <?php echo htmlspecialchars($parcel['track_code']); ?></title>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<style>
  body{font-family:'Inter', Arial, sans-serif;background:#f0f2f5;margin:0;padding:20px;display:flex;flex-direction:column;align-items:center}

  /* ПОЧТОВАЯ МАРКА (для N и P) */
  .postage-stamp{width:100mm;height:60mm;padding:5mm;border:2px dashed #333;box-sizing:border-box;background:#fff;position:relative;margin-top:10px;box-shadow:0 10px 20px rgba(0,0,0,0.1);display:flex;align-items:center;justify-content:space-between;overflow:hidden;}
  .stamp-watermark{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-30deg);font-size:60px;font-weight:900;color:rgba(0,0,0,0.03);white-space:nowrap;pointer-events:none;z-index:1}
  .stamp-main{z-index:2;display:flex;flex-direction:column;gap:5px;width:60%}
  .stamp-code{z-index:2;width:35%;display:flex;flex-direction:column;align-items:center;gap:5px}
  .stamp-brand{font-size:22px;font-weight:900;border-bottom:2px solid #000;display:inline-block}
  .stamp-no{font-family:monospace;font-size:12px;color:#666}

  /* ЯРЛЫК 120мм на 200мм */
  .label{width:120mm;height:200mm;padding:10mm;border:4px solid #000;box-sizing:border-box;background:#fff;position:relative;margin-top:10px;box-shadow:0 10px 30px rgba(0,0,0,0.1);display:flex;flex-direction:column;}
  .hdr{display:flex;justify-content:space-between;align-items:center;border-bottom:3px solid #000;padding-bottom:12px;margin-bottom:15px}
  .brand{font-weight:900;font-size:38px;letter-spacing:-1px}
  .date{font-size:14px;text-align:right;font-weight:bold;line-height:1.2}
  .barcode-container{margin:20px 0;text-align:center;border:1px solid #eee;padding:15px 0}
  .info-table{width:100%;font-size:16px;border-collapse:collapse;margin-top:15px}
  .info-table td{padding:10px 0;vertical-align:top}
  .bold{font-weight:800}
  .inventory-box{margin-top:20px;padding:15px;border:2px solid #000;flex-grow:1;min-height:150px;font-size:14px;line-height:1.5}
  .fees-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:25px;font-size:14px;border-top:2px solid #eee;padding-top:20px}
  .secret-box{margin-top:25px;border:3px dashed #000;padding:20px;text-align:center;background:#f9f9f9}
  .secret-value{font-family:'Courier New',monospace;font-size:36px;font-weight:900;letter-spacing:6px}
  .footer-sig{display:flex;justify-content:space-between;align-items:flex-end;margin-top:30px;border-top:2px solid #000;padding-top:20px}
  .stamp-place{width:85px;height:85px;border:1px dashed #ccc;display:flex;align-items:center;justify-content:center;font-size:9px;color:#999;text-align:center;border-radius:50%}
  @media print{ .no-print{display:none} body{padding:0;background:#fff} .label{margin:0;box-shadow:none;border:3px solid #000} }
  .btn{padding:12px 24px;cursor:pointer;background:#4361ee;color:#fff;border:none;border-radius:10px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:8px}
</style>
</head>
<body>
<div class="no-print" style="margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;justify-content:center">
  <button class="btn" onclick="window.print()"><i class="bi bi-printer"></i> ПЕЧАТЬ</button>
  <?php if($is_return): ?>
    <a href="label_print.php?id=<?php echo $parcel_id; ?>" class="btn" style="background:#4361ee">ОБЫЧНЫЙ</a>
  <?php else: ?>
    <a href="label_print.php?id=<?php echo $parcel_id; ?>&return=1" class="btn" style="background:#f59e0b">ВОЗВРАТНЫЙ</a>
  <?php endif; ?>
  <a href="dashboard.php" class="btn" style="background:#6b7280">НАЗАД</a>
</div>

<?php if ($parcel['tariff'] === 'N' || $parcel['tariff'] === 'P'): ?>
    <!-- ПОЧТОВАЯ МАРКА ДЛЯ ТАРИФОВ N И P -->
    <div class="postage-stamp">
        <div class="stamp-watermark">POSTAL STAMP</div>
        <div class="stamp-main">
            <div class="stamp-brand">EHPST POST</div>
            <div class="stamp-no">Марка №<?php echo str_pad($parcel['id'], 8, '0', STR_PAD_LEFT); ?></div>

            <div style="font-size:11px; margin-top:10px;">
                <strong>ОТ:</strong> <?php echo e($parcel['sender_name'] ?: $parcel['sender_login']); ?><br>
                <strong>КОМУ:</strong> <?php echo e($parcel['recipient_name'] ?: $parcel['recipient_login']); ?><br>
                <strong>ТАРИФ:</strong> <?php echo $parcel['tariff'] === 'P' ? 'ПРИОРИТЕТ' : 'НЕ ПРИОРИТЕТ'; ?>
            </div>

            <div style="margin-top:15px; font-weight:bold; font-size:14px;">
                BYN <?php echo number_format($parcel['cost'], 2); ?>
            </div>
        </div>
        <div class="stamp-code">
            <div id="qrcode"></div>
            <div style="font-family:monospace; font-size:10px; margin-top:5px;"><?php echo e($parcel['track_code']); ?></div>
            <div style="font-size:9px; color:#777;">Код: <?php echo $secret_code; ?></div>
        </div>
    </div>
<?php else: ?>
    <!-- ОБЫЧНЫЙ ЯРЛЫК ДЛЯ ОСТАЛЬНЫХ ТАРИФОВ -->
    <div class="label">
      <div class="hdr">
        <div class="brand">EHPST <?php echo $is_return ? '<span style="font-size:16px;color:#dc2626">(RET)</span>' : ''; ?></div>
        <div class="date"><?php echo date('d.m.Y'); ?><br><?php echo date('H:i:s'); ?></div>
      </div>

      <div style="font-size:18px;font-weight:900;text-align:center"><?php echo htmlspecialchars($parcel['track_code']); ?></div>

      <div class="barcode-container">
        <svg id="barcode"></svg>
      </div>

      <table class="info-table">
        <?php
          $s_name = htmlspecialchars($parcel['sender_name'] ?: $parcel['sender_login'] ?: 'ID '.$parcel['sender_id']);
          $r_name = htmlspecialchars($parcel['recipient_name'] ?: $parcel['recipient_login'] ?: 'ID '.$parcel['recipient_id']);
          $s_addr = htmlspecialchars($parcel['sender_address'] ?: 'Не указан');
        if (!empty($parcel['sender_pvz'])) $s_addr = "<b>" . htmlspecialchars($parcel['sender_pvz']) . "</b>, " . $s_addr;

          $r_addr = htmlspecialchars($parcel['address'] ?: 'Не указан');
        if (!empty($parcel['pickup_point'])) $r_addr = "<b>" . htmlspecialchars($parcel['pickup_point']) . "</b>, " . $r_addr;

          if($is_return) { $from=$r_name; $to=$s_name; $fa=$r_addr; $ta=$s_addr; } else { $from=$s_name; $to=$r_name; $fa=$s_addr; $ta=$r_addr; }
        ?>
        <tr><td class="bold" style="width:100px">ОТКУДА:</td><td><?php echo $from; ?><br><span style="font-size:11px"><?php echo $fa; ?></span></td></tr>
        <tr><td class="bold">КУДА:</td><td><?php echo $to; ?><br><span style="font-size:11px"><?php echo $ta; ?></span></td></tr>
      </table>

      <?php if (!empty($parcel['inventory'])): ?>
        <div class="bold" style="font-size:10px;margin-top:10px;text-transform:uppercase">Опись вложения:</div>
        <div class="inventory-box">
          <?php echo nl2br(htmlspecialchars($parcel['inventory'])); ?>
        </div>
      <?php endif; ?>

      <div class="fees-grid">
        <div>ВЕС: <span class="bold"><?php echo number_format($parcel['weight'],3); ?> КГ</span></div>
        <div>ТАРИФ: <span class="bold"><?php echo htmlspecialchars($parcel['tariff']); ?></span></div>
        <div>ЦЕННОСТЬ: <span class="bold"><?php echo number_format($parcel['declared_value'], 2); ?> BYN</span></div>
        <div>НАЛОЖЕННЫЙ: <span class="bold"><?php echo number_format($parcel['cod'], 2); ?> BYN</span></div>
        <div>СБОР (НАЛ): <span class="bold"><?php echo number_format($cod_fee, 2); ?> BYN</span></div>
        <div>СБОР (ЦЕН): <span class="bold"><?php echo number_format($dv_fee, 2); ?> BYN</span></div>
        <div>СБОР (ОПИСЬ): <span class="bold"><?php echo number_format($inv_fee, 2); ?> BYN</span></div>
        <div style="font-size:16px; grid-column: span 2; text-align: right; margin-top: 10px; border-top: 1px dashed #ccc; padding-top: 5px;">
            К ОПЛАТЕ: <span class="bold"><?php echo number_format($parcel['cost'], 2); ?> BYN</span>
        </div>
      </div>

      <div class="secret-box">
        <div style="font-size:9px;font-weight:bold;text-transform:uppercase;margin-bottom:3px">Код выдачи (Секретно)</div>
        <div class="secret-value"><?php echo $secret_code; ?></div>
      </div>

      <div class="footer-sig">
        <div>
          <div style="font-size:10px;font-weight:bold;margin-bottom:20px">ПОДПИСЬ ОТПРАВИТЕЛЯ:</div>
          <div style="width:140px;border-bottom:2px solid #000"></div>
        </div>
        <div class="stamp-place">МЕСТО ДЛЯ<br>ПЕЧАТИ (ШТАМПА)</div>
      </div>
    </div>
<?php endif; ?>

<script>
  if (document.getElementById('barcode')) {
      JsBarcode("#barcode", "<?php echo $parcel['track_code']; ?>", {
        format: "CODE128", width: 2.2, height: 60, displayValue: false
      });
  }

  if (document.getElementById('qrcode')) {
      new QRCode(document.getElementById("qrcode"), {
          text: "<?php echo $parcel['track_code'] . '|' . $parcel['sender_name'] . '|' . $parcel['recipient_name'] . '|' . $parcel['tariff'] . '|' . $secret_code; ?>",
          width: 100,
          height: 100
      });
  }
</script>
</body>
</html>
