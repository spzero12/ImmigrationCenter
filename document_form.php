<?php

// Заглушка формы добавления/редактирования документа сотрудника.
// Спецификация: .agent-os/specs/2026-08-20-migration-documents/spec.md
//
// TODO: по аналогии с employee_form.php —
// - принять employee_id (обязателен) и id документа (для редактирования)
// - обработать POST: валидация, INSERT/UPDATE в employee_documents
// - редирект обратно на карточку сотрудника или documents.php

require __DIR__ . '/includes/auth.php';
requireLogin();

$documentTypes = [
    'passport' => 'Паспорт',
    'visa' => 'Виза',
    'work_patent' => 'Патент на работу',
    'work_permit' => 'Разрешение на работу',
    'registration' => 'Регистрация по месту пребывания',
];

$pageTitle = 'Документ сотрудника';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header d-print-none mb-3">
    <h2 class="page-title"><?php echo htmlspecialchars($pageTitle); ?></h2>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <div class="alert alert-info"><!-- TODO: реализовать сохранение --> Раздел в разработке.</div>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label required">Тип документа</label>
                        <select class="form-select" name="type" required>
                            <?php foreach ($documentTypes as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Номер</label>
                            <input type="text" class="form-control" name="number">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Серия</label>
                            <input type="text" class="form-control" name="series">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Дата выдачи</label>
                            <input type="date" class="form-control" name="issue_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Срок действия до</label>
                            <input type="date" class="form-control" name="expire_date">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Кем выдан</label>
                        <input type="text" class="form-control" name="issued_by">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Примечания</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>

                    <div class="form-footer">
                        <a href="documents.php" class="btn btn-link">Отмена</a>
                        <button type="submit" class="btn btn-primary" disabled>Сохранить (TODO)</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
