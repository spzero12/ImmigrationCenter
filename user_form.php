<?php

require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/audit.php';
requireRole(['admin']);

$db = getDb();

$user = [
    'id' => null,
    'username' => '',
    'full_name' => '',
    'role' => 'hr',
];

$isEdit = isset($_GET['id']);

if ($isEdit) {
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([(int) $_GET['id']]);
    $found = $stmt->fetch();

    if (!$found) {
        http_response_code(404);
        exit('Пользователь не найден');
    }

    $user = $found;
}

$roleLabels = [
    'admin' => 'Администратор',
    'hr' => 'HR',
    'lawyer' => 'Юрист',
    'manager' => 'Руководитель',
];

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user['username'] = trim($_POST['username'] ?? '');
    $user['full_name'] = trim($_POST['full_name'] ?? '');
    $user['role'] = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($user['username'] === '' || $user['full_name'] === '') {
        $error = 'Укажите логин и ФИО';
    } elseif (!array_key_exists($user['role'], $roleLabels)) {
        $error = 'Выберите роль';
    } elseif (!$isEdit && $password === '') {
        $error = 'Укажите пароль для нового пользователя';
    } elseif ($password !== '' && strlen($password) < 6) {
        $error = 'Пароль должен быть не короче 6 символов';
    } else {
        $dupStmt = $db->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $dupStmt->execute([$user['username'], $user['id'] ?? 0]);

        if ($dupStmt->fetch()) {
            $error = 'Такой логин уже занят';
        } else {
            if ($isEdit) {
                if ($password !== '') {
                    $stmt = $db->prepare('UPDATE users SET username = ?, full_name = ?, role = ?, password_hash = ? WHERE id = ?');
                    $stmt->execute([$user['username'], $user['full_name'], $user['role'], password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                } else {
                    $stmt = $db->prepare('UPDATE users SET username = ?, full_name = ?, role = ? WHERE id = ?');
                    $stmt->execute([$user['username'], $user['full_name'], $user['role'], $user['id']]);
                }
                logAudit('update', 'user', (int) $user['id'], $user['username']);
            } else {
                $stmt = $db->prepare('INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)');
                $stmt->execute([$user['username'], password_hash($password, PASSWORD_DEFAULT), $user['full_name'], $user['role']]);
                logAudit('create', 'user', (int) $db->lastInsertId(), $user['username']);
            }

            header('Location: users.php');
            exit;
        }
    }
}

$pageTitle = $isEdit ? 'Редактирование пользователя' : 'Новый пользователь';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header d-print-none mb-3">
    <h2 class="page-title"><?php echo htmlspecialchars($pageTitle); ?></h2>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label required">Логин</label>
                        <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">ФИО</label>
                        <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label required">Роль</label>
                        <select class="form-select" name="role" required>
                            <?php foreach ($roleLabels as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $user['role'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label <?php echo $isEdit ? '' : 'required'; ?>">Пароль</label>
                        <input type="password" class="form-control" name="password" <?php echo $isEdit ? '' : 'required'; ?>>
                        <?php if ($isEdit): ?>
                            <div class="form-hint">Оставьте пустым, чтобы не менять текущий пароль.</div>
                        <?php endif; ?>
                    </div>

                    <div class="form-footer">
                        <a href="users.php" class="btn btn-link">Отмена</a>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
