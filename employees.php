<?php

require __DIR__ . '/includes/auth.php';
requireLogin();

$pageTitle = 'Сотрудники';
$extraScripts = ['js/employees.js'];
require __DIR__ . '/includes/header.php';
?>

<main class="container">
    <div class="page-header">
        <h1>Иностранные сотрудники</h1>
        <a class="btn" href="employee_form.php">+ Добавить сотрудника</a>
    </div>

    <input type="text" id="employee-search" placeholder="Поиск по ФИО, должности, гражданству...">

    <table class="table" id="employees-table">
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
</main>

<script>
    const CAN_DELETE = <?php echo in_array(currentUser()['role'], ['admin', 'hr'], true) ? 'true' : 'false'; ?>;
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
