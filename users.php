<?php

require __DIR__ . '/includes/auth.php';
requireRole(['admin']);

$db = getDb();
$users = $db->query('SELECT * FROM users ORDER BY full_name')->fetchAll();

$roleLabels = [
    'admin' => 'Администратор',
    'hr' => 'HR',
    'lawyer' => 'Юрист',
    'manager' => 'Руководитель',
];

$pageTitle = 'Пользователи';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header d-print-none mb-3">
    <h2 class="page-title">Пользователи</h2>
    <div class="page-actions">
        <a href="user_form.php" class="btn btn-primary">
            <i class="ti ti-plus"></i>
            Добавить пользователя
        </a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter table-sm card-table">
            <thead>
                <tr>
                    <th>Логин</th>
                    <th>ФИО</th>
                    <th>Роль</th>
                    <th>Создан</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($roleLabels[$user['role']] ?? $user['role']); ?></td>
                        <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                        <td class="text-end">
                            <a href="user_form.php?id=<?php echo (int) $user['id']; ?>">Изменить</a>
                            <?php if ((int) $user['id'] !== (int) currentUser()['id']): ?>
                                <button type="button" class="btn btn-danger btn-sm ms-2" data-delete-user="<?php echo (int) $user['id']; ?>">Удалить</button>
                            <?php else: ?>
                                <span class="text-secondary ms-2">это вы</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    $(function () {
        $('[data-delete-user]').on('click', function () {
            if (!confirm('Удалить пользователя?')) {
                return;
            }

            const id = $(this).data('delete-user');

            $.ajax({
                url: 'api/users.php',
                method: 'DELETE',
                data: { id: id },
            }).done(function () {
                location.reload();
            }).fail(function (xhr) {
                alert((xhr.responseJSON && xhr.responseJSON.error) || 'Не удалось удалить пользователя');
            });
        });
    });
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
