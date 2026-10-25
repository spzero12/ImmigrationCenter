<?php

require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/processes.php';
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
        <div class="d-flex flex-wrap gap-2">
            <select id="organization-filter" class="form-select w-auto" aria-label="Организация">
                <option value="">Все организации</option>
            </select>
            <select id="work-site-filter" class="form-select w-auto" aria-label="Объект">
                <option value="">Все объекты</option>
            </select>
            <div class="btn-group" role="group" aria-label="Быстрые фильтры по срокам">
                <button type="button" class="btn btn-outline-danger quick-filter" data-key="red" aria-pressed="false">
                    <span class="indicator indicator-red"></span> Красные <span class="quick-count badge bg-danger-lt ms-1"></span>
                </button>
                <button type="button" class="btn btn-outline-warning quick-filter" data-key="yellow" aria-pressed="false">
                    <span class="indicator indicator-yellow"></span> Жёлтые <span class="quick-count badge bg-warning-lt ms-1"></span>
                </button>
            </div>
            <div class="input-icon w-auto">
                <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                <input type="text" id="employee-search" class="form-control" placeholder="Поиск по ФИО, паспорту, должности...">
            </div>
        </div>
        <div class="card-actions d-flex gap-2">
            <?php // Описи у процессов разные: тип описи выбирается в этой же кнопке (меню). ?>
            <div class="dropdown">
                <button type="button" class="btn btn-outline-primary dropdown-toggle" id="inventory-button" data-bs-toggle="dropdown" aria-expanded="false" disabled
                        title="Отметьте сотрудников галочками слева">
                    <i class="ti ti-list-details"></i>
                    Сформировать опись <span id="inventory-count"></span>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php foreach (INVENTORY_TYPES as $inventoryType => $inventoryDefinition): ?>
                        <a class="dropdown-item" href="#" data-inventory-type="<?php echo htmlspecialchars($inventoryType); ?>">
                            <div style="max-width: 22rem;">
                                <div class="fw-bold"><?php echo htmlspecialchars($inventoryDefinition['label']); ?></div>
                                <div class="text-secondary small text-wrap"><?php echo htmlspecialchars($inventoryDefinition['description']); ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <a class="btn btn-primary" href="employee_form.php">
                <i class="ti ti-plus"></i>
                Добавить сотрудника
            </a>
        </div>
    </div>
    <div class="table-responsive employees-wrap">
        <table class="table table-vcenter table-sm card-table" id="employees-table">
            <colgroup>
                <col class="c-sel"><col class="c-name"><col class="c-pass"><col class="c-irs"><col class="c-site">
                <col class="c-date"><col class="c-date"><col class="c-date"><col class="c-date"><col class="c-date"><col class="c-date">
                <col class="c-docs"><col class="c-act">
            </colgroup>
            <thead>
                <tr>
                    <th class="w-1"></th>
                    <th>ФИО</th>
                    <th>№ паспорта</th>
                    <th>№ ИРС</th>
                    <th>Объект</th>
                    <th>Паспорт до</th>
                    <th>Виза до</th>
                    <th>РНР до</th>
                    <th>МУ до</th>
                    <th>Мед. справка до</th>
                    <th>ДМС до</th>
                    <th>Документы</th>
                    <th></th>
                </tr>
                <tr class="column-filters">
                    <th></th>
                    <?php for ($col = 1; $col <= 11; $col++): ?>
                    <?php /* фильтры только у ФИО, паспорта и ИРС; у сроков (5–10) и «Документов» (11) не нужны */ ?>
                    <th><?php if ($col <= 3): ?><input type="text" class="form-control form-control-sm column-filter" data-col="<?php echo $col; ?>" placeholder="Фильтр" aria-label="Фильтр по столбцу"><?php endif; ?></th>
                    <?php endfor; ?>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="13">Загрузка...</td></tr>
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
