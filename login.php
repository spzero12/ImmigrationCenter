<?php

require __DIR__ . '/includes/auth.php';

if (currentUser()) {
    header('Location: employees.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (login($username, $password)) {
        header('Location: employees.php');
        exit;
    }

    $error = 'Неверный логин или пароль';
}

$pageTitle = 'Вход';
require __DIR__ . '/includes/header.php';
?>

<main class="container container--narrow">
    <h1>Вход в систему</h1>

    <?php if ($error): ?>
        <p class="alert alert-error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="post" class="form">
        <label>
            Логин
            <input type="text" name="username" required autofocus>
        </label>
        <label>
            Пароль
            <input type="password" name="password" required>
        </label>
        <button type="submit">Войти</button>
    </form>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
