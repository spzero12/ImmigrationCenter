<?php

require __DIR__ . '/includes/auth.php';
requireLogin();

$pageTitle = 'Документы';
$extraScripts = ['js/documents.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="page-header d-print-none mb-3">
    <h2 class="page-title">Разрешительные документы</h2>
</div>

<div class="row row-deck row-cards mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="subheader">Просрочено</div>
                <div class="h1 mb-0 text-danger" id="stat-expired">—</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="subheader">Скоро истекает</div>
                <div class="h1 mb-0 text-warning" id="stat-expiring">—</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="input-icon w-auto">
            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
            <input type="text" id="document-search" class="form-control" placeholder="Поиск по сотруднику...">
        </div>
        <div class="card-actions">
            <select id="document-status-filter" class="form-select w-auto">
                <option value="">Все статусы</option>
                <option value="expired">Просрочен</option>
                <option value="expiring_soon">Скоро истекает</option>
                <option value="valid">В порядке</option>
                <option value="none">Без срока</option>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter table-sm card-table" id="documents-table">
            <thead>
                <tr>
                    <th>Тип документа</th>
                    <th>Номер</th>
                    <th>Срок действия</th>
                    <th>Статус</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="5">Загрузка...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    const CAN_DELETE = <?php echo in_array(currentUser()['role'], ['admin', 'hr'], true) ? 'true' : 'false'; ?>;
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
