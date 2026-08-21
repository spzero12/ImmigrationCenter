<?php

// Фича "Миграционный учёт": .agent-os/specs/2026-08-20-migration-documents/spec.md

const DOCUMENT_EXPIRY_WARNING_DAYS = 30;

const DOCUMENT_TYPE_LABELS = [
    'passport' => 'Паспорт',
    'visa' => 'Виза',
    'work_patent' => 'Патент на работу',
    'work_permit' => 'Разрешение на работу',
    'registration' => 'Регистрация по месту пребывания',
];

// Фича "Уникальные поля по типу документа" — свои поля под каждый тип,
// сверх общих (номер/серия/даты/кем выдан). Хранятся в
// employee_documents.extra_fields (JSON), ключ => человекочитаемая метка.
const DOCUMENT_TYPE_EXTRA_FIELDS = [
    'passport' => [
        'birth_place' => 'Место рождения',
    ],
    'visa' => [
        'visa_category' => 'Категория визы',
        'visa_multiplicity' => 'Кратность',
    ],
    'work_patent' => [
        'profession' => 'Профессия',
        'region' => 'Регион действия',
    ],
    'work_permit' => [
        'profession' => 'Профессия',
        'employer_name' => 'Работодатель',
    ],
    'registration' => [
        'address' => 'Адрес регистрации',
        'registering_party' => 'Принимающая сторона',
    ],
];

// Возвращает только заполненные пары [key, label, value] для конкретного
// документа — используется и в document_form.php (предзаполнение), и в
// documents.php/employee_form.php (компактный вывод в таблице).
function documentExtraFieldPairs(string $type, ?string $extraFieldsJson): array
{
    $labels = DOCUMENT_TYPE_EXTRA_FIELDS[$type] ?? [];

    if (empty($labels) || !$extraFieldsJson) {
        return [];
    }

    $values = json_decode($extraFieldsJson, true) ?: [];
    $pairs = [];

    foreach ($labels as $key => $label) {
        if (!empty($values[$key])) {
            $pairs[] = ['key' => $key, 'label' => $label, 'value' => $values[$key]];
        }
    }

    return $pairs;
}

function documentStatus(?string $expireDate, ?string $referenceDate = null): array
{
    if (!$expireDate) {
        return ['key' => 'none', 'label' => 'Без срока', 'badgeClass' => 'bg-secondary-lt'];
    }

    $reference = $referenceDate ? new DateTimeImmutable($referenceDate) : new DateTimeImmutable('today');

    $daysLeft = (int) $reference
        ->diff(new DateTimeImmutable($expireDate))
        ->format('%r%a');

    if ($daysLeft < 0) {
        return ['key' => 'expired', 'label' => 'Просрочен', 'badgeClass' => 'bg-danger-lt'];
    }

    if ($daysLeft <= DOCUMENT_EXPIRY_WARNING_DAYS) {
        return ['key' => 'expiring_soon', 'label' => 'Скоро истекает', 'badgeClass' => 'bg-warning-lt'];
    }

    return ['key' => 'valid', 'label' => 'В порядке', 'badgeClass' => 'bg-success-lt'];
}

// Фича "Генерация документов": .agent-os/specs/2026-08-20-document-generation/spec.md

const DOCUMENT_COMMON_FIELDS = [
    'last_name', 'first_name', 'middle_name', 'citizenship', 'birth_date',
    'position', 'department', 'organization',
    'passport_number', 'passport_series', 'passport_issued_by', 'passport_issue_date',
];

// Используется только как подсказка по умолчанию при создании НОВОГО
// документа (пока passport_document_id ещё не выбран) — см. спецификацию
// фичи passport-centric-documents. Для уже сохранённого документа источник
// паспортных полей — getLinkedPassport() по его собственному
// passport_document_id, а не пересчёт "канонического" паспорта.
function getCanonicalPassport(PDO $db, int $employeeId): ?array
{
    $stmt = $db->prepare(
        'SELECT * FROM employee_documents WHERE employee_id = ? AND type = ? ORDER BY issue_date IS NULL, issue_date DESC LIMIT 1'
    );
    $stmt->execute([$employeeId, 'passport']);
    $passport = $stmt->fetch();

    return $passport ?: null;
}

function getLinkedPassport(PDO $db, ?int $passportDocumentId): ?array
{
    if (!$passportDocumentId) {
        return null;
    }

    $stmt = $db->prepare('SELECT * FROM employee_documents WHERE id = ?');
    $stmt->execute([$passportDocumentId]);
    $passport = $stmt->fetch();

    return $passport ?: null;
}

function getEmployeePassports(PDO $db, int $employeeId): array
{
    $stmt = $db->prepare(
        "SELECT * FROM employee_documents WHERE employee_id = ? AND type = 'passport' ORDER BY issue_date IS NULL, issue_date DESC"
    );
    $stmt->execute([$employeeId]);

    return $stmt->fetchAll();
}

// Значения "единого источника" — как они есть в employees/паспорте сейчас,
// без учёта переопределений конкретного документа.
function documentCommonFieldDefaults(array $employee, ?array $passport): array
{
    return [
        'last_name' => $employee['last_name'] ?? '',
        'first_name' => $employee['first_name'] ?? '',
        'middle_name' => $employee['middle_name'] ?? '',
        'citizenship' => $employee['citizenship'] ?? '',
        'birth_date' => $employee['birth_date'] ?? '',
        'position' => $employee['position'] ?? '',
        'department' => $employee['department'] ?? '',
        'organization' => $employee['organization'] ?? '',
        'passport_number' => $passport['number'] ?? '',
        'passport_series' => $passport['series'] ?? '',
        'passport_issued_by' => $passport['issued_by'] ?? '',
        'passport_issue_date' => $passport['issue_date'] ?? '',
    ];
}

// Значения "по умолчанию" с наложенными поверх field_overrides конкретного
// документа — то, что реально нужно показать в форме документа.
function documentEffectiveCommonFields(array $employee, ?array $passport, ?string $overridesJson): array
{
    $fields = documentCommonFieldDefaults($employee, $passport);
    $overrides = $overridesJson ? (json_decode($overridesJson, true) ?: []) : [];

    return array_merge($fields, array_intersect_key($overrides, $fields));
}

// Из отправленных пользователем значений формы оставляет только те, что
// реально отличаются от единого источника — это и есть то, что нужно
// сохранить в field_overrides (пустой массив, если ничего не менялось).
function documentBuildOverrides(array $submitted, array $defaults): array
{
    $overrides = [];

    foreach (DOCUMENT_COMMON_FIELDS as $field) {
        $value = trim($submitted[$field] ?? '');

        if ($value !== ($defaults[$field] ?? '')) {
            $overrides[$field] = $value;
        }
    }

    return $overrides;
}

function formatRuDate(?string $date): string
{
    if (!$date) {
        return '';
    }

    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);

    return $parsed ? $parsed->format('d.m.Y') : $date;
}

// Полный набор плейсхолдеров для document_generate.php: эффективные общие
// данные сотрудника (с учётом field_overrides) + данные самого документа.
function documentTemplatePlaceholders(array $employee, array $document, ?array $passport): array
{
    // Паспорт не привязывается сам к себе (passport_document_id всегда
    // NULL для type=passport) — при генерации самого паспорта его
    // собственные поля и есть источник ${passport_*}.
    if ($document['type'] === 'passport') {
        $passport = $document;
    }

    $common = documentEffectiveCommonFields($employee, $passport, $document['field_overrides'] ?? null);

    return [
        'last_name' => $common['last_name'],
        'first_name' => $common['first_name'],
        'middle_name' => $common['middle_name'],
        'full_name' => trim($common['last_name'] . ' ' . $common['first_name'] . ' ' . $common['middle_name']),
        'citizenship' => $common['citizenship'],
        'birth_date' => formatRuDate($common['birth_date']),
        'position' => $common['position'],
        'department' => $common['department'],
        'organization' => $common['organization'],
        'passport_number' => $common['passport_number'],
        'passport_series' => $common['passport_series'],
        'passport_issued_by' => $common['passport_issued_by'],
        'passport_issue_date' => formatRuDate($common['passport_issue_date']),
        'document_type' => DOCUMENT_TYPE_LABELS[$document['type']] ?? $document['type'],
        'document_number' => $document['number'] ?? '',
        'document_series' => $document['series'] ?? '',
        'issue_date' => formatRuDate($document['issue_date'] ?? null),
        'expire_date' => formatRuDate($document['expire_date'] ?? null),
        'issued_by' => $document['issued_by'] ?? '',
        'generated_date' => formatRuDate(date('Y-m-d')),
    ];
}
