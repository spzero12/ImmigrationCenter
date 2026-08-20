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

<main class="container container--narrow">
    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

    <?php if ($error): ?>
        <p class="alert alert-error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form method="post" class="form">
        <label>ФИО
            <input type="text" name="full_name" value="<?php echo htmlspecialchars($employee['full_name']); ?>" required>
        </label>
        <label>Гражданство
            <input type="text" name="citizenship" value="<?php echo htmlspecialchars($employee['citizenship'] ?? ''); ?>">
        </label>
        <label>Дата рождения
            <input type="date" name="birth_date" value="<?php echo htmlspecialchars($employee['birth_date'] ?? ''); ?>">
        </label>
        <label>Должность
            <input type="text" name="position" value="<?php echo htmlspecialchars($employee['position'] ?? ''); ?>">
        </label>
        <label>Подразделение
            <input type="text" name="department" value="<?php echo htmlspecialchars($employee['department'] ?? ''); ?>">
        </label>
        <label>Телефон
            <input type="text" name="phone" value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
        </label>
        <label>Email
            <input type="email" name="email" value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>">
        </label>
        <label>Примечания
            <textarea name="notes"><?php echo htmlspecialchars($employee['notes'] ?? ''); ?></textarea>
        </label>

        <div class="form-actions">
            <button type="submit">Сохранить</button>
            <a href="employees.php" class="btn btn-secondary">Отмена</a>
        </div>
    </form>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
