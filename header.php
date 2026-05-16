<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#4361ee">
    <title><?php echo $page_title ?? 'EHPST - Почтовая система'; ?></title>

    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3f37c9;
            --primary-light: #4895ef;
            --success: #06c755;
            --warning: #ff9500;
            --danger: #ff3b30;
            --text-dark: #1a1a2e;
            --text-gray: #6b7280;
            --bg-light: #f4f7fe;
            --bg-white: #ffffff;
            --border-color: #e5e7eb;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-light);
            color: var(--text-dark);
            font-size: 16px;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        @media (max-width: 768px) {
            body { font-size: 14px; }
            .container { padding-left: 12px; padding-right: 12px; }
        }

        /* ===== NAVBAR ===== */
        .navbar {
            background: rgba(255, 255, 255, 0.9) !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            padding: 0.8rem 0;
            z-index: 1050;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.5rem;
            color: var(--primary) !important;
            letter-spacing: -0.5px;
        }

        .nav-link {
            font-weight: 600;
            color: var(--text-dark) !important;
            font-size: 0.9rem;
            padding: 0.5rem 1.2rem !important;
            border-radius: 10px;
            transition: all 0.2s;
        }

        .nav-link:hover {
            background: rgba(67, 97, 238, 0.05);
            color: var(--primary) !important;
        }

        .dropdown-menu {
            border: none;
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 0.6rem;
            margin-top: 10px;
        }

        .dropdown-item {
            padding: 0.7rem 1.2rem;
            font-weight: 600;
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .dropdown-item:hover {
            background-color: var(--primary);
            color: white;
            transform: translateX(3px);
        }

        /* ===== GLOBAL STYLES ===== */
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn {
            border-radius: 14px;
            font-weight: 700;
            padding: 0.8rem 1.5rem;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border: none;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(67, 97, 238, 0.4);
            background: var(--primary-dark);
        }

        .main-content {
            padding: 2.5rem 0;
            min-height: calc(100vh - 180px);
        }

        @media (max-width: 768px) {
            .main-content { padding: 1rem 0; }
        }

        .x-small { font-size: 0.75rem; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-lightning-charge-fill me-2"></i>EHPST
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#topNav">
                <i class="bi bi-list fs-1 text-dark"></i>
            </button>
            <div class="collapse navbar-collapse" id="topNav">
                <ul class="navbar-nav ms-auto align-items-center gap-2 mt-3 mt-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Панель</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="track.php">Отследить</a>
                    </li>
                    <?php
                    $u = currentUser();
                    if($u):
                    ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle bg-light rounded-pill px-4 py-2 d-flex align-items-center border" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle fs-5 me-2"></i>
                            <span><?php echo e($u['name'] ?: $u['login']); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-grid-1x2 me-2"></i>Дашборд</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Выход</a></li>
                        </ul>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Войти</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary btn-sm px-4 rounded-pill" href="register.php" style="min-height: auto; padding: 0.6rem 1.5rem;">Регистрация</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div class="main-content">
        <div class="container">
