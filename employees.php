<?php

require __DIR__ . '/includes/auth.php';
requireLogin();

$pageTitle = 'Сотрудники';
$extraScripts = ['js/employees.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="page-header d-print-none mb-3">
    <h2 class="page-title">Иностранные сотрудники</h2>
</div>

<div class="card">
    <div class="card-header">
        <div class="input-icon w-auto">
            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
            <input type="text" id="employee-search" class="form-control" placeholder="Поиск по ФИО, должности, гражданству...">
        </div>
        <div class="card-actions">
            <a class="btn btn-primary" href="employee_form.php">
                <i class="ti ti-plus"></i>
                Добавить сотрудника
            </a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter table-sm card-table" id="employees-table">
            <thead>
                <tr>
                    <th>ФИО</th>
                    <th>Гражданство</th>
                    <th>Должность</th>
                    <th>Подразделение</th>
                    <th>Телефон</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="6">Загрузка...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    const CAN_DELETE = <?php echo in_array(currentUser()['role'], ['admin', 'hr'], true) ? 'true' : 'false'; ?>;
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
