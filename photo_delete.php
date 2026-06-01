<?php
require_once 'config.php';
check_auth();

$id = (int)$_GET['id'];
$trip_id = (int)$_GET['trip_id'];

// Get filename to delete from disk
$stmt = $pdo->prepare("SELECT filename FROM photos WHERE id = ? AND trip_id = ?");
$stmt->execute([$id, $trip_id]);
$photo = $stmt->fetch();

if ($photo) {
    if (file_exists($photo['filename'])) {
        unlink($photo['filename']);
    }
    $stmt = $pdo->prepare("DELETE FROM photos WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: trip_view.php?id=$trip_id");
exit;
?>
