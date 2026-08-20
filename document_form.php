<?php

require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/documents.php';
requireLogin();

$document = [
    'id' => null,
    'employee_id' => (int) ($_GET['employee_id'] ?? 0),
    'type' => array_key_first(DOCUMENT_TYPE_LABELS),
    'number' => '',
    'series' => '',
    'issue_date' => '',
    'expire_date' => '',
    'issued_by' => '',
    'notes' => '',
    'field_overrides' => null,
    'scan_original_name' => null,
    'scan_mime_type' => null,
];

$isEdit = isset($_GET['id']);
$db = getDb();

if ($isEdit) {
    $stmt = $db->prepare('SELECT * FROM employee_documents WHERE id = ?');
    $stmt->execute([(int) $_GET['id']]);
    $found = $stmt->fetch();

    if (!$found) {
        http_response_code(404);
        exit('Документ не найден');
    }

    $document = $found;
}

if ($document['employee_id'] <= 0) {
    http_response_code(400);
    exit('Не указан сотрудник (employee_id)');
}

$employeeStmt = $db->prepare('SELECT * FROM employees WHERE id = ?');
$employeeStmt->execute([$document['employee_id']]);
$employee = $employeeStmt->fetch();

if (!$employee) {
    http_response_code(404);
    exit('Сотрудник не найден');
}

$passport = getCanonicalPassport($db, $employee['id']);
$commonDefaults = documentCommonFieldDefaults($employee, $passport);

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $document['type'] = $_POST['type'] ?? '';
    $document['number'] = trim($_POST['number'] ?? '');
    $document['series'] = trim($_POST['series'] ?? '');
    $document['issue_date'] = $_POST['issue_date'] ?: null;
    $document['expire_date'] = $_POST['expire_date'] ?: null;
    $document['issued_by'] = trim($_POST['issued_by'] ?? '');
    $document['notes'] = trim($_POST['notes'] ?? '');

    $submittedCommon = [];
    foreach (DOCUMENT_COMMON_FIELDS as $field) {
        $submittedCommon[$field] = trim($_POST['common_' . $field] ?? '');
    }

    $overrides = documentBuildOverrides($submittedCommon, $commonDefaults);
    $overridesJson = $overrides ? json_encode($overrides, JSON_UNESCAPED_UNICODE) : null;

    if (!array_key_exists($document['type'], DOCUMENT_TYPE_LABELS)) {
        $error = 'Выберите тип документа';
    } else {
        if ($isEdit) {
            $stmt = $db->prepare(
                'UPDATE employee_documents SET type = ?, number = ?, series = ?, issue_date = ?, expire_date = ?, issued_by = ?, notes = ?, field_overrides = ? WHERE id = ?'
            );
            $stmt->execute([
                $document['type'], $document['number'], $document['series'],
                $document['issue_date'], $document['expire_date'], $document['issued_by'],
                $document['notes'], $overridesJson, $document['id'],
            ]);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO employee_documents (employee_id, type, number, series, issue_date, expire_date, issued_by, notes, field_overrides, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $document['employee_id'], $document['type'], $document['number'], $document['series'],
                $document['issue_date'], $document['expire_date'], $document['issued_by'],
                $document['notes'], $overridesJson, currentUser()['id'],
            ]);
        }

        header('Location: employee_form.php?id=' . $document['employee_id']);
        exit;
    }
}

$commonFields = documentEffectiveCommonFields($employee, $passport, $document['field_overrides']);

$pageTitle = $isEdit ? 'Редактирование документа' : 'Новый документ';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header d-print-none mb-3">
    <h2 class="page-title"><?php echo htmlspecialchars($pageTitle); ?></h2>
    <div class="page-subtitle"><?php echo htmlspecialchars($employee['full_name']); ?></div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label required">Тип документа</label>
                        <select class="form-select" name="type" required>
                            <?php foreach (DOCUMENT_TYPE_LABELS as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $document['type'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Номер</label>
                            <input type="text" class="form-control" name="number" value="<?php echo htmlspecialchars($document['number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Серия</label>
                            <input type="text" class="form-control" name="series" value="<?php echo htmlspecialchars($document['series'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Дата выдачи</label>
                            <input type="date" class="form-control" name="issue_date" value="<?php echo htmlspecialchars($document['issue_date'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Срок действия до</label>
                            <input type="date" class="form-control" name="expire_date" value="<?php echo htmlspecialchars($document['expire_date'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Кем выдан</label>
                        <input type="text" class="form-control" name="issued_by" value="<?php echo htmlspecialchars($document['issued_by'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Примечания</label>
                        <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($document['notes'] ?? ''); ?></textarea>
                    </div>

                    <hr class="my-4">

                    <h3 class="card-title">Общие данные сотрудника для этого документа</h3>
                    <p class="text-secondary">
                        Предзаполнено из карточки сотрудника и его паспорта. Можно
                        изменить только для этого документа (например, другая
                        транслитерация в конкретной визе) — правки карточки
                        сотрудника сюда сами по себе не влияют, если поле здесь
                        уже отличается от неё.
                    </p>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Фамилия</label>
                            <input type="text" class="form-control" name="common_last_name" value="<?php echo htmlspecialchars($commonFields['last_name']); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Имя</label>
                            <input type="text" class="form-control" name="common_first_name" value="<?php echo htmlspecialchars($commonFields['first_name']); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Отчество</label>
                            <input type="text" class="form-control" name="common_middle_name" value="<?php echo htmlspecialchars($commonFields['middle_name']); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Гражданство</label>
                            <input type="text" class="form-control" name="common_citizenship" value="<?php echo htmlspecialchars($commonFields['citizenship']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Дата рождения</label>
                            <input type="date" class="form-control" name="common_birth_date" value="<?php echo htmlspecialchars($commonFields['birth_date']); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Должность</label>
                            <input type="text" class="form-control" name="common_position" value="<?php echo htmlspecialchars($commonFields['position']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Подразделение</label>
                            <input type="text" class="form-control" name="common_department" value="<?php echo htmlspecialchars($commonFields['department']); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Номер паспорта</label>
                            <input type="text" class="form-control" name="common_passport_number" value="<?php echo htmlspecialchars($commonFields['passport_number']); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Серия паспорта</label>
                            <input type="text" class="form-control" name="common_passport_series" value="<?php echo htmlspecialchars($commonFields['passport_series']); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Кем выдан паспорт</label>
                            <input type="text" class="form-control" name="common_passport_issued_by" value="<?php echo htmlspecialchars($commonFields['passport_issued_by']); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Дата выдачи паспорта</label>
                            <input type="date" class="form-control" name="common_passport_issue_date" value="<?php echo htmlspecialchars($commonFields['passport_issue_date']); ?>">
                        </div>
                    </div>

                    <div class="form-footer">
                        <a href="employee_form.php?id=<?php echo (int) $employee['id']; ?>" class="btn btn-link">Отмена</a>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($isEdit): ?>
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title">Скан документа</h3>
            </div>
            <div class="card-body">
                <?php if ($document['scan_original_name']): ?>
                    <p>
                        Загружен файл:
                        <a href="document_scan.php?document_id=<?php echo (int) $document['id']; ?>" target="_blank">
                            <?php echo htmlspecialchars($document['scan_original_name']); ?>
                        </a>
                    </p>
                <?php else: ?>
                    <p class="text-secondary">Скан пока не загружен.</p>
                <?php endif; ?>

                <?php if (isset($_GET['scan_error'])): ?>
                    <div class="alert alert-danger">
                        <?php
                        $scanErrors = [
                            'type' => 'Недопустимый формат файла — только JPG, PNG или PDF.',
                            'size' => 'Файл больше 10 МБ.',
                            'save' => 'Не удалось сохранить файл на сервере.',
                        ];
                        echo htmlspecialchars($scanErrors[$_GET['scan_error']] ?? 'Не удалось загрузить файл.');
                        ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="document_scan_upload.php" enctype="multipart/form-data" class="row g-2 align-items-end">
                    <input type="hidden" name="document_id" value="<?php echo (int) $document['id']; ?>">
                    <div class="col-auto">
                        <input type="file" class="form-control" name="scan" accept=".jpg,.jpeg,.png,.pdf" required>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-secondary">
                            <?php echo $document['scan_original_name'] ? 'Заменить' : 'Загрузить'; ?>
                        </button>
                    </div>
                </form>
                <div class="form-hint mt-2">JPG, PNG или PDF, до 10 МБ.</div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h3 class="card-title mb-0">Готовый документ</h3>
                    <div class="text-secondary">Заполняет шаблон данными выше и отдаёт .docx</div>
                </div>
                <a href="document_generate.php?document_id=<?php echo (int) $document['id']; ?>" class="btn btn-primary">
                    <i class="ti ti-download"></i>
                    Скачать .docx
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
