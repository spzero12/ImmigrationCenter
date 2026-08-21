<?php

require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/documents.php';
require_once __DIR__ . '/includes/audit.php';
requireLogin();

$document = [
    'id' => null,
    'employee_id' => (int) ($_GET['employee_id'] ?? 0),
    'type' => array_key_first(DOCUMENT_TYPE_LABELS),
    'passport_document_id' => null,
    'number' => '',
    'series' => '',
    'issue_date' => '',
    'expire_date' => '',
    'issued_by' => '',
    'notes' => '',
    'field_overrides' => null,
    'extra_fields' => null,
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

$employeePassports = getEmployeePassports($db, $employee['id']);
$defaultPassportId = $employeePassports[0]['id'] ?? null;

// Без хотя бы одного паспорта нельзя создать никакой другой документ —
// паспорт здесь обязательная точка входа (см. спецификацию фичи
// passport-centric-documents).
$availableTypes = (!$isEdit && empty($employeePassports))
    ? ['passport' => DOCUMENT_TYPE_LABELS['passport']]
    : DOCUMENT_TYPE_LABELS;

if (!$isEdit && empty($employeePassports)) {
    $document['type'] = 'passport';
}

if (!$isEdit && $document['passport_document_id'] === null) {
    $document['passport_document_id'] = $defaultPassportId;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $document['type'] = $_POST['type'] ?? '';
    $document['number'] = trim($_POST['number'] ?? '');
    $document['series'] = trim($_POST['series'] ?? '');
    $document['issue_date'] = $_POST['issue_date'] ?: null;
    $document['expire_date'] = $_POST['expire_date'] ?: null;
    $document['issued_by'] = trim($_POST['issued_by'] ?? '');
    $document['notes'] = trim($_POST['notes'] ?? '');
    $document['passport_document_id'] = $document['type'] === 'passport'
        ? null
        : ((int) ($_POST['passport_document_id'] ?? 0) ?: null);

    $extraValues = [];
    foreach (DOCUMENT_TYPE_EXTRA_FIELDS[$document['type']] ?? [] as $key => $label) {
        $value = trim($_POST['extra_' . $key] ?? '');

        if ($value !== '') {
            $extraValues[$key] = $value;
        }
    }
    $extraFieldsJson = $extraValues ? json_encode($extraValues, JSON_UNESCAPED_UNICODE) : null;

    if (!array_key_exists($document['type'], DOCUMENT_TYPE_LABELS)) {
        $error = 'Выберите тип документа';
    } elseif ($document['type'] !== 'passport' && empty($employeePassports)) {
        $error = 'Сначала добавьте паспорт сотруднику — остальные документы привязываются к нему';
    } elseif ($document['type'] !== 'passport' && !$document['passport_document_id']) {
        $error = 'Выберите паспорт, к которому относится документ';
    } else {
        $label = DOCUMENT_TYPE_LABELS[$document['type']] . ' ' . $document['number'] . ' — ' . $employee['full_name'];

        if ($isEdit) {
            $stmt = $db->prepare(
                'UPDATE employee_documents SET type = ?, passport_document_id = ?, number = ?, series = ?, issue_date = ?, expire_date = ?, issued_by = ?, notes = ?, extra_fields = ? WHERE id = ?'
            );
            $stmt->execute([
                $document['type'], $document['passport_document_id'], $document['number'], $document['series'],
                $document['issue_date'], $document['expire_date'], $document['issued_by'],
                $document['notes'], $extraFieldsJson, $document['id'],
            ]);
            logAudit('update', 'employee_document', (int) $document['id'], $label);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO employee_documents (employee_id, type, passport_document_id, number, series, issue_date, expire_date, issued_by, notes, extra_fields, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $document['employee_id'], $document['type'], $document['passport_document_id'], $document['number'], $document['series'],
                $document['issue_date'], $document['expire_date'], $document['issued_by'],
                $document['notes'], $extraFieldsJson, currentUser()['id'],
            ]);
            logAudit('create', 'employee_document', (int) $db->lastInsertId(), $label);
        }

        header('Location: employee_form.php?id=' . $document['employee_id']);
        exit;
    }
}

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

                <?php if (empty($employeePassports) && !$isEdit): ?>
                    <div class="alert alert-info">
                        У сотрудника ещё нет ни одного паспорта. Остальные типы
                        документов (виза, патент, разрешение на работу, регистрация)
                        привязываются к конкретному паспорту, поэтому сначала нужно
                        добавить его.
                    </div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label required">Тип документа</label>
                        <select class="form-select" name="type" id="document-type" required <?php echo (empty($employeePassports) && !$isEdit) ? 'disabled' : ''; ?>>
                            <?php foreach ($availableTypes as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $document['type'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($employeePassports) && !$isEdit): ?>
                            <input type="hidden" name="type" value="passport">
                        <?php endif; ?>
                    </div>

                    <div class="mb-3" id="passport-select-wrapper" <?php echo $document['type'] === 'passport' ? 'style="display:none"' : ''; ?>>
                        <label class="form-label required">Паспорт</label>
                        <select class="form-select" name="passport_document_id">
                            <?php foreach ($employeePassports as $employeePassport): ?>
                                <option value="<?php echo (int) $employeePassport['id']; ?>" <?php echo (int) $document['passport_document_id'] === (int) $employeePassport['id'] ? 'selected' : ''; ?>>
                                    №<?php echo htmlspecialchars($employeePassport['number'] ?: '—'); ?>
                                    от <?php echo htmlspecialchars(formatRuDate($employeePassport['issue_date']) ?: '—'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-hint">Документ привязывается к конкретному паспорту — при выдаче сотруднику нового паспорта старые документы за ним не переезжают автоматически.</div>
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

                    <?php $existingExtraValues = json_decode($document['extra_fields'] ?? '', true) ?: []; ?>
                    <?php foreach (DOCUMENT_TYPE_EXTRA_FIELDS as $extraType => $extraFields): ?>
                        <div class="extra-fields-group row" data-extra-type="<?php echo htmlspecialchars($extraType); ?>" style="<?php echo $document['type'] === $extraType ? '' : 'display:none'; ?>">
                            <?php foreach ($extraFields as $key => $label): ?>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?php echo htmlspecialchars($label); ?></label>
                                    <input type="text" class="form-control" name="extra_<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($existingExtraValues[$key] ?? ''); ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="mb-3">
                        <label class="form-label">Примечания</label>
                        <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($document['notes'] ?? ''); ?></textarea>
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

<script>
    $(function () {
        $('#document-type').on('change', function () {
            const type = $(this).val();
            $('#passport-select-wrapper').toggle(type !== 'passport');
            $('.extra-fields-group').each(function () {
                $(this).toggle($(this).data('extra-type') === type);
            });
        });
    });
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
