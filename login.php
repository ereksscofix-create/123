<?php
require_once 'config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = t('Неверный логин или пароль', 'Қате логин немесе пароль');
    }
}

include 'header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-4">
        <div class="card p-4">
            <h2 class="text-center mb-4"><?= t('Вход', 'Кіру') ?></h2>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label" for="login"><?= t('Логин', 'Логин') ?></label>
                    <input type="text" name="login" id="login" class="form-control" required autocomplete="username">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password"><?= t('Пароль', 'Құпия сөз') ?></label>
                    <input type="password" name="password" id="password" class="form-control" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-rainbow w-100"><?= t('Войти', 'Кіру') ?></button>
            </form>
            <div class="mt-3 text-center">
                <a href="register.php" class="text-decoration-none"><?= t('Нет аккаунта? Зарегистрируйтесь', 'Аккаунт жоқ па? Тіркеліңіз') ?></a>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
