<?php

// Единая карточка сотрудника: личные данные, документы (по одному каждого
// типа, без версий) и процессы оформления — на одной странице.
// Спецификация: .agent-os/specs/2026-10-04-employee-card-processes/spec.md

require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/documents.php';
require_once __DIR__ . '/includes/audit.php';
require_once __DIR__ . '/includes/organizations.php';
require_once __DIR__ . '/includes/regions.php';
require_once __DIR__ . '/includes/scans.php';
require_once __DIR__ . '/includes/processes.php';
requireLogin();

const EMPLOYEE_TEXT_FIELDS = [
    'last_name', 'first_name', 'middle_name', 'citizenship',
    'last_name_lat', 'first_name_lat', 'middle_name_lat',
    'birth_country', 'birth_place', 'residence_country',
    'visa_country', 'visa_city', 'foreign_workplace',
    'position', 'phone', 'email', 'notes',
];

const STAY_ADDRESS_FIELDS = [
    'region' => 'Область, край, республика',
    'district' => 'Район',
    'locality' => 'Населённый пункт',
    'street' => 'Улица',
    'house' => 'Дом',
    'corpus' => 'Корпус',
    'building' => 'Строение',
    'flat' => 'Квартира',
    'phone' => 'Телефон по месту пребывания',
];

$employee = ['id' => null, 'organization_id' => null, 'department_id' => null, 'region_id' => null, 'birth_date' => '', 'gender' => ''];

foreach (EMPLOYEE_TEXT_FIELDS as $field) {
    $employee[$field] = '';
}

$stayAddress = array_fill_keys(array_keys(STAY_ADDRESS_FIELDS), '');
$isEdit = isset($_GET['id']);
$db = getDb();

if ($isEdit) {
    $stmt = $db->prepare('SELECT * FROM employees WHERE id = ?');
    $stmt->execute([(int) $_GET['id']]);
    $found = $stmt->fetch();

    if (!$found) {
        http_response_code(404);
        exit('Сотрудник не найден');
    }

    $employee = $found;
    $stayAddress = array_merge($stayAddress, json_decode($found['stay_address'] ?? '', true) ?: []);
}

$documents = $isEdit ? getEmployeeDocumentsByType($db, (int) $employee['id']) : [];

// Текущие значения форм документов (при ошибке валидации — введённые).
$documentValues = [];

foreach (DOCUMENT_TYPES_ORDER as $type) {
    $doc = $documents[$type] ?? [];
    $documentValues[$type] = [
        'number' => $doc['number'] ?? '',
        'series' => $doc['series'] ?? '',
        'issue_date' => $doc['issue_date'] ?? '',
        'expire_date' => $doc['expire_date'] ?? '',
        'issued_by' => $doc['issued_by'] ?? '',
        'extra' => json_decode($doc['extra_fields'] ?? '', true) ?: [],
    ];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (EMPLOYEE_TEXT_FIELDS as $field) {
        $employee[$field] = trim($_POST[$field] ?? '');
    }

    $employee['birth_date'] = $_POST['birth_date'] ?: null;
    $employee['gender'] = in_array($_POST['gender'] ?? '', ['male', 'female'], true) ? $_POST['gender'] : null;
    $employee['organization_id'] = (int) ($_POST['organization_id'] ?? 0) ?: null;
    $employee['department_id'] = (int) ($_POST['department_id'] ?? 0) ?: null;
    $employee['region_id'] = (int) ($_POST['region_id'] ?? 0) ?: null;

    foreach (STAY_ADDRESS_FIELDS as $key => $label) {
        $stayAddress[$key] = trim($_POST['stay_address'][$key] ?? '');
    }

    foreach (DOCUMENT_TYPES_ORDER as $type) {
        $post = $_POST['documents'][$type] ?? [];
        $extra = [];

        foreach (array_keys(DOCUMENT_TYPE_EXTRA_FIELDS[$type]) as $key) {
            $value = trim($post['extra'][$key] ?? '');

            if ($value !== '') {
                $extra[$key] = $value;
            }
        }

        $documentValues[$type] = [
            'number' => trim($post['number'] ?? ''),
            'series' => trim($post['series'] ?? ''),
            'issue_date' => $post['issue_date'] ?? '',
            'expire_date' => $post['expire_date'] ?? '',
            'issued_by' => trim($post['issued_by'] ?? ''),
            'extra' => $extra,
        ];
    }

    // full_name — производное значение из частей (список, поиск и api/*
    // читают его как раньше).
    $fullName = trim($employee['last_name'] . ' ' . $employee['first_name'] . ' ' . $employee['middle_name']);

    // Подразделение должно принадлежать выбранной организации — на случай
    // подмены значений в обход выпадающего списка.
    if ($employee['department_id']) {
        $deptCheckStmt = $db->prepare('SELECT id FROM departments WHERE id = ? AND organization_id = ?');
        $deptCheckStmt->execute([$employee['department_id'], $employee['organization_id']]);

        if (!$deptCheckStmt->fetch()) {
            $employee['department_id'] = null;
        }
    }

    if ($employee['last_name'] === '' || $employee['first_name'] === '') {
        $errors[] = 'Укажите фамилию и имя сотрудника';
    }

    // Сканы валидируем и сохраняем до записи в БД, чтобы ошибка скана не
    // оставляла карточку наполовину сохранённой.
    $newScans = [];

    if (!$errors) {
        foreach (DOCUMENT_TYPES_ORDER as $type) {
            $file = $_FILES['scan_' . $type] ?? null;

            if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                [$scanData, $scanError] = storeUploadedScan($file);

                if ($scanError) {
                    $errors[] = DOCUMENT_TYPE_LABELS[$type] . ': ' . $scanError;
                } else {
                    $newScans[$type] = $scanData;
                }
            }
        }
    }

    if ($errors) {
        foreach ($newScans as $scanData) {
            deleteStoredScan($scanData['scan_stored_name']);
        }
    } else {
        $params = [
            $fullName, $employee['last_name'], $employee['first_name'], $employee['middle_name'] ?: null,
            $employee['last_name_lat'] ?: null, $employee['first_name_lat'] ?: null, $employee['middle_name_lat'] ?: null,
            $employee['gender'], $employee['citizenship'], $employee['birth_date'],
            $employee['birth_country'] ?: null, $employee['birth_place'] ?: null,
            $employee['residence_country'] ?: null, $employee['visa_country'] ?: null, $employee['visa_city'] ?: null,
            $employee['foreign_workplace'] ?: null,
            json_encode(array_filter($stayAddress, static fn($v) => $v !== ''), JSON_UNESCAPED_UNICODE) ?: null,
            $employee['organization_id'], $employee['region_id'], $employee['position'], $employee['department_id'],
            $employee['phone'], $employee['email'], $employee['notes'],
        ];

        $db->beginTransaction();

        try {
            if ($isEdit) {
                $stmt = $db->prepare(
                    'UPDATE employees SET full_name = ?, last_name = ?, first_name = ?, middle_name = ?,
                        last_name_lat = ?, first_name_lat = ?, middle_name_lat = ?, gender = ?, citizenship = ?, birth_date = ?,
                        birth_country = ?, birth_place = ?, residence_country = ?, visa_country = ?, visa_city = ?,
                        foreign_workplace = ?, stay_address = ?, organization_id = ?, region_id = ?, position = ?, department_id = ?,
                        phone = ?, email = ?, notes = ? WHERE id = ?'
                );
                $stmt->execute(array_merge($params, [$employee['id']]));
                $employeeId = (int) $employee['id'];
                $auditAction = 'update';
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO employees (full_name, last_name, first_name, middle_name,
                        last_name_lat, first_name_lat, middle_name_lat, gender, citizenship, birth_date,
                        birth_country, birth_place, residence_country, visa_country, visa_city,
                        foreign_workplace, stay_address, organization_id, region_id, position, department_id,
                        phone, email, notes, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute(array_merge($params, [currentUser()['id']]));
                $employeeId = (int) $db->lastInsertId();
                $auditAction = 'create';
            }

            $replacedScans = [];

            foreach (DOCUMENT_TYPES_ORDER as $type) {
                $v = $documentValues[$type];
                $existing = $documents[$type] ?? null;
                $hasExtra = !empty($v['extra']);
                $isEmpty = $v['number'] === '' && $v['series'] === '' && $v['issue_date'] === ''
                    && $v['expire_date'] === '' && $v['issued_by'] === '' && !$hasExtra;

                if ($isEmpty && !isset($newScans[$type])) {
                    // Пустой блок = документа нет. Скан удаляем вместе с записью
                    // (файл — после коммита).
                    if ($existing) {
                        $db->prepare('DELETE FROM employee_documents WHERE id = ?')->execute([$existing['id']]);
                        $replacedScans[] = $existing['scan_stored_name'];
                    }

                    continue;
                }

                $scan = $newScans[$type] ?? null;

                $db->prepare(
                    'INSERT INTO employee_documents (employee_id, type, number, series, issue_date, expire_date, issued_by, extra_fields, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE number = VALUES(number), series = VALUES(series), issue_date = VALUES(issue_date),
                        expire_date = VALUES(expire_date), issued_by = VALUES(issued_by), extra_fields = VALUES(extra_fields)'
                )->execute([
                    $employeeId, $type, $v['number'] ?: null, $v['series'] ?: null,
                    $v['issue_date'] ?: null, $v['expire_date'] ?: null, $v['issued_by'] ?: null,
                    $hasExtra ? json_encode($v['extra'], JSON_UNESCAPED_UNICODE) : null,
                    currentUser()['id'],
                ]);

                if ($scan) {
                    $db->prepare(
                        'UPDATE employee_documents SET scan_stored_name = ?, scan_original_name = ?, scan_mime_type = ?, scan_size = ?
                         WHERE employee_id = ? AND type = ?'
                    )->execute([$scan['scan_stored_name'], $scan['scan_original_name'], $scan['scan_mime_type'], $scan['scan_size'], $employeeId, $type]);

                    if ($existing) {
                        $replacedScans[] = $existing['scan_stored_name'];
                    }
                }
            }

            logAudit($auditAction, 'employee', $employeeId, $fullName);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();

            foreach ($newScans as $scanData) {
                deleteStoredScan($scanData['scan_stored_name']);
            }

            throw $e;
        }

        foreach ($replacedScans as $stored) {
            deleteStoredScan($stored);
        }

        header('Location: ' . ($isEdit ? 'employee_form.php?id=' . $employeeId . '&saved=1' : 'employee_form.php?id=' . $employeeId . '&created=1'));
        exit;
    }
}

$organizations = getOrganizations($db);
$allDepartments = getDepartments($db);
$regions = getRegions($db);

$pageTitle = $isEdit ? 'Карточка сотрудника' : 'Новый сотрудник';
require __DIR__ . '/includes/header.php';

function e($value): string
{
    return htmlspecialchars((string) $value);
}
?>

<div class="page-header d-print-none mb-3">
    <div class="d-flex align-items-center gap-3">
        <?php if (!empty($employee['photo_stored_name'])): ?>
            <span class="avatar avatar-lg rounded" style="background-image: url(employee_photo.php?employee_id=<?php echo (int) $employee['id']; ?>)"></span>
        <?php else: ?>
            <span class="avatar avatar-lg rounded"><?php echo e(mb_substr($employee['last_name'] ?? '', 0, 1) . mb_substr($employee['first_name'] ?? '', 0, 1)); ?></span>
        <?php endif; ?>
        <h2 class="page-title mb-0"><?php echo e($isEdit && $employee['full_name'] ? $employee['full_name'] : $pageTitle); ?></h2>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <?php if (isset($_GET['saved']) || isset($_GET['created'])): ?>
            <div class="alert alert-success"><?php echo isset($_GET['created']) ? 'Сотрудник создан.' : 'Карточка сохранена.'; ?></div>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endforeach; ?>

        <form method="post" enctype="multipart/form-data">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Личные данные</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Фамилия</label>
                            <input type="text" class="form-control" name="last_name" value="<?php echo e($employee['last_name']); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Имя</label>
                            <input type="text" class="form-control" name="first_name" value="<?php echo e($employee['first_name']); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Отчество</label>
                            <input type="text" class="form-control" name="middle_name" value="<?php echo e($employee['middle_name']); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Фамилия (лат.)</label>
                            <input type="text" class="form-control" name="last_name_lat" value="<?php echo e($employee['last_name_lat']); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Имя (лат.)</label>
                            <input type="text" class="form-control" name="first_name_lat" value="<?php echo e($employee['first_name_lat']); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Отчество (лат.)</label>
                            <input type="text" class="form-control" name="middle_name_lat" value="<?php echo e($employee['middle_name_lat']); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Пол</label>
                            <select class="form-select" name="gender">
                                <option value="">—</option>
                                <option value="male" <?php echo ($employee['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Мужской</option>
                                <option value="female" <?php echo ($employee['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Женский</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Дата рождения</label>
                            <input type="date" class="form-control" name="birth_date" value="<?php echo e($employee['birth_date']); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Гражданство</label>
                            <input type="text" class="form-control" name="citizenship" value="<?php echo e($employee['citizenship']); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Государство рождения</label>
                            <input type="text" class="form-control" name="birth_country" value="<?php echo e($employee['birth_country']); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Место рождения</label>
                            <input type="text" class="form-control" name="birth_place" value="<?php echo e($employee['birth_place']); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Государство постоянного проживания</label>
                            <input type="text" class="form-control" name="residence_country" value="<?php echo e($employee['residence_country']); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Место работы за рубежом</label>
                            <input type="text" class="form-control" name="foreign_workplace" value="<?php echo e($employee['foreign_workplace']); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Страна получения визы</label>
                            <input type="text" class="form-control" name="visa_country" value="<?php echo e($employee['visa_country']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Город получения визы</label>
                            <input type="text" class="form-control" name="visa_city" value="<?php echo e($employee['visa_city']); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Организация</label>
                            <select class="form-select" name="organization_id" id="organization-select">
                                <option value="">— не указана —</option>
                                <?php foreach ($organizations as $organization): ?>
                                    <option value="<?php echo (int) $organization['id']; ?>" <?php echo (int) $employee['organization_id'] === (int) $organization['id'] ? 'selected' : ''; ?>><?php echo e($organization['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-hint"><a href="organizations.php">Организации</a></div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Подразделение</label>
                            <select class="form-select" name="department_id" id="department-select">
                                <option value="">— не указано —</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Регион</label>
                            <select class="form-select" name="region_id">
                                <option value="">— не указан —</option>
                                <?php foreach ($regions as $region): ?>
                                    <option value="<?php echo (int) $region['id']; ?>" <?php echo (int) $employee['region_id'] === (int) $region['id'] ? 'selected' : ''; ?>><?php echo e($region['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-hint"><a href="regions.php">Регионы</a></div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Должность</label>
                            <input type="text" class="form-control" name="position" value="<?php echo e($employee['position']); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Телефон</label>
                            <input type="text" class="form-control" name="phone" value="<?php echo e($employee['phone']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?php echo e($employee['email']); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Примечания</label>
                        <textarea class="form-control" name="notes" rows="2"><?php echo e($employee['notes']); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><h3 class="card-title">Адрес предполагаемого места пребывания</h3></div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach (STAY_ADDRESS_FIELDS as $key => $label): ?>
                            <div class="col-md-4 mb-3">
                                <label class="form-label"><?php echo e($label); ?></label>
                                <input type="text" class="form-control" name="stay_address[<?php echo e($key); ?>]" value="<?php echo e($stayAddress[$key]); ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">Документы</h3>
                    <div class="card-subtitle">Один документ каждого типа. Пустой блок — документа нет.</div>
                </div>
                <div class="card-body">
                    <?php foreach (DOCUMENT_TYPES_ORDER as $type): $v = $documentValues[$type]; $doc = $documents[$type] ?? null; ?>
                        <div class="mb-4 pb-3 border-bottom">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <h4 class="mb-0"><?php echo e(DOCUMENT_TYPE_LABELS[$type]); ?></h4>
                                <?php if ($doc): $status = documentStatus($doc['expire_date']); ?>
                                    <span class="badge <?php echo $status['badgeClass']; ?>"><?php echo e($status['label']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="row">
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Серия</label>
                                    <input type="text" class="form-control" name="documents[<?php echo $type; ?>][series]" value="<?php echo e($v['series']); ?>">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Номер</label>
                                    <input type="text" class="form-control" name="documents[<?php echo $type; ?>][number]" value="<?php echo e($v['number']); ?>">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Дата выдачи</label>
                                    <input type="date" class="form-control" name="documents[<?php echo $type; ?>][issue_date]" value="<?php echo e($v['issue_date']); ?>">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Действителен до</label>
                                    <input type="date" class="form-control" name="documents[<?php echo $type; ?>][expire_date]" value="<?php echo e($v['expire_date']); ?>">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Кем выдан</label>
                                    <input type="text" class="form-control" name="documents[<?php echo $type; ?>][issued_by]" value="<?php echo e($v['issued_by']); ?>">
                                </div>
                            </div>
                            <div class="row">
                                <?php foreach (DOCUMENT_TYPE_EXTRA_FIELDS[$type] as $key => $label): ?>
                                    <div class="col-md-3 mb-2">
                                        <label class="form-label"><?php echo e($label); ?></label>
                                        <input type="text" class="form-control" name="documents[<?php echo $type; ?>][extra][<?php echo e($key); ?>]" value="<?php echo e($v['extra'][$key] ?? ''); ?>">
                                    </div>
                                <?php endforeach; ?>
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Скан (JPG, PNG, PDF до 10 МБ)</label>
                                    <input type="file" class="form-control" name="scan_<?php echo $type; ?>" accept=".jpg,.jpeg,.png,.pdf">
                                    <?php if ($doc && $doc['scan_stored_name']): ?>
                                        <div class="form-hint"><a href="document_scan.php?document_id=<?php echo (int) $doc['id']; ?>" target="_blank"><?php echo e($doc['scan_original_name']); ?></a> — загрузка нового файла заменит этот</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-footer">
                <a href="employees.php" class="btn btn-link">К списку</a>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>

        <script>
            $(function () {
                const allDepartments = <?php echo json_encode(array_map(static function (array $d): array {
                    return ['id' => (int) $d['id'], 'organization_id' => (int) $d['organization_id'], 'name' => $d['name']];
                }, $allDepartments)); ?>;
                const currentDepartmentId = <?php echo (int) ($employee['department_id'] ?? 0); ?>;

                const $organizationSelect = $('#organization-select');
                const $departmentSelect = $('#department-select');

                function renderDepartmentOptions() {
                    const organizationId = parseInt($organizationSelect.val(), 10) || 0;
                    $departmentSelect.empty().append('<option value="">— не указано —</option>');

                    allDepartments
                        .filter(function (d) { return d.organization_id === organizationId; })
                        .forEach(function (d) {
                            const selected = d.id === currentDepartmentId ? 'selected' : '';
                            $departmentSelect.append('<option value="' + d.id + '" ' + selected + '>' + $('<div>').text(d.name).html() + '</option>');
                        });
                }

                $organizationSelect.on('change', renderDepartmentOptions);
                renderDepartmentOptions();
            });
        </script>

        <?php if ($isEdit): ?>
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title">Фото</h3>
            </div>
            <div class="card-body">
                <?php if (isset($_GET['photo_error'])): ?>
                    <div class="alert alert-danger">
                        <?php
                        $photoErrors = [
                            'type' => 'Недопустимый формат файла — только JPG или PNG.',
                            'size' => 'Файл больше 10 МБ.',
                            'save' => 'Не удалось сохранить файл на сервере.',
                        ];
                        echo e($photoErrors[$_GET['photo_error']] ?? 'Не удалось загрузить файл.');
                        ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="employee_photo_upload.php" enctype="multipart/form-data" class="row g-2 align-items-end">
                    <input type="hidden" name="employee_id" value="<?php echo (int) $employee['id']; ?>">
                    <div class="col-auto">
                        <input type="file" class="form-control" name="photo" accept=".jpg,.jpeg,.png" required>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-secondary">
                            <?php echo !empty($employee['photo_stored_name']) ? 'Заменить' : 'Загрузить'; ?>
                        </button>
                    </div>
                </form>
                <div class="form-hint mt-2">JPG или PNG, до 10 МБ.</div>
            </div>
        </div>

        <?php require __DIR__ . '/includes/process_card.php'; ?>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
