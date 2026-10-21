<?php

// Фича "Единая карточка сотрудника": .agent-os/specs/2026-10-04-employee-card-processes/spec.md
// У сотрудника ровно один документ каждого типа (UNIQUE employee_id+type),
// без версий — данные в карточке всегда актуальны.

const DOCUMENT_EXPIRY_WARNING_DAYS = 30;

const DOCUMENT_TYPE_LABELS = [
    'passport' => 'Паспорт',
    'visa' => 'Виза',
    'work_patent' => 'Патент на работу',
    'work_permit' => 'Разрешение на работу',
    'registration' => 'Миграционный учёт (регистрация)',
    'medical_certificate' => 'Медицинская справка',
    'dms' => 'ДМС',
];

// Порядок блоков документов в карточке сотрудника.
const DOCUMENT_TYPES_ORDER = ['passport', 'visa', 'work_permit', 'work_patent', 'registration', 'medical_certificate', 'dms'];

// Пороги светофора в таблице сотрудников: [красный строго меньше N дней,
// жёлтый до M дней включительно], дальше — зелёный. Просрочено — красный.
const DOCUMENT_INDICATOR_THRESHOLDS = [
    'passport' => [180, 360],
    'visa' => [60, 90],
    'work_permit' => [60, 90],
    'work_patent' => [30, 60],
    'registration' => [30, 60],
    'medical_certificate' => [30, 60],
    'dms' => [14, 30],
];

// Своя пара полей под каждый тип сверх общих (номер/серия/даты/кем выдан).
// Хранятся в employee_documents.extra_fields (JSON), ключ => метка.
const DOCUMENT_TYPE_EXTRA_FIELDS = [
    'passport' => [],
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
    'medical_certificate' => [],
    'dms' => [
        'insurer' => 'Страховая компания',
    ],
];

// Все документы сотрудника, индексированные по типу.
function getEmployeeDocumentsByType(PDO $db, int $employeeId): array
{
    $stmt = $db->prepare('SELECT * FROM employee_documents WHERE employee_id = ?');
    $stmt->execute([$employeeId]);
    $byType = [];

    foreach ($stmt->fetchAll() as $doc) {
        $byType[$doc['type']] = $doc;
    }

    return $byType;
}

// Только заполненные пары [key, label, value] — для компактного вывода.
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

function daysUntil(?string $date, ?string $referenceDate = null): ?int
{
    if (!$date) {
        return null;
    }

    $reference = $referenceDate ? new DateTimeImmutable($referenceDate) : new DateTimeImmutable('today');

    return (int) $reference->diff(new DateTimeImmutable($date))->format('%r%a');
}

function documentStatus(?string $expireDate, ?string $referenceDate = null): array
{
    $daysLeft = daysUntil($expireDate, $referenceDate);

    if ($daysLeft === null) {
        return ['key' => 'none', 'label' => 'Без срока', 'badgeClass' => 'bg-secondary-lt'];
    }

    if ($daysLeft < 0) {
        return ['key' => 'expired', 'label' => 'Просрочен', 'badgeClass' => 'bg-danger-lt'];
    }

    if ($daysLeft <= DOCUMENT_EXPIRY_WARNING_DAYS) {
        return ['key' => 'expiring_soon', 'label' => 'Скоро истекает', 'badgeClass' => 'bg-warning-lt'];
    }

    return ['key' => 'valid', 'label' => 'В порядке', 'badgeClass' => 'bg-success-lt'];
}

// Светофор для таблицы сотрудников: red / yellow / green / none (нет
// документа или срока). Пороги — по типу документа.
function documentIndicator(string $type, ?string $expireDate, ?string $referenceDate = null): array
{
    $daysLeft = daysUntil($expireDate, $referenceDate);

    if ($daysLeft === null) {
        return ['key' => 'none', 'days' => null];
    }

    [$redBelow, $yellowUpTo] = DOCUMENT_INDICATOR_THRESHOLDS[$type] ?? [30, 60];

    if ($daysLeft < $redBelow) {
        return ['key' => 'red', 'days' => $daysLeft];
    }

    if ($daysLeft <= $yellowUpTo) {
        return ['key' => 'yellow', 'days' => $daysLeft];
    }

    return ['key' => 'green', 'days' => $daysLeft];
}

function formatRuDate(?string $date): string
{
    if (!$date) {
        return '';
    }

    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);

    return $parsed ? $parsed->format('d.m.Y') : $date;
}
