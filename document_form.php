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
];

$isEdit = isset($_GET['id']);

if ($isEdit) {
    $stmt = getDb()->prepare('SELECT * FROM employee_documents WHERE id = ?');
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

$employeeStmt = getDb()->prepare('SELECT * FROM employees WHERE id = ?');
$employeeStmt->execute([$document['employee_id']]);
$employee = $employeeStmt->fetch();

if (!$employee) {
    http_response_code(404);
    exit('Сотрудник не найден');
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

    if (!array_key_exists($document['type'], DOCUMENT_TYPE_LABELS)) {
        $error = 'Выберите тип документа';
    } else {
        $db = getDb();

        if ($isEdit) {
            $stmt = $db->prepare(
                'UPDATE employee_documents SET type = ?, number = ?, series = ?, issue_date = ?, expire_date = ?, issued_by = ?, notes = ? WHERE id = ?'
            );
            $stmt->execute([
                $document['type'], $document['number'], $document['series'],
                $document['issue_date'], $document['expire_date'], $document['issued_by'],
                $document['notes'], $document['id'],
            ]);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO employee_documents (employee_id, type, number, series, issue_date, expire_date, issued_by, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $document['employee_id'], $document['type'], $document['number'], $document['series'],
                $document['issue_date'], $document['expire_date'], $document['issued_by'],
                $document['notes'], currentUser()['id'],
            ]);
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

                    <div class="form-footer">
                        <a href="employee_form.php?id=<?php echo (int) $employee['id']; ?>" class="btn btn-link">Отмена</a>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
