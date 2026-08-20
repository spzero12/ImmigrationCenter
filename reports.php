<?php

require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/documents.php';
requireLogin();

$db = getDb();

// 1. Сводка по сотрудникам.
$employees = $db->query('SELECT id, full_name, citizenship, department FROM employees ORDER BY full_name')->fetchAll();
$totalEmployees = count($employees);

$byCitizenship = [];
$byDepartment = [];

foreach ($employees as $employee) {
    $citizenship = $employee['citizenship'] ?: '—';
    $department = $employee['department'] ?: '—';
    $byCitizenship[$citizenship] = ($byCitizenship[$citizenship] ?? 0) + 1;
    $byDepartment[$department] = ($byDepartment[$department] ?? 0) + 1;
}

$docCounts = [];
$nearestExpireByEmployee = [];

foreach ($db->query('SELECT employee_id, expire_date FROM employee_documents ORDER BY expire_date IS NULL, expire_date ASC') as $row) {
    $docCounts[$row['employee_id']] = ($docCounts[$row['employee_id']] ?? 0) + 1;

    if (!array_key_exists($row['employee_id'], $nearestExpireByEmployee)) {
        $nearestExpireByEmployee[$row['employee_id']] = $row['expire_date'];
    }
}

// 2. Статусы документов на выбранную дату.
$statusDate = $_GET['status_date'] ?? date('Y-m-d');
$statusCounts = ['expired' => 0, 'expiring_soon' => 0, 'valid' => 0, 'none' => 0];

foreach ($db->query('SELECT expire_date FROM employee_documents') as $row) {
    $status = documentStatus($row['expire_date'], $statusDate);
    $statusCounts[$status['key']]++;
}

// 3. История добавлений за период.
$historyFrom = $_GET['history_from'] ?? date('Y-m-d', strtotime('-30 days'));
$historyTo = $_GET['history_to'] ?? date('Y-m-d');

$newEmployeesStmt = $db->prepare('SELECT id, full_name, created_at FROM employees WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC');
$newEmployeesStmt->execute([$historyFrom, $historyTo]);
$newEmployees = $newEmployeesStmt->fetchAll();

$newDocsStmt = $db->prepare(
    'SELECT d.id, d.type, d.number, d.created_at, e.full_name AS employee_name
     FROM employee_documents d JOIN employees e ON e.id = d.employee_id
     WHERE DATE(d.created_at) BETWEEN ? AND ? ORDER BY d.created_at DESC'
);
$newDocsStmt->execute([$historyFrom, $historyTo]);
$newDocs = $newDocsStmt->fetchAll();

$pageTitle = 'Отчёты';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header d-print-none mb-3">
    <h2 class="page-title">Отчёты</h2>
    <div class="page-actions">
        <a href="report_export.php" class="btn btn-outline-primary">
            <i class="ti ti-download"></i>
            Экспорт сводки в Excel
        </a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">Сводка по сотрудникам</h3>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-4">
                <div class="subheader">Всего сотрудников</div>
                <div class="h1"><?php echo $totalEmployees; ?></div>
            </div>
            <div class="col-md-4">
                <div class="subheader">По гражданству</div>
                <?php foreach ($byCitizenship as $label => $count): ?>
                    <div><?php echo htmlspecialchars($label); ?>: <strong><?php echo $count; ?></strong></div>
                <?php endforeach; ?>
            </div>
            <div class="col-md-4">
                <div class="subheader">По подразделениям</div>
                <?php foreach ($byDepartment as $label => $count): ?>
                    <div><?php echo htmlspecialchars($label); ?>: <strong><?php echo $count; ?></strong></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter table-sm card-table">
            <thead>
                <tr>
                    <th>ФИО</th>
                    <th>Гражданство</th>
                    <th>Подразделение</th>
                    <th>Документов</th>
                    <th>Ближайший срок</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($employees)): ?>
                    <tr><td colspan="5">Сотрудников нет</td></tr>
                <?php else: foreach ($employees as $employee): $status = documentStatus($nearestExpireByEmployee[$employee['id']] ?? null); ?>
                    <tr>
                        <td><a href="employee_form.php?id=<?php echo (int) $employee['id']; ?>"><?php echo htmlspecialchars($employee['full_name']); ?></a></td>
                        <td><?php echo htmlspecialchars($employee['citizenship'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($employee['department'] ?? ''); ?></td>
                        <td><?php echo $docCounts[$employee['id']] ?? 0; ?></td>
                        <td><span class="badge <?php echo $status['badgeClass']; ?>"><?php echo htmlspecialchars($status['label']); ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">Статусы документов на дату</h3>
        <div class="card-actions">
            <form method="get" class="d-flex align-items-center gap-2">
                <input type="date" name="status_date" class="form-control" value="<?php echo htmlspecialchars($statusDate); ?>">
                <button type="submit" class="btn btn-outline-secondary">Пересчитать</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <div class="row row-deck row-cards">
            <div class="col-sm-6 col-lg-3">
                <div class="subheader">Просрочено</div>
                <div class="h2 text-danger"><?php echo $statusCounts['expired']; ?></div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="subheader">Скоро истекает</div>
                <div class="h2 text-warning"><?php echo $statusCounts['expiring_soon']; ?></div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="subheader">В порядке</div>
                <div class="h2 text-success"><?php echo $statusCounts['valid']; ?></div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="subheader">Без срока</div>
                <div class="h2 text-secondary"><?php echo $statusCounts['none']; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">История добавлений за период</h3>
        <div class="card-actions">
            <form method="get" class="d-flex align-items-center gap-2">
                <input type="date" name="history_from" class="form-control" value="<?php echo htmlspecialchars($historyFrom); ?>">
                <span>—</span>
                <input type="date" name="history_to" class="form-control" value="<?php echo htmlspecialchars($historyTo); ?>">
                <button type="submit" class="btn btn-outline-secondary">Показать</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <p><strong>Новых сотрудников:</strong> <?php echo count($newEmployees); ?>, <strong>новых документов:</strong> <?php echo count($newDocs); ?></p>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter table-sm card-table">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Тип</th>
                    <th>Запись</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($newEmployees) && empty($newDocs)): ?>
                    <tr><td colspan="3">За этот период ничего не добавлено</td></tr>
                <?php else: ?>
                    <?php foreach ($newEmployees as $employee): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($employee['created_at']); ?></td>
                            <td>Сотрудник</td>
                            <td><a href="employee_form.php?id=<?php echo (int) $employee['id']; ?>"><?php echo htmlspecialchars($employee['full_name']); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ($newDocs as $doc): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($doc['created_at']); ?></td>
                            <td>Документ</td>
                            <td><?php echo htmlspecialchars((DOCUMENT_TYPE_LABELS[$doc['type']] ?? $doc['type']) . ' ' . $doc['number'] . ' — ' . $doc['employee_name']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
