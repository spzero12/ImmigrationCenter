<?php

require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/organizations.php';
requireLogin();

$db = getDb();

$organizations = $db->query(
    'SELECT o.*,
        (SELECT COUNT(*) FROM departments d WHERE d.organization_id = o.id) AS department_count,
        (SELECT COUNT(*) FROM employees e WHERE e.organization_id = o.id) AS employee_count
     FROM organizations o
     ORDER BY o.name'
)->fetchAll();

$pageTitle = 'Организации';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header d-print-none mb-3">
    <h2 class="page-title">Организации</h2>
    <div class="page-actions">
        <a href="organization_form.php" class="btn btn-primary">
            <i class="ti ti-plus"></i>
            Добавить организацию
        </a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter table-sm card-table">
            <thead>
                <tr>
                    <th>Название</th>
                    <th>Подразделений</th>
                    <th>Сотрудников</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($organizations)): ?>
                    <tr><td colspan="4">Организаций пока нет</td></tr>
                <?php else: foreach ($organizations as $organization): ?>
                    <tr>
                        <td><a href="organization_form.php?id=<?php echo (int) $organization['id']; ?>"><?php echo htmlspecialchars($organization['name']); ?></a></td>
                        <td><?php echo (int) $organization['department_count']; ?></td>
                        <td><?php echo (int) $organization['employee_count']; ?></td>
                        <td class="text-end">
                            <a href="organization_form.php?id=<?php echo (int) $organization['id']; ?>">Изменить</a>
                            <button type="button" class="btn btn-danger btn-sm ms-2" data-delete-organization="<?php echo (int) $organization['id']; ?>">Удалить</button>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    $(function () {
        $('[data-delete-organization]').on('click', function () {
            if (!confirm('Удалить организацию? Подразделения будут удалены вместе с ней, у сотрудников организация станет не указана.')) {
                return;
            }

            const id = $(this).data('delete-organization');

            $.ajax({
                url: 'api/organizations.php',
                method: 'DELETE',
                data: { id: id },
            }).done(function () {
                location.reload();
            });
        });
    });
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
