// Заглушка клиентской логики фичи "Миграционный учёт" (документы и сроки).
// Спецификация: .agent-os/specs/2026-08-20-migration-documents/spec.md
// api/documents.php пока возвращает 501 — методы ниже не вызываются.

$(function () {
    // TODO: loadDocuments(filters) — GET api/documents.php, рендер строк
    // таблицы #documents-table с вычисленным статусом (см. spec.md).

    // TODO: обработчик удаления документа (роли admin/hr), по аналогии с
    // js/employees.js.

    // TODO: обработчик фильтра по статусу и поиска по сотруднику.
});
