<?php
require_once 'config.php';
check_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $trip_id = (int)$_POST['trip_id'];
    $caption = trim($_POST['caption']);

    // Security: Validate file extension
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));

    if (!in_array($file_extension, $allowed_extensions)) {
        die("Invalid file type.");
    }

    // Security: Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $_FILES['photo']['tmp_name']);
    finfo_close($finfo);
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    if (!in_array($mime_type, $allowed_mimes)) {
        die("Invalid file content.");
    }

    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;
    $target_file = $upload_dir . $filename;

    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
        $stmt = $pdo->prepare("INSERT INTO photos (trip_id, filename, caption) VALUES (?, ?, ?)");
        $stmt->execute([$trip_id, $target_file, $caption]);
    }

    header("Location: trip_view.php?id=$trip_id");
    exit;
}
?>
