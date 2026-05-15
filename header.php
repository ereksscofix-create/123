<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'EHPST'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4361ee;
            --success: #06c755;
            --border-color: #eef2f7;
            --text-gray: #6c757d;
        }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .btn-primary { background-color: var(--primary); border: none; border-radius: 8px; }
        .btn-primary:hover { background-color: #374fc7; }
        .navbar { background: #fff; border-bottom: 1px solid var(--border-color); }
        .badge-status { border-radius: 20px; padding: 0.4em 0.8em; font-weight: 500; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="dashboard.php"><i class="bi bi-lightning-fill"></i> EHPST</a>
        <div class="d-flex align-items-center">
            <span class="me-3 d-none d-md-inline"><?php echo e($name ?? 'Гость'); ?></span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">Выйти</a>
        </div>
    </div>
</nav>
<div class="container">
