<?php

// Заглушка страницы фичи "Миграционный учёт" (сводный список документов).
// Спецификация: .agent-os/specs/2026-08-20-migration-documents/spec.md

require __DIR__ . '/includes/auth.php';
requireLogin();

$pageTitle = 'Документы';
$extraScripts = ['js/documents.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="page-header d-print-none mb-3">
    <h2 class="page-title">Разрешительные документы</h2>
</div>

<!-- TODO: фильтр по статусу (просрочен / скоро истекает / все) и
     поиск по сотруднику — см. tasks.md фичи migration-documents -->

<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter table-sm card-table" id="documents-table">
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
                <!--
                    Пример разметки статусов для js/documents.js (см. tasks.md):
                    <span class="badge bg-danger-lt">Просрочен</span>
                    <span class="badge bg-warning-lt">Скоро истекает</span>
                    <span class="badge bg-success-lt">В порядке</span>
                    <span class="badge bg-secondary-lt">Без срока</span>
                -->
                <tr><td colspan="6">TODO: реализовать загрузку списка (api/documents.php)</td></tr>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
