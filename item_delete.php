<?php
require_once 'config.php';
check_auth();

$id = (int)$_GET['id'];
$trip_id = (int)$_GET['trip_id'];

$stmt = $pdo->prepare("DELETE FROM trip_items WHERE id = ?");
$stmt->execute([$id]);

header("Location: trip_view.php?id=$trip_id");
exit;
?>
