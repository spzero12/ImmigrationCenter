<?php

require __DIR__ . '/includes/auth.php';
requireRole(['admin']);

$db = getDb();

$actionFilter = $_GET['action'] ?? '';
$entityTypeFilter = $_GET['entity_type'] ?? '';

$sql = 'SELECT a.*, u.full_name AS user_name
        FROM audit_log a
        LEFT JOIN users u ON u.id = a.user_id
        WHERE 1 = 1';
$params = [];

if (in_array($actionFilter, ['create', 'update', 'delete'], true)) {
    $sql .= ' AND a.action = ?';
    $params[] = $actionFilter;
}

if (in_array($entityTypeFilter, ['employee', 'employee_document'], true)) {
    $sql .= ' AND a.entity_type = ?';
    $params[] = $entityTypeFilter;
}

$sql .= ' ORDER BY a.created_at DESC LIMIT 200';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();

$actionLabels = [
    'create' => 'Создание',
    'update' => 'Изменение',
    'delete' => 'Удаление',
];

$entityTypeLabels = [
    'employee' => 'Сотрудник',
    'employee_document' => 'Документ',
];

$actionBadge = [
    'create' => 'bg-success-lt',
    'update' => 'bg-info-lt',
    'delete' => 'bg-danger-lt',
];

$pageTitle = 'Журнал аудита';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header d-print-none mb-3">
    <h2 class="page-title">Журнал аудита</h2>
</div>

<div class="card">
    <div class="card-header">
        <form method="get" class="d-flex gap-2">
            <select name="action" class="form-select w-auto" onchange="this.form.submit()">
                <option value="">Все действия</option>
                <?php foreach ($actionLabels as $value => $label): ?>
                    <option value="<?php echo $value; ?>" <?php echo $actionFilter === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="entity_type" class="form-select w-auto" onchange="this.form.submit()">
                <option value="">Все типы</option>
                <?php foreach ($entityTypeLabels as $value => $label): ?>
                    <option value="<?php echo $value; ?>" <?php echo $entityTypeFilter === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-vcenter table-sm card-table">
            <thead>
                <tr>
                    <th>Дата и время</th>
                    <th>Пользователь</th>
                    <th>Действие</th>
                    <th>Тип</th>
                    <th>Запись</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($entries)): ?>
                    <tr><td colspan="5">Записей нет</td></tr>
                <?php else: foreach ($entries as $entry): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($entry['created_at']); ?></td>
                        <td><?php echo htmlspecialchars($entry['user_name'] ?? '—'); ?></td>
                        <td><span class="badge <?php echo $actionBadge[$entry['action']]; ?>"><?php echo htmlspecialchars($actionLabels[$entry['action']]); ?></span></td>
                        <td><?php echo htmlspecialchars($entityTypeLabels[$entry['entity_type']] ?? $entry['entity_type']); ?></td>
                        <td><?php echo htmlspecialchars($entry['entity_label']); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer text-secondary">Последние 200 записей</div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
