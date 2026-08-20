<?php

// Заглушка страницы фичи "Миграционный учёт" (сводный список документов).
// Спецификация: .agent-os/specs/2026-08-20-migration-documents/spec.md

require __DIR__ . '/includes/auth.php';
requireLogin();

$pageTitle = 'Документы';
$extraScripts = ['js/documents.js'];
require __DIR__ . '/includes/header.php';
?>

<main class="container">
    <div class="page-header">
        <h1>Разрешительные документы</h1>
    </div>

    <p>
        <!-- TODO: фильтр по статусу (просрочен / скоро истекает / все) и
             поиск по сотруднику — см. tasks.md -->
        Раздел в разработке.
    </p>

    <table class="table" id="documents-table">
        <thead>
            <tr>
                <th>Сотрудник</th>
                <th>Тип документа</th>
                <th>Номер</th>
                <th>Срок действия</th>
                <th>Статус</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="6">TODO: реализовать загрузку списка (api/documents.php)</td></tr>
        </tbody>
    </table>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
