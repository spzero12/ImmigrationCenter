<?php

require __DIR__ . '/includes/auth.php';
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
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
