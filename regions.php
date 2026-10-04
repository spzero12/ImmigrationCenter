<?php

require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/regions.php';
requireLogin();

$db = getDb();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $nameDative = trim($_POST['name_dative'] ?? '');

    if ($name === '' || $nameDative === '') {
        $error = 'Укажите название и форму для адресата';
    } else {
        $dupStmt = $db->prepare('SELECT id FROM regions WHERE name = ? AND id != ?');
        $dupStmt->execute([$name, $id]);

        if ($dupStmt->fetch()) {
            $error = 'Регион с таким названием уже есть';
        } elseif ($id > 0) {
            $db->prepare('UPDATE regions SET name = ?, name_dative = ? WHERE id = ?')->execute([$name, $nameDative, $id]);
        } else {
            $db->prepare('INSERT INTO regions (name, name_dative) VALUES (?, ?)')->execute([$name, $nameDative]);
        }

        if (!$error) {
            header('Location: regions.php');
            exit;
        }
    }
}

$regions = $db->query(
    'SELECT r.*, (SELECT COUNT(*) FROM employees e WHERE e.region_id = r.id) AS employee_count FROM regions r ORDER BY r.name'
)->fetchAll();

$pageTitle = 'Регионы';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header d-print-none mb-3">
    <h2 class="page-title">Регионы</h2>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <div class="form-hint mb-3">«Для адресата» — форма в предложном падеже, подставляется в документы: «УВМ ГУ МВД России по <em>г. Москве</em>».</div>
                <?php foreach ($regions as $region): ?>
                    <form method="post" class="row g-2 align-items-center mb-2">
                        <input type="hidden" name="id" value="<?php echo (int) $region['id']; ?>">
                        <div class="col-md-4"><input type="text" class="form-control form-control-sm" name="name" value="<?php echo htmlspecialchars($region['name']); ?>"></div>
                        <div class="col-md-4"><input type="text" class="form-control form-control-sm" name="name_dative" value="<?php echo htmlspecialchars($region['name_dative']); ?>"></div>
                        <div class="col-auto"><button type="submit" class="btn btn-sm btn-outline-secondary">Сохранить</button></div>
                        <div class="col-auto"><button type="button" class="btn btn-sm btn-danger" data-delete-region="<?php echo (int) $region['id']; ?>">Удалить</button></div>
                        <div class="col-auto text-secondary">сотрудников: <?php echo (int) $region['employee_count']; ?></div>
                    </form>
                <?php endforeach; ?>
                <hr>
                <form method="post" class="row g-2 align-items-center">
                    <div class="col-md-4"><input type="text" class="form-control form-control-sm" name="name" placeholder="Название (Москва)" required></div>
                    <div class="col-md-4"><input type="text" class="form-control form-control-sm" name="name_dative" placeholder="Для адресата (г. Москве)" required></div>
                    <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">Добавить</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $(function () {
        $('[data-delete-region]').on('click', function () {
            if (!confirm('Удалить регион? У сотрудников регион станет не указан.')) {
                return;
            }

            $.ajax({ url: 'api/regions.php', method: 'DELETE', data: { id: $(this).data('delete-region') } })
                .done(function () { location.reload(); });
        });
    });
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
