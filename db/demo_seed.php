<?php

// Наполняет БД вымышленными данными для демонстрации интерфейса —
// НЕ для реального продакшена с настоящими сотрудниками. Запуск:
// php db/demo_seed.php
//
// Добавляет: 3 демо-пользователей (hr/lawyer/manager) и 6 сотрудников
// с паспортами и разрешительными документами разных статусов (просрочен /
// скоро истекает / в порядке / без срока), в разных организациях/
// подразделениях (заводятся автоматически через findOrCreate*) — чтобы
// сразу было видно группировку, фильтры и отчёты в работе. У каждого
// документа заполнены и уникальные для его типа поля (extra_fields) —
// см. DOCUMENT_TYPE_EXTRA_FIELDS в includes/documents.php.

require __DIR__ . '/../includes/db.php';

$db = getDb();

echo "=== Демо-пользователи ===\n";

$demoUsers = [
    ['hr_demo', 'HR Демонстрационный', 'hr'],
    ['lawyer_demo', 'Юрист Демонстрационный', 'lawyer'],
    ['manager_demo', 'Руководитель Демонстрационный', 'manager'],
];

foreach ($demoUsers as [$username, $fullName, $role]) {
    $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);

    if ($stmt->fetch()) {
        echo "  {$username} уже существует, пропуск\n";
        continue;
    }

    $stmt = $db->prepare('INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)');
    $stmt->execute([$username, password_hash('Demo12345', PASSWORD_DEFAULT), $fullName, $role]);
    echo "  {$username} / Demo12345 (роль: {$role})\n";
}

$adminStmt = $db->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1");
$adminId = $adminStmt->fetchColumn() ?: null;

function findOrCreateOrganization(PDO $db, ?string $name): ?int
{
    if (!$name) {
        return null;
    }

    $stmt = $db->prepare('SELECT id FROM organizations WHERE name = ?');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();

    if ($id) {
        return (int) $id;
    }

    $db->prepare('INSERT INTO organizations (name) VALUES (?)')->execute([$name]);

    return (int) $db->lastInsertId();
}

function findOrCreateDepartment(PDO $db, ?int $organizationId, ?string $name): ?int
{
    if (!$organizationId || !$name) {
        return null;
    }

    $stmt = $db->prepare('SELECT id FROM departments WHERE organization_id = ? AND name = ?');
    $stmt->execute([$organizationId, $name]);
    $id = $stmt->fetchColumn();

    if ($id) {
        return (int) $id;
    }

    $db->prepare('INSERT INTO departments (organization_id, name) VALUES (?, ?)')->execute([$organizationId, $name]);

    return (int) $db->lastInsertId();
}

function insertEmployee(PDO $db, array $data, ?int $createdBy): int
{
    $organizationId = findOrCreateOrganization($db, $data['organization'] ?? null);
    $departmentId = findOrCreateDepartment($db, $organizationId, $data['department'] ?? null);

    $stmt = $db->prepare(
        'INSERT INTO employees (full_name, last_name, first_name, middle_name, citizenship, birth_date, organization_id, position, department_id, phone, email, notes, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $fullName = trim($data['last_name'] . ' ' . $data['first_name'] . ' ' . ($data['middle_name'] ?? ''));
    $stmt->execute([
        $fullName, $data['last_name'], $data['first_name'], $data['middle_name'] ?? null,
        $data['citizenship'], $data['birth_date'], $organizationId,
        $data['position'], $departmentId, $data['phone'] ?? null, $data['email'] ?? null,
        $data['notes'] ?? null, $createdBy,
    ]);

    return (int) $db->lastInsertId();
}

function insertDocument(PDO $db, int $employeeId, ?int $passportDocumentId, array $data, ?int $createdBy): int
{
    $extraFieldsJson = !empty($data['extra']) ? json_encode($data['extra'], JSON_UNESCAPED_UNICODE) : null;

    $stmt = $db->prepare(
        'INSERT INTO employee_documents (employee_id, passport_document_id, type, number, series, issue_date, expire_date, issued_by, extra_fields, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $employeeId, $passportDocumentId, $data['type'], $data['number'], $data['series'] ?? null,
        $data['issue_date'] ?? null, $data['expire_date'] ?? null, $data['issued_by'] ?? null,
        $extraFieldsJson, $createdBy,
    ]);

    return (int) $db->lastInsertId();
}

echo "\n=== Демо-сотрудники ===\n";

$employees = [
    [
        'employee' => [
            'last_name' => 'Иванов', 'first_name' => 'Иван', 'middle_name' => 'Иванович',
            'citizenship' => 'Узбекистан', 'birth_date' => '1990-03-12',
            'organization' => 'ООО «Ромашка»', 'department' => 'Логистика', 'position' => 'Кладовщик',
            'phone' => '+7 900 111-22-33',
        ],
        'passport' => ['number' => 'AA1234567', 'series' => 'AA', 'issue_date' => '2021-05-10', 'expire_date' => '2031-05-10', 'issued_by' => 'МВД Узбекистана', 'extra' => ['birth_place' => 'г. Ташкент']],
        'documents' => [
            ['type' => 'work_patent', 'number' => 'ПТ-778899', 'issue_date' => '2025-08-20', 'expire_date' => '2026-09-10', 'issued_by' => 'ГУ МВД по г. Москве', 'extra' => ['profession' => 'Кладовщик', 'region' => 'г. Москва']],
            ['type' => 'registration', 'number' => 'РЕГ-001122', 'issue_date' => '2026-01-15', 'expire_date' => '2026-07-15', 'issued_by' => 'ОВМ УМВД', 'extra' => ['address' => 'г. Москва, ул. Складская, д. 5', 'registering_party' => 'ООО «Ромашка»']],
        ],
    ],
    [
        'employee' => [
            'last_name' => 'Петров', 'first_name' => 'Пётр', 'middle_name' => 'Петрович',
            'citizenship' => 'Таджикистан', 'birth_date' => '1988-11-02',
            'organization' => 'ООО «Ромашка»', 'department' => 'Логистика', 'position' => 'Водитель',
            'phone' => '+7 900 222-33-44',
        ],
        'passport' => ['number' => 'BB2233445', 'series' => 'BB', 'issue_date' => '2019-11-20', 'expire_date' => '2029-11-20', 'issued_by' => 'МВД Таджикистана', 'extra' => ['birth_place' => 'г. Душанбе']],
        'documents' => [
            ['type' => 'work_patent', 'number' => 'ПТ-556677', 'issue_date' => '2025-12-01', 'expire_date' => '2026-12-01', 'issued_by' => 'ГУ МВД по г. Москве', 'extra' => ['profession' => 'Водитель', 'region' => 'г. Москва']],
        ],
    ],
    [
        'employee' => [
            'last_name' => 'Сидорова', 'first_name' => 'Анна', 'middle_name' => 'Сергеевна',
            'citizenship' => 'Киргизия', 'birth_date' => '1995-06-23',
            'organization' => 'АО «СтройГрад»', 'department' => 'Строительство', 'position' => 'Разнорабочая',
            'phone' => '+7 900 333-44-55',
        ],
        'passport' => ['number' => 'CC3344556', 'series' => 'CC', 'issue_date' => '2022-02-14', 'expire_date' => '2032-02-14', 'issued_by' => 'МВД Киргизии', 'extra' => ['birth_place' => 'г. Бишкек']],
        'documents' => [
            ['type' => 'registration', 'number' => 'РЕГ-334455', 'issue_date' => '2026-02-01', 'expire_date' => '2026-08-30', 'issued_by' => 'ОВМ УМВД', 'extra' => ['address' => 'г. Москва, ул. Строителей, д. 12', 'registering_party' => 'АО «СтройГрад»']],
        ],
    ],
    [
        'employee' => [
            'last_name' => 'Каримов', 'first_name' => 'Фарход', 'middle_name' => 'Рустамович',
            'citizenship' => 'Узбекистан', 'birth_date' => '1982-01-19',
            'organization' => 'АО «СтройГрад»', 'department' => 'Строительство', 'position' => 'Бригадир',
            'phone' => '+7 900 444-55-66',
        ],
        'passport' => ['number' => 'DD4455667', 'series' => 'DD', 'issue_date' => '2018-07-01', 'expire_date' => '2026-07-01', 'issued_by' => 'МВД Узбекистана', 'extra' => ['birth_place' => 'г. Самарканд']],
        'documents' => [
            ['type' => 'work_permit', 'number' => 'РАЗР-998877', 'issue_date' => '2023-01-10', 'expire_date' => '2026-01-10', 'issued_by' => 'ГУ МВД по г. Москве', 'extra' => ['profession' => 'Бригадир', 'employer_name' => 'АО «СтройГрад»']],
        ],
    ],
    [
        'employee' => [
            'last_name' => 'Юсупова', 'first_name' => 'Дилноза', 'middle_name' => 'Акрамовна',
            'citizenship' => 'Таджикистан', 'birth_date' => '1993-09-30',
            'organization' => 'ИП Смирнов А.В.', 'department' => 'Клининг', 'position' => 'Уборщица',
            'phone' => '+7 900 555-66-77',
        ],
        'passport' => ['number' => 'EE5566778', 'series' => 'EE', 'issue_date' => '2023-09-05', 'expire_date' => '2033-09-05', 'issued_by' => 'МВД Таджикистана', 'extra' => ['birth_place' => 'г. Худжанд']],
        'documents' => [
            ['type' => 'work_patent', 'number' => 'ПТ-112233', 'issue_date' => '2026-08-01', 'expire_date' => '2026-09-01', 'issued_by' => 'ГУ МВД по г. Москве', 'extra' => ['profession' => 'Уборщица', 'region' => 'г. Москва']],
            ['type' => 'registration', 'number' => 'РЕГ-667788', 'issue_date' => '2026-06-01', 'expire_date' => '2027-06-01', 'issued_by' => 'ОВМ УМВД', 'extra' => ['address' => 'г. Москва, ул. Клининговая, д. 3', 'registering_party' => 'ИП Смирнов А.В.']],
        ],
    ],
    [
        'employee' => [
            'last_name' => 'Назаров', 'first_name' => 'Бахтиёр', 'middle_name' => 'Олимович',
            'citizenship' => 'Киргизия', 'birth_date' => '1991-04-04',
            'organization' => null, 'department' => 'Склад', 'position' => 'Грузчик',
            'phone' => '+7 900 666-77-88',
            'notes' => 'Организация ещё не назначена — демонстрация группы «Без организации».',
        ],
        'passport' => ['number' => 'FF6677889', 'series' => 'FF', 'issue_date' => '2024-04-04', 'expire_date' => '2034-04-04', 'issued_by' => 'МВД Киргизии', 'extra' => ['birth_place' => 'г. Ош']],
        'documents' => [
            ['type' => 'visa', 'number' => 'ВИЗА-001', 'issue_date' => '2026-01-01', 'expire_date' => null, 'issued_by' => 'Посольство РФ', 'extra' => ['visa_category' => 'Рабочая', 'visa_multiplicity' => 'Многократная']],
        ],
    ],
];

foreach ($employees as $entry) {
    $existsStmt = $db->prepare('SELECT id FROM employees WHERE last_name = ? AND first_name = ?');
    $existsStmt->execute([$entry['employee']['last_name'], $entry['employee']['first_name']]);

    if ($existsStmt->fetch()) {
        echo "  {$entry['employee']['last_name']} {$entry['employee']['first_name']} уже существует, пропуск\n";
        continue;
    }

    $employeeId = insertEmployee($db, $entry['employee'], $adminId);

    $passportData = $entry['passport'] + ['type' => 'passport'];
    $passportId = insertDocument($db, $employeeId, null, $passportData, $adminId);

    foreach ($entry['documents'] as $doc) {
        insertDocument($db, $employeeId, $passportId, $doc, $adminId);
    }

    echo "  {$entry['employee']['last_name']} {$entry['employee']['first_name']} — добавлен (id={$employeeId}), паспорт + " . count($entry['documents']) . " документ(ов)\n";
}

echo "\nГотово.\n";
