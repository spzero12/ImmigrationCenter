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

<main class="container container--narrow">
    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

    <p><!-- TODO: реализовать сохранение --> Раздел в разработке.</p>

    <form method="post" class="form">
        <label>Тип документа
            <select name="type" required>
                <?php foreach ($documentTypes as $value => $label): ?>
                    <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Номер
            <input type="text" name="number">
        </label>
        <label>Серия
            <input type="text" name="series">
        </label>
        <label>Дата выдачи
            <input type="date" name="issue_date">
        </label>
        <label>Срок действия до
            <input type="date" name="expire_date">
        </label>
        <label>Кем выдан
            <input type="text" name="issued_by">
        </label>
        <label>Примечания
            <textarea name="notes"></textarea>
        </label>

        <div class="form-actions">
            <button type="submit" disabled>Сохранить (TODO)</button>
            <a href="documents.php" class="btn btn-secondary">Отмена</a>
        </div>
    </form>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
