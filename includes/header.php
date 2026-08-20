<?php require_once __DIR__ . '/auth.php'; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — Миграционный портал' : 'Миграционный портал'; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/css/tabler.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.46.0/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="page">
    <?php if ($user = currentUser()): ?>
    <aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu" aria-controls="sidebar-menu" aria-expanded="false" aria-label="Показать меню">
                <span class="navbar-toggler-icon"></span>
            </button>
            <h1 class="navbar-brand navbar-brand-autodark">
                <a href="index.php">Миграционный портал</a>
            </h1>
            <div class="collapse navbar-collapse" id="sidebar-menu">
                <ul class="navbar-nav pt-lg-3">
                    <li class="nav-item">
                        <a class="nav-link" href="employees.php">
                            <span class="nav-link-icon"><i class="ti ti-users"></i></span>
                            <span class="nav-link-title">Сотрудники</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="documents.php">
                            <span class="nav-link-icon"><i class="ti ti-file-text"></i></span>
                            <span class="nav-link-title">Документы</span>
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav mt-auto pt-lg-3">
                    <li class="nav-item">
                        <span class="nav-link disabled">
                            <span class="nav-link-icon"><i class="ti ti-user"></i></span>
                            <span class="nav-link-title"><?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['role']); ?>)</span>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <span class="nav-link-icon"><i class="ti ti-logout"></i></span>
                            <span class="nav-link-title">Выйти</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </aside>
    <?php endif; ?>
    <div class="page-wrapper">
        <div class="page-body">
            <div class="container-xl">
