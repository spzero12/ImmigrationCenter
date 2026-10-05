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
            <input type="text" id="employee-search" class="form-control" placeholder="Поиск по ФИО, паспорту, должности...">
        </div>
        <div class="card-actions d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" id="inventory-button" disabled>
                <i class="ti ti-list-details"></i>
                Сформировать опись <span id="inventory-count"></span>
            </button>
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
                    <th class="w-1"></th>
                    <th>ФИО</th>
                    <th>№ паспорта</th>
                    <th>Паспорт до</th>
                    <th>Виза до</th>
                    <th>РНР до</th>
                    <th>МУ до</th>
                    <th>Мед. справка до</th>
                    <th>ДМС до</th>
                    <th>Документы</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="11">Загрузка...</td></tr>
            </tbody>
        </table>
    </div>
    <div class="card-footer text-secondary">
        <span class="indicator indicator-red"></span> срочно
        <span class="indicator indicator-yellow ms-3"></span> скоро
        <span class="indicator indicator-green ms-3"></span> в порядке
        <span class="indicator indicator-none ms-3"></span> нет данных.
        Строка с красным сроком обведена красной рамкой, с жёлтым — оранжевой; у жёлтых и красных значений подписан тип документа.
        Пороги отличаются по типам документов (паспорт: &lt;180 / 180–360 / &gt;360 дней; виза и РНР: 60 / 90; МУ и мед. справка: 30 / 60; ДМС: 14 / 30).
    </div>
</div>

<script>
    const CAN_DELETE = <?php echo in_array(currentUser()['role'], ['admin', 'hr'], true) ? 'true' : 'false'; ?>;
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
