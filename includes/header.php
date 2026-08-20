<?php require_once __DIR__ . '/auth.php'; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — Миграционный портал' : 'Миграционный портал'; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="site-header">
    <div class="container site-header__inner">
        <a href="index.php" class="site-header__logo">Миграционный портал</a>
        <?php if ($user = currentUser()): ?>
            <nav class="site-header__nav">
                <a href="employees.php">Сотрудники</a>
                <span class="site-header__user"><?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['role']); ?>)</span>
                <a href="logout.php">Выйти</a>
            </nav>
        <?php endif; ?>
    </div>
</header>
