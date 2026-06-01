<?php
require_once 'config.php';
check_auth();

$id = (int)$_GET['id'];
$stmt = $pdo->prepare("DELETE FROM trips WHERE id = ?");
$stmt->execute([$id]);

header("Location: dashboard.php");
exit;
?>
