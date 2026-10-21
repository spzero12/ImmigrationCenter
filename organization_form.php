<?php

// Организация: реквизиты, бланк (.docx с колонтитулами), подписанты,
// подразделения. Спецификация: .agent-os/specs/2026-10-04-employee-card-processes/spec.md

require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/organizations.php';
requireLogin();

// Поля карточки по разделам выписки ЕГРЮЛ: поле => [подпись, тип, ширина col-md].
const ORGANIZATION_FIELD_GROUPS = [
    'Наименование и регистрация' => [
        'full_name' => ['Полное наименование (с ОПФ)', 'text', 12],
        'opf' => ['Организационно-правовая форма', 'text', 4],
        'status' => ['Статус', 'text', 4],
        'inn' => ['ИНН', 'text', 4],
        'ogrn' => ['ОГРН', 'text', 4],
        'ogrn_date' => ['Дата присвоения ОГРН', 'date', 4],
        'kpp' => ['КПП', 'text', 4],
        'registration_date' => ['Дата регистрации', 'date', 4],
        'egrul_date' => ['Дата внесения записи в ЕГРЮЛ', 'date', 4],
        'termination_date' => ['Дата прекращения деятельности', 'date', 4],
        'registration_authority' => ['Регистрирующий орган', 'text', 12],
        'tax_authority' => ['Налоговый орган', 'text', 8],
        'tax_registration_date' => ['Дата постановки на учёт в налоговом органе', 'date', 4],
    ],
    'Адреса и контакты' => [
        'legal_address' => ['Юридический (почтовый) адрес', 'text', 12],
        'actual_address' => ['Фактический адрес', 'text', 12],
        'phone' => ['Телефон', 'text', 6],
        'email' => ['Email', 'text', 6],
    ],
    'Руководитель и капитал' => [
        'director_position' => ['Должность руководителя', 'text', 4],
        'director_name' => ['ФИО руководителя', 'text', 5],
        'director_inn' => ['ИНН руководителя', 'text', 3],
        'authorized_capital' => ['Уставный капитал, руб.', 'number', 4],
        'founders' => ['Учредители (участники)', 'textarea', 12],
    ],
    'Виды деятельности и коды' => [
        'okved_main' => ['Основной вид деятельности (ОКВЭД)', 'text', 12],
        'okved_additional' => ['Дополнительные виды деятельности', 'textarea', 12],
        'okpo' => ['ОКПО', 'text', 3],
        'oktmo' => ['ОКТМО', 'text', 3],
        'okato' => ['ОКАТО', 'text', 3],
        'pfr_reg_number' => ['Рег. номер в ПФР', 'text', 3],
        'fss_reg_number' => ['Рег. номер в ФСС', 'text', 3],
    ],
];

$organizationFields = array_merge(...array_values(ORGANIZATION_FIELD_GROUPS));

$db = getDb();

$organization = ['id' => null, 'name' => ''];

foreach (array_keys($organizationFields) as $field) {
    $organization[$field] = '';
}

$isEdit = isset($_GET['id']);

if ($isEdit) {
    $stmt = $db->prepare('SELECT * FROM organizations WHERE id = ?');
    $stmt->execute([(int) $_GET['id']]);
    $found = $stmt->fetch();

    if (!$found) {
        http_response_code(404);
        exit('Организация не найдена');
    }

    $organization = $found;
}

$error = null;
$notice = null;
$action = $_POST['action'] ?? 'save';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isEdit && $action === 'add_signer') {
        $position = trim($_POST['position'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $shortName = trim($_POST['short_name'] ?? '');

        if ($position === '' || $fullName === '' || $shortName === '') {
            $error = 'Для подписанта укажите должность, ФИО и ФИО в формате «Иванов И.И.»';
        } else {
            $db->prepare('INSERT INTO organization_signers (organization_id, position, full_name, short_name) VALUES (?, ?, ?, ?)')
                ->execute([$organization['id'], $position, $fullName, $shortName]);
            header('Location: organization_form.php?id=' . $organization['id'] . '#signers');
            exit;
        }
    } elseif ($isEdit && $action === 'delete_signer') {
        $db->prepare('DELETE FROM organization_signers WHERE id = ? AND organization_id = ?')
            ->execute([(int) ($_POST['signer_id'] ?? 0), $organization['id']]);
        header('Location: organization_form.php?id=' . $organization['id'] . '#signers');
        exit;
    } elseif ($isEdit && $action === 'upload_letterhead') {
        $file = $_FILES['letterhead'] ?? null;
        $zip = new ZipArchive();

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Не удалось загрузить бланк';
        } elseif ($file['size'] > 10 * 1024 * 1024) {
            $error = 'Бланк больше 10 МБ';
        } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'docx'
            || $zip->open($file['tmp_name']) !== true || $zip->locateName('word/document.xml') === false) {
            $error = 'Бланк должен быть файлом .docx';
        } else {
            $zip->close();
            $dir = __DIR__ . '/uploads/organizations/';

            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                $error = 'Не удалось сохранить бланк на сервере';
            } else {
                $storedName = bin2hex(random_bytes(16)) . '.docx';

                if (!move_uploaded_file($file['tmp_name'], $dir . $storedName)) {
                    $error = 'Не удалось сохранить бланк на сервере';
                } else {
                    $db->prepare('UPDATE organizations SET letterhead_stored_name = ?, letterhead_original_name = ? WHERE id = ?')
                        ->execute([$storedName, $file['name'], $organization['id']]);

                    if (!empty($organization['letterhead_stored_name']) && is_file($dir . $organization['letterhead_stored_name'])) {
                        unlink($dir . $organization['letterhead_stored_name']);
                    }

                    header('Location: organization_form.php?id=' . $organization['id'] . '&saved=1');
                    exit;
                }
            }
        }
    } elseif ($isEdit && $action === 'delete_letterhead') {
        if (!empty($organization['letterhead_stored_name'])) {
            $path = __DIR__ . '/uploads/organizations/' . $organization['letterhead_stored_name'];

            if (is_file($path)) {
                unlink($path);
            }

            $db->prepare('UPDATE organizations SET letterhead_stored_name = NULL, letterhead_original_name = NULL WHERE id = ?')
                ->execute([$organization['id']]);
        }

        header('Location: organization_form.php?id=' . $organization['id'] . '&saved=1');
        exit;
    } else {
        $organization['name'] = trim($_POST['name'] ?? '');

        foreach (array_keys($organizationFields) as $field) {
            $organization[$field] = trim($_POST[$field] ?? '');
        }

        if ($organization['name'] === '') {
            $error = 'Укажите название организации';
        } else {
            $dupStmt = $db->prepare('SELECT id FROM organizations WHERE name = ? AND id != ?');
            $dupStmt->execute([$organization['name'], $organization['id'] ?? 0]);

            if ($dupStmt->fetch()) {
                $error = 'Организация с таким названием уже есть';
            } else {
                $values = [$organization['name']];

                foreach (array_keys($organizationFields) as $field) {
                    $values[] = $organization[$field] !== '' ? $organization[$field] : null;
                }

                $setClause = 'name = ?, ' . implode(' = ?, ', array_keys($organizationFields)) . ' = ?';

                if ($isEdit) {
                    $db->prepare("UPDATE organizations SET {$setClause} WHERE id = ?")->execute(array_merge($values, [$organization['id']]));
                    header('Location: organization_form.php?id=' . $organization['id'] . '&saved=1');
                } else {
                    $columns = 'name, ' . implode(', ', array_keys($organizationFields));
                    $placeholders = implode(', ', array_fill(0, count($values), '?'));
                    $db->prepare("INSERT INTO organizations ({$columns}) VALUES ({$placeholders})")->execute($values);
                    header('Location: organization_form.php?id=' . $db->lastInsertId());
                }

                exit;
            }
        }
    }
}

$departments = $isEdit ? getDepartments($db, (int) $organization['id']) : [];
$signers = [];

if ($isEdit) {
    $signersStmt = $db->prepare('SELECT * FROM organization_signers WHERE organization_id = ? ORDER BY id');
    $signersStmt->execute([$organization['id']]);
    $signers = $signersStmt->fetchAll();
}

$pageTitle = $isEdit ? 'Редактирование организации' : 'Новая организация';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header d-print-none mb-3">
    <h2 class="page-title"><?php echo htmlspecialchars($pageTitle); ?></h2>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <?php if (isset($_GET['saved'])): ?>
            <div class="alert alert-success">Сохранено.</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label required">Название (краткое)</label>
                        <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($organization['name']); ?>" required>
                    </div>
                    <?php foreach (ORGANIZATION_FIELD_GROUPS as $groupTitle => $groupFields): ?>
                        <h4 class="mt-2 mb-3"><?php echo htmlspecialchars($groupTitle); ?></h4>
                        <div class="row">
                            <?php foreach ($groupFields as $field => [$label, $type, $cols]): ?>
                                <?php $fieldValue = htmlspecialchars((string) ($organization[$field] ?? '')); ?>
                                <div class="col-md-<?php echo $cols; ?> mb-3">
                                    <label class="form-label"><?php echo htmlspecialchars($label); ?></label>
                                    <?php if ($type === 'textarea'): ?>
                                        <textarea class="form-control" name="<?php echo $field; ?>" rows="3"><?php echo $fieldValue; ?></textarea>
                                    <?php else: ?>
                                        <input type="<?php echo $type; ?>"<?php echo $type === 'number' ? ' step="0.01" min="0"' : ''; ?> class="form-control" name="<?php echo $field; ?>" value="<?php echo $fieldValue; ?>">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="form-footer">
                        <a href="organizations.php" class="btn btn-link">К списку</a>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($isEdit): ?>
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title">Бланк организации</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($organization['letterhead_stored_name'])): ?>
                    <div class="mb-3">
                        <a href="organization_letterhead.php?id=<?php echo (int) $organization['id']; ?>"><?php echo htmlspecialchars($organization['letterhead_original_name']); ?></a>
                        <form method="post" class="d-inline ms-2" onsubmit="return confirm('Удалить бланк?');">
                            <input type="hidden" name="action" value="delete_letterhead">
                            <button type="submit" class="btn btn-sm btn-danger">Удалить</button>
                        </form>
                    </div>
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
                    <input type="hidden" name="action" value="upload_letterhead">
                    <div class="col-auto"><input type="file" class="form-control" name="letterhead" accept=".docx" required></div>
                    <div class="col-auto"><button type="submit" class="btn btn-secondary"><?php echo !empty($organization['letterhead_stored_name']) ? 'Заменить' : 'Загрузить'; ?></button></div>
                </form>
                <div class="form-hint mt-2">Файл .docx: логотип и реквизиты в верхнем/нижнем колонтитуле переносятся в формируемые документы.</div>
            </div>
        </div>

        <div class="card mt-3" id="signers">
            <div class="card-header">
                <h3 class="card-title">Подписанты</h3>
            </div>
            <form method="post" id="signer-add-form"><input type="hidden" name="action" value="add_signer"></form>
            <div class="table-responsive">
                <table class="table table-vcenter table-sm card-table">
                    <thead><tr><th>Должность</th><th>ФИО</th><th>Кратко</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($signers as $signer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($signer['position']); ?></td>
                                <td><?php echo htmlspecialchars($signer['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($signer['short_name']); ?></td>
                                <td class="text-end">
                                    <form method="post" onsubmit="return confirm('Удалить подписанта?');">
                                        <input type="hidden" name="action" value="delete_signer">
                                        <input type="hidden" name="signer_id" value="<?php echo (int) $signer['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Удалить</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td><input type="text" form="signer-add-form" class="form-control form-control-sm" name="position" placeholder="Генеральный директор" required></td>
                            <td><input type="text" form="signer-add-form" class="form-control form-control-sm" name="full_name" placeholder="Иванов Иван Иванович" required></td>
                            <td><input type="text" form="signer-add-form" class="form-control form-control-sm" name="short_name" placeholder="Иванов И.И." required></td>
                            <td class="text-end"><button type="submit" form="signer-add-form" class="btn btn-sm btn-primary">Добавить</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title">Подразделения</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter table-sm card-table">
                    <tbody>
                        <?php if (empty($departments)): ?>
                            <tr><td>Подразделений пока нет</td></tr>
                        <?php else: foreach ($departments as $department): ?>
                            <tr>
                                <td>
                                    <form method="post" action="department_form.php" class="row g-2 align-items-center">
                                        <input type="hidden" name="id" value="<?php echo (int) $department['id']; ?>">
                                        <input type="hidden" name="organization_id" value="<?php echo (int) $organization['id']; ?>">
                                        <div class="col-auto flex-grow-1">
                                            <input type="text" class="form-control form-control-sm" name="name" value="<?php echo htmlspecialchars($department['name']); ?>">
                                        </div>
                                        <div class="col-auto">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Сохранить</button>
                                        </div>
                                        <div class="col-auto">
                                            <button type="button" class="btn btn-sm btn-danger" data-delete-department="<?php echo (int) $department['id']; ?>">Удалить</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        <tr>
                            <td>
                                <form method="post" action="department_form.php" class="row g-2 align-items-center">
                                    <input type="hidden" name="organization_id" value="<?php echo (int) $organization['id']; ?>">
                                    <div class="col-auto flex-grow-1">
                                        <input type="text" class="form-control form-control-sm" name="name" placeholder="Новое подразделение" required>
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn btn-sm btn-primary">Добавить</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <script>
            $(function () {
                $('[data-delete-department]').on('click', function () {
                    if (!confirm('Удалить подразделение?')) {
                        return;
                    }

                    const id = $(this).data('delete-department');

                    $.ajax({
                        url: 'api/departments.php',
                        method: 'DELETE',
                        data: { id: id },
                    }).done(function () {
                        location.reload();
                    });
                });
            });
        </script>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
