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
            --primary-hover: #374fc7;
            --success: #06c755;
            --warning: #ff9f43;
            --danger: #ea5455;
            --border-color: #eef2f7;
            --bg-light: #f8f9fa;
        }
        body {
            background-color: #f4f7fe;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #2b3674;
        }
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(163, 174, 184, 0.15);
            transition: transform 0.2s;
        }
        .btn { border-radius: 10px; font-weight: 600; padding: 0.6rem 1.2rem; }
        .btn-primary { background-color: var(--primary); border: none; }
        .btn-primary:hover { background-color: var(--primary-hover); transform: translateY(-1px); }
        .navbar {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 0;
        }
        .navbar-brand { font-size: 1.5rem; letter-spacing: -0.5px; }
        .badge { border-radius: 8px; padding: 0.5em 0.8em; }
        .table thead th {
            background: var(--bg-light);
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            color: #a3aed0;
            padding: 1.2rem 1rem;
            border: none;
        }
        .table tbody td { padding: 1.2rem 1rem; border-bottom: 1px solid var(--border-color); }
        .x-small { font-size: 0.75rem; }
        .form-control, .form-select {
            border-radius: 12px;
            padding: 0.7rem 1rem;
            border: 1px solid #d1d9e6;
        }
        .form-control:focus { box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1); border-color: var(--primary); }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg sticky-top mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="dashboard.php">
            <i class="bi bi-lightning-charge-fill me-2"></i>EHPST
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center gap-2">
                <li class="nav-item">
                    <a class="nav-link fw-semibold px-3" href="dashboard.php">Панель</a>
                </li>
                <?php if (isset($user) && $user): ?>
                <li class="nav-item dropdown ms-lg-3">
                    <a class="nav-link dropdown-toggle bg-light rounded-pill px-3 py-2 fw-bold text-dark" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-2"></i><?php echo e($user['name'] ?: $user['login']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2 mt-2" style="border-radius: 12px;">
                        <li><a class="dropdown-item rounded-2 py-2" href="profile.php"><i class="bi bi-gear me-2"></i>Настройки</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item rounded-2 py-2 text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Выйти</a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a href="login.php" class="btn btn-primary px-4">Войти</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<div class="container pb-5">
