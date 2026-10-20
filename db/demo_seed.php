<?php

// Наполняет БД вымышленными данными для демонстрации интерфейса —
// НЕ для реального продакшена с настоящими сотрудниками. Запуск:
// php db/demo_seed.php
//
// Добавляет: 3 демо-пользователей (hr/lawyer/manager), организации с
// реквизитами и подписантами, регион «Москва» и сотрудников с документами
// (по одному каждого типа), сроки которых заданы относительно сегодняшней
// даты — чтобы в таблице сотрудников были видны все цвета светофора.
// Реквизиты организации «Бизнес Альянс» — из образцов заказчика
// (.agent-os/specs/2026-10-04-employee-card-processes/samples/).

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

$adminId = $db->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn() ?: null;

function inDays(int $days): string
{
    return date('Y-m-d', strtotime(($days >= 0 ? '+' : '') . $days . ' days'));
}

function findOrCreateRegion(PDO $db, string $name, string $dative): int
{
    $stmt = $db->prepare('SELECT id FROM regions WHERE name = ?');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();

    if ($id) {
        return (int) $id;
    }

    $db->prepare('INSERT INTO regions (name, name_dative) VALUES (?, ?)')->execute([$name, $dative]);

    return (int) $db->lastInsertId();
}

function findOrCreateOrganization(PDO $db, array $org): int
{
    $stmt = $db->prepare('SELECT id FROM organizations WHERE name = ?');
    $stmt->execute([$org['name']]);
    $id = $stmt->fetchColumn();

    if ($id) {
        return (int) $id;
    }

    $db->prepare(
        'INSERT INTO organizations (name, full_name, inn, ogrn, kpp, egrul_date, legal_address, actual_address, phone, email)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $org['name'], $org['full_name'] ?? null, $org['inn'] ?? null, $org['ogrn'] ?? null, $org['kpp'] ?? null,
        $org['egrul_date'] ?? null, $org['legal_address'] ?? null, $org['actual_address'] ?? null,
        $org['phone'] ?? null, $org['email'] ?? null,
    ]);
    $orgId = (int) $db->lastInsertId();

    foreach ($org['signers'] ?? [] as [$position, $fullName, $shortName]) {
        $db->prepare('INSERT INTO organization_signers (organization_id, position, full_name, short_name) VALUES (?, ?, ?, ?)')
            ->execute([$orgId, $position, $fullName, $shortName]);
    }

    return $orgId;
}

function findOrCreateDepartment(PDO $db, int $organizationId, ?string $name): ?int
{
    if (!$name) {
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

$moscowId = findOrCreateRegion($db, 'Москва', 'г. Москве');

$organizations = [
    'ba' => [
        'name' => 'ООО "БИЗНЕС АЛЬЯНС"',
        'full_name' => 'Общество с ограниченной ответственностью "БИЗНЕС АЛЬЯНС"',
        'inn' => '7716568140', 'ogrn' => '1077746078959', 'kpp' => '771701001', 'egrul_date' => '2007-01-17',
        'legal_address' => '129085, город Москва, Проспект Мира, дом 95, строение 1, помещение 1/1',
        'actual_address' => '141009, Московская область, город Мытищи, улица Колонцова, дом 5, строение 3, офис 210',
        'phone' => '+7(495)795-66-45', 'email' => 'info@b-al.com',
        'signers' => [['Генеральный директор', 'Бочков Вадим Владимирович', 'Бочков В.В.']],
    ],
    'stroy' => [
        'name' => 'АО «СтройГрад»', 'full_name' => 'Акционерное общество «СтройГрад»',
        'inn' => '7701000001', 'ogrn' => '1020000000001', 'kpp' => '770101001', 'egrul_date' => '2010-05-20',
        'legal_address' => '101000, город Москва, улица Строителей, дом 1', 'phone' => '+7(495)000-00-01', 'email' => 'info@stroygrad.example',
        'signers' => [['Генеральный директор', 'Строганов Сергей Сергеевич', 'Строганов С.С.']],
    ],
];

$orgIds = [];

foreach ($organizations as $key => $org) {
    $orgIds[$key] = findOrCreateOrganization($db, $org);
}

echo "\n=== Демо-сотрудники ===\n";

// Сроки документов — в днях от сегодня (отрицательные — просрочено).
$employees = [
    [
        'e' => [
            'last_name' => 'Алам', 'first_name' => 'Наддир', 'last_name_lat' => 'ALAM', 'first_name_lat' => 'NADDIR',
            'gender' => 'male', 'citizenship' => 'Индия', 'birth_date' => '2000-01-01',
            'birth_country' => 'Индия', 'birth_place' => 'Бардиха, Бихар', 'residence_country' => 'Индия',
            'visa_country' => 'Индия', 'visa_city' => 'Дели',
            'org' => 'ba', 'department' => 'Производство', 'position' => 'Рабочий',
            'stay_address' => ['region' => 'Московская область', 'locality' => 'город Раменское', 'street' => 'Советская', 'house' => '19', 'phone' => '+7(495)795-66-45'],
        ],
        'docs' => [
            'passport' => ['U', '3148572', -2000, 1800, 'ПАТНА'],
            'visa' => [null, 'V-1001', -10, 100, null],
            'work_permit' => [null, 'РНР-1', -50, 75, null],
            'registration' => [null, 'МУ-1', -20, 45, null],
            'medical_certificate' => [null, 'МС-1', -100, 20, null],
            'dms' => [null, 'ДМС-1', -100, 40, null],
        ],
    ],
    [
        'e' => [
            'last_name' => 'Ансари', 'first_name' => 'Саджид', 'last_name_lat' => 'ANSARI', 'first_name_lat' => 'SAJID',
            'gender' => 'male', 'citizenship' => 'Индия', 'birth_date' => '1995-03-12',
            'birth_country' => 'Индия', 'birth_place' => 'Мумбаи', 'residence_country' => 'Индия',
            'visa_country' => 'Индия', 'visa_city' => 'Дели',
            'org' => 'ba', 'department' => 'Производство', 'position' => 'Рабочий',
            'stay_address' => ['region' => 'Московская область', 'locality' => 'город Мытищи', 'street' => 'Колонцова', 'house' => '5'],
        ],
        'docs' => [
            'passport' => ['AJ', '669184', -1000, 200, 'МУМБАИ'],
            'visa' => [null, 'V-1002', -10, 40, null],
            'work_permit' => [null, 'РНР-2', -50, 120, null],
            'registration' => [null, 'МУ-2', -20, 10, null],
            'dms' => [null, 'ДМС-2', -100, 400, null],
        ],
    ],
    [
        'e' => [
            'last_name' => 'Сидорова', 'first_name' => 'Анна', 'middle_name' => 'Сергеевна',
            'gender' => 'female', 'citizenship' => 'Киргизия', 'birth_date' => '1995-06-23',
            'org' => 'stroy', 'department' => 'Строительство', 'position' => 'Разнорабочая',
        ],
        'docs' => [
            'passport' => ['CC', '3344556', -1500, 365, 'МВД Киргизии'],
            'registration' => [null, 'МУ-3', -20, -5, null],
        ],
    ],
    [
        'e' => [
            'last_name' => 'Назаров', 'first_name' => 'Бахтиёр', 'middle_name' => 'Олимович',
            'gender' => 'male', 'citizenship' => 'Киргизия', 'birth_date' => '1991-04-04',
            'org' => null, 'position' => 'Грузчик',
        ],
        'docs' => [
            'passport' => ['FF', '6677889', -300, 3000, 'МВД Киргизии'],
        ],
    ],
];

foreach ($employees as $entry) {
    $e = $entry['e'];
    $existsStmt = $db->prepare('SELECT id FROM employees WHERE last_name = ? AND first_name = ?');
    $existsStmt->execute([$e['last_name'], $e['first_name']]);

    if ($existsStmt->fetch()) {
        echo "  {$e['last_name']} {$e['first_name']} уже существует, пропуск\n";
        continue;
    }

    $orgId = $e['org'] ? $orgIds[$e['org']] : null;
    $deptId = $orgId ? findOrCreateDepartment($db, $orgId, $e['department'] ?? null) : null;
    $fullName = trim($e['last_name'] . ' ' . $e['first_name'] . ' ' . ($e['middle_name'] ?? ''));

    $db->prepare(
        'INSERT INTO employees (full_name, last_name, first_name, middle_name, last_name_lat, first_name_lat, gender, citizenship, birth_date,
            birth_country, birth_place, residence_country, visa_country, visa_city, stay_address, organization_id, region_id, position, department_id, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $fullName, $e['last_name'], $e['first_name'], $e['middle_name'] ?? null, $e['last_name_lat'] ?? null, $e['first_name_lat'] ?? null,
        $e['gender'] ?? null, $e['citizenship'], $e['birth_date'], $e['birth_country'] ?? null, $e['birth_place'] ?? null,
        $e['residence_country'] ?? null, $e['visa_country'] ?? null, $e['visa_city'] ?? null,
        isset($e['stay_address']) ? json_encode($e['stay_address'], JSON_UNESCAPED_UNICODE) : null,
        $orgId, $orgId ? $moscowId : null, $e['position'], $deptId, $adminId,
    ]);
    $employeeId = (int) $db->lastInsertId();

    foreach ($entry['docs'] as $type => [$series, $number, $issueOffset, $expireOffset, $issuedBy]) {
        $db->prepare(
            'INSERT INTO employee_documents (employee_id, type, series, number, issue_date, expire_date, issued_by, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$employeeId, $type, $series, $number, inDays($issueOffset), inDays($expireOffset), $issuedBy, $adminId]);
    }

    echo "  {$fullName} — добавлен (id={$employeeId}), документов: " . count($entry['docs']) . "\n";
}


echo "\n=== Демо-объекты ===\n";

$demoSites = [
    ['ЖК «Северный парк»', 'Строительство жилого комплекса, корпуса 1–3', 'г. Москва, ул. Лесная, д. 10'],
    ['Склад «Подольск»', 'Логистический комплекс, погрузочно-разгрузочные работы', 'Московская область, г. Подольск, Промышленная ул., д. 5'],
];
$siteIds = [];

foreach ($demoSites as [$siteName, $siteDescription, $siteAddress]) {
    $existing = $db->prepare('SELECT id FROM work_sites WHERE name = ?');
    $existing->execute([$siteName]);
    $siteId = $existing->fetchColumn();

    if (!$siteId) {
        $db->prepare('INSERT INTO work_sites (name, description, address) VALUES (?, ?, ?)')->execute([$siteName, $siteDescription, $siteAddress]);
        $siteId = $db->lastInsertId();
    }

    $siteIds[] = (int) $siteId;
    echo "  {$siteName}\n";
}

// Сотрудникам без объекта — по кругу демо-объекты.
$withoutSite = $db->query('SELECT id FROM employees WHERE work_site_id IS NULL ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);

foreach ($withoutSite as $index => $employeeId) {
    $db->prepare('UPDATE employees SET work_site_id = ? WHERE id = ?')->execute([$siteIds[$index % count($siteIds)], $employeeId]);
}
echo "\nГотово.\n";
