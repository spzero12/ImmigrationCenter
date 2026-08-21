<?php

require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/organizations.php';
requireLogin();

$db = getDb();

$organization = ['id' => null, 'name' => ''];
$isEdit = isset($_GET['id']);

if ($isEdit) {
    $stmt = $db->prepare('SELECT * FROM organizations WHERE id = ?');
    $stmt->execute([(int) $_GET['id']]);
    $found = $stmt->fetch();

    if (!$found) {
        http_response_code(404);
        exit('Организация не найдена');
    }

    $organization = $found;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $organization['name'] = trim($_POST['name'] ?? '');

    if ($organization['name'] === '') {
        $error = 'Укажите название организации';
    } else {
        $dupStmt = $db->prepare('SELECT id FROM organizations WHERE name = ? AND id != ?');
        $dupStmt->execute([$organization['name'], $organization['id'] ?? 0]);

        if ($dupStmt->fetch()) {
            $error = 'Организация с таким названием уже есть';
        } elseif ($isEdit) {
            $stmt = $db->prepare('UPDATE organizations SET name = ? WHERE id = ?');
            $stmt->execute([$organization['name'], $organization['id']]);
            header('Location: organization_form.php?id=' . $organization['id']);
            exit;
        } else {
            $stmt = $db->prepare('INSERT INTO organizations (name) VALUES (?)');
            $stmt->execute([$organization['name']]);
            header('Location: organization_form.php?id=' . $db->lastInsertId());
            exit;
        }
    }
}

$departments = $isEdit ? getDepartments($db, (int) $organization['id']) : [];

$pageTitle = $isEdit ? 'Редактирование организации' : 'Новая организация';
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
                        <label class="form-label required">Название</label>
                        <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($organization['name']); ?>" required>
                    </div>

                    <div class="form-footer">
                        <a href="organizations.php" class="btn btn-link">Отмена</a>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($isEdit): ?>
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title">Подразделения</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter table-sm card-table">
                    <tbody>
                        <?php if (empty($departments)): ?>
                            <tr><td>Подразделений пока нет</td></tr>
                        <?php else: foreach ($departments as $department): ?>
                            <tr>
                                <td>
                                    <form method="post" action="department_form.php" class="row g-2 align-items-center">
                                        <input type="hidden" name="id" value="<?php echo (int) $department['id']; ?>">
                                        <input type="hidden" name="organization_id" value="<?php echo (int) $organization['id']; ?>">
                                        <div class="col-auto flex-grow-1">
                                            <input type="text" class="form-control form-control-sm" name="name" value="<?php echo htmlspecialchars($department['name']); ?>">
                                        </div>
                                        <div class="col-auto">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Сохранить</button>
                                        </div>
                                        <div class="col-auto">
                                            <button type="button" class="btn btn-sm btn-danger" data-delete-department="<?php echo (int) $department['id']; ?>">Удалить</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        <tr>
                            <td>
                                <form method="post" action="department_form.php" class="row g-2 align-items-center">
                                    <input type="hidden" name="organization_id" value="<?php echo (int) $organization['id']; ?>">
                                    <div class="col-auto flex-grow-1">
                                        <input type="text" class="form-control form-control-sm" name="name" placeholder="Новое подразделение" required>
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn btn-sm btn-primary">Добавить</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <script>
            $(function () {
                $('[data-delete-department]').on('click', function () {
                    if (!confirm('Удалить подразделение?')) {
                        return;
                    }

                    const id = $(this).data('delete-department');

                    $.ajax({
                        url: 'api/departments.php',
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
