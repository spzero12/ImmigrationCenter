<?php

require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/documents.php';
requireLogin();

$employee = [
    'id' => null,
    'full_name' => '',
    'citizenship' => '',
    'birth_date' => '',
    'position' => '',
    'department' => '',
    'phone' => '',
    'email' => '',
    'notes' => '',
];

$isEdit = isset($_GET['id']);

if ($isEdit) {
    $stmt = getDb()->prepare('SELECT * FROM employees WHERE id = ?');
    $stmt->execute([(int) $_GET['id']]);
    $found = $stmt->fetch();

    if (!$found) {
        http_response_code(404);
        exit('Сотрудник не найден');
    }

    $employee = $found;
}

$employeeDocuments = [];

if ($isEdit) {
    $docsStmt = getDb()->prepare('SELECT * FROM employee_documents WHERE employee_id = ? ORDER BY expire_date IS NULL, expire_date ASC');
    $docsStmt->execute([$employee['id']]);
    $employeeDocuments = $docsStmt->fetchAll();
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee['full_name'] = trim($_POST['full_name'] ?? '');
    $employee['citizenship'] = trim($_POST['citizenship'] ?? '');
    $employee['birth_date'] = $_POST['birth_date'] ?: null;
    $employee['position'] = trim($_POST['position'] ?? '');
    $employee['department'] = trim($_POST['department'] ?? '');
    $employee['phone'] = trim($_POST['phone'] ?? '');
    $employee['email'] = trim($_POST['email'] ?? '');
    $employee['notes'] = trim($_POST['notes'] ?? '');

    if ($employee['full_name'] === '') {
        $error = 'Укажите ФИО сотрудника';
    } else {
        $db = getDb();

        if ($isEdit) {
            $stmt = $db->prepare(
                'UPDATE employees SET full_name = ?, citizenship = ?, birth_date = ?, position = ?, department = ?, phone = ?, email = ?, notes = ? WHERE id = ?'
            );
            $stmt->execute([
                $employee['full_name'], $employee['citizenship'], $employee['birth_date'],
                $employee['position'], $employee['department'], $employee['phone'],
                $employee['email'], $employee['notes'], $employee['id'],
            ]);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO employees (full_name, citizenship, birth_date, position, department, phone, email, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $employee['full_name'], $employee['citizenship'], $employee['birth_date'],
                $employee['position'], $employee['department'], $employee['phone'],
                $employee['email'], $employee['notes'], currentUser()['id'],
            ]);
        }

        header('Location: employees.php');
        exit;
    }
}

$pageTitle = $isEdit ? 'Редактирование сотрудника' : 'Новый сотрудник';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header d-print-none mb-3">
    <h2 class="page-title"><?php echo htmlspecialchars($pageTitle); ?></h2>
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
                        <label class="form-label required">ФИО</label>
                        <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($employee['full_name']); ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Гражданство</label>
                            <input type="text" class="form-control" name="citizenship" value="<?php echo htmlspecialchars($employee['citizenship'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Дата рождения</label>
                            <input type="date" class="form-control" name="birth_date" value="<?php echo htmlspecialchars($employee['birth_date'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Должность</label>
                            <input type="text" class="form-control" name="position" value="<?php echo htmlspecialchars($employee['position'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Подразделение</label>
                            <input type="text" class="form-control" name="department" value="<?php echo htmlspecialchars($employee['department'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Телефон</label>
                            <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Примечания</label>
                        <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($employee['notes'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-footer">
                        <a href="employees.php" class="btn btn-link">Отмена</a>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($isEdit): ?>
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title">Документы сотрудника</h3>
                <div class="card-actions">
                    <a href="document_form.php?employee_id=<?php echo (int) $employee['id']; ?>" class="btn btn-primary btn-sm">
                        <i class="ti ti-plus"></i>
                        Добавить документ
                    </a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter table-sm card-table">
                    <thead>
                        <tr>
                            <th>Тип</th>
                            <th>Номер</th>
                            <th>Срок действия</th>
                            <th>Статус</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($employeeDocuments)): ?>
                            <tr><td colspan="5">Документов пока нет</td></tr>
                        <?php else: foreach ($employeeDocuments as $doc): $status = documentStatus($doc['expire_date']); ?>
                            <tr>
                                <td><?php echo htmlspecialchars(DOCUMENT_TYPE_LABELS[$doc['type']] ?? $doc['type']); ?></td>
                                <td><?php echo htmlspecialchars($doc['number'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($doc['expire_date'] ?? '—'); ?></td>
                                <td><span class="badge <?php echo $status['badgeClass']; ?>"><?php echo htmlspecialchars($status['label']); ?></span></td>
                                <td class="text-end">
                                    <a href="document_form.php?id=<?php echo (int) $doc['id']; ?>&employee_id=<?php echo (int) $employee['id']; ?>">Изменить</a>
                                    <?php if (in_array(currentUser()['role'], ['admin', 'hr'], true)): ?>
                                        <button type="button" class="btn btn-danger btn-sm ms-2" data-delete-document="<?php echo (int) $doc['id']; ?>">Удалить</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <script>
            $(function () {
                $('[data-delete-document]').on('click', function () {
                    if (!confirm('Удалить документ?')) {
                        return;
                    }

                    const id = $(this).data('delete-document');

                    $.ajax({
                        url: 'api/documents.php',
                        method: 'DELETE',
                        data: { id: id },
                    }).done(function () {
                        location.reload();
                    });
                });
            });
        </script>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
