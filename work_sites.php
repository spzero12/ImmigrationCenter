<?php

// Объекты, на которых работают сотрудники: название, описание, адрес.

require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/work_sites.php';
requireLogin();

$db = getDb();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === '') {
        $error = 'Укажите название объекта';
    } else {
        $dupStmt = $db->prepare('SELECT id FROM work_sites WHERE name = ? AND id != ?');
        $dupStmt->execute([$name, $id]);

        if ($dupStmt->fetch()) {
            $error = 'Объект с таким названием уже есть';
        } elseif ($id > 0) {
            $db->prepare('UPDATE work_sites SET name = ?, description = ?, address = ? WHERE id = ?')
                ->execute([$name, $description !== '' ? $description : null, $address !== '' ? $address : null, $id]);
        } else {
            $db->prepare('INSERT INTO work_sites (name, description, address) VALUES (?, ?, ?)')
                ->execute([$name, $description !== '' ? $description : null, $address !== '' ? $address : null]);
        }

        if (!$error) {
            header('Location: work_sites.php');
            exit;
        }
    }
}

$workSites = $db->query(
    'SELECT w.*, (SELECT COUNT(*) FROM employees e WHERE e.work_site_id = w.id) AS employee_count FROM work_sites w ORDER BY w.name'
)->fetchAll();

$pageTitle = 'Объекты';
$extraScripts = ['js/work_sites.js'];
require __DIR__ . '/includes/header.php';
?>

<div class="page-header d-print-none mb-3">
    <h2 class="page-title">Объекты</h2>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <div class="form-hint mb-3">Объект — место, где работает сотрудник. Название показывается в таблице сотрудников.</div>
                <?php foreach ($workSites as $site): ?>
                    <form method="post" class="row g-2 align-items-start mb-3">
                        <input type="hidden" name="id" value="<?php echo (int) $site['id']; ?>">
                        <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="name" value="<?php echo htmlspecialchars($site['name']); ?>" placeholder="Название"></div>
                        <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="address" value="<?php echo htmlspecialchars((string) $site['address']); ?>" placeholder="Адрес"></div>
                        <div class="col-md-3"><textarea class="form-control form-control-sm" name="description" rows="2" placeholder="Описание"><?php echo htmlspecialchars((string) $site['description']); ?></textarea></div>
                        <div class="col-auto"><button type="submit" class="btn btn-sm btn-outline-secondary">Сохранить</button></div>
                        <div class="col-auto"><button type="button" class="btn btn-sm btn-danger" data-delete-site="<?php echo (int) $site['id']; ?>">Удалить</button></div>
                        <div class="col-auto text-secondary">сотрудников: <?php echo (int) $site['employee_count']; ?></div>
                    </form>
                <?php endforeach; ?>
                <?php if (!$workSites): ?>
                    <p class="text-secondary">Объектов пока нет.</p>
                <?php endif; ?>
                <hr>
                <form method="post" class="row g-2 align-items-start">
                    <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="name" placeholder="Название" required></div>
                    <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="address" placeholder="Адрес"></div>
                    <div class="col-md-3"><textarea class="form-control form-control-sm" name="description" rows="2" placeholder="Описание"></textarea></div>
                    <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">Добавить</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
