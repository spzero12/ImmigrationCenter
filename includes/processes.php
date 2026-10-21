<?php

// Процессы оформления документов. Пока один процесс — "Приглашение".
// Опись — групповой документ (несколько сотрудников из таблицы), в состав
// процесса не входит.
//
// Каждый документ процесса открывается на своей странице
// (process_document.php): поля предзаполняются из карточки сотрудника и
// организации, поля, отмеченные в образце красным (т.е. переменные
// данные), редактируются, затем "Сформировать" выдаёт .docx. Правки
// хранятся в process_documents.fields как переопределения поверх данных
// карточки.
// Спецификация: .agent-os/specs/2026-10-04-employee-card-processes/spec.md

require_once __DIR__ . '/documents.php';
require_once __DIR__ . '/docx_template.php';

const PROCESS_TYPES = [
    'invitation' => [
        'label' => 'Приглашение',
        'documents' => [
            'petition' => ['label' => 'Ходатайство', 'available' => true],
            'guarantee_letter' => ['label' => 'Гарантийное письмо', 'available' => true],
            'power_of_attorney' => ['label' => 'Доверенность', 'available' => false],
        ],
    ],
];

// Параметры поездки в образце ходатайства набраны чёрным (не красным) —
// фиксированные значения процесса, в форме не редактируются.
const INVITATION_TRIP = [
    'purpose' => 'Трудовая деятельность',
    'stay_days' => '0090',
    'multiplicity' => 'single',
    'visa_kind' => 'work',
];

const VISA_MULTIPLICITY_LABELS = [
    'single' => 'однократная',
    'double' => 'двукратная',
    'multiple' => 'многократная',
];

const VISA_KIND_LABELS = [
    'private' => 'частная',
    'business' => 'деловая',
    'study' => 'учебная',
    'work' => 'рабочая',
    'humanitarian' => 'гуманитарная',
];

// Поля форм документов: key => [label, type (text|date|select), group,
// readonly]. Порядок = порядок в форме.
function documentFieldDefinitions(string $document): array
{
    $org = 'Приглашающая организация';
    $signer = 'Подписант';

    if ($document === 'guarantee_letter') {
        return [
            'document_date' => ['Дата письма', 'date', 'Документ'],
            'region_dative' => ['Адресат: УВМ ГУ МВД России по … (из региона сотрудника)', 'text', 'Документ', true],
            'org_short_name' => ['Наименование в тексте (например, ООО "…")', 'text', $org],
            'org_full_name' => ['Полное наименование', 'text', $org],
            'org_legal_address' => ['Юридический адрес', 'text', $org],
            'org_inn' => ['ИНН', 'text', $org],
            'org_ogrn' => ['ОГРН', 'text', $org],
            'org_kpp' => ['КПП', 'text', $org],
            'org_phone' => ['Телефон', 'text', $org],
            'citizenship' => ['Гражданство', 'text', 'Приглашаемый'],
            'person_name' => ['ФИО', 'text', 'Приглашаемый'],
            'birth_date' => ['Дата рождения', 'date', 'Приглашаемый'],
            'passport_number' => ['Паспорт (серия и номер)', 'text', 'Приглашаемый'],
            'passport_issue_date' => ['Паспорт выдан', 'date', 'Приглашаемый'],
            'passport_expire_date' => ['Паспорт действителен до', 'date', 'Приглашаемый'],
            'stay_address' => ['Адрес проживания', 'text', 'Приглашаемый'],
            'signer_position' => ['Должность', 'text', $signer],
            'signer_name' => ['ФИО подписанта', 'text', $signer],
        ];
    }

    if ($document === 'petition') {
        return [
            'document_date' => ['Дата', 'date', 'Документ'],
            'region_dative' => ['УВМ ГУ МВД России по … (из региона сотрудника)', 'text', 'Документ', true],
            'org_full_name' => ['1.1. Полное наименование', 'text', $org],
            'org_inn' => ['ИНН', 'text', $org],
            'org_ogrn' => ['ОГРН', 'text', $org],
            'org_egrul_date' => ['Дата внесения записи в ЕГРЮЛ', 'date', $org],
            'org_legal_address' => ['1.2. Почтовый адрес', 'text', $org],
            'org_actual_address' => ['Фактический адрес', 'text', $org],
            'org_phone' => ['Телефон', 'text', $org],
            'org_email' => ['Адрес электронной почты', 'text', $org],
            'visit_places' => ['2.7. Пункты посещения в РФ', 'text', 'Сведения о поездке'],
            'last_name' => ['3.1. Фамилия рус.', 'text', 'Приглашаемый'],
            'last_name_lat' => ['3.2. Фамилия лат.', 'text', 'Приглашаемый'],
            'first_name' => ['3.3. Имя рус.', 'text', 'Приглашаемый'],
            'first_name_lat' => ['3.4. Имя лат.', 'text', 'Приглашаемый'],
            'middle_name' => ['3.5. Отчество рус.', 'text', 'Приглашаемый'],
            'middle_name_lat' => ['3.6. Отчество лат.', 'text', 'Приглашаемый'],
            'gender' => ['3.7. Пол', 'select', 'Приглашаемый'],
            'birth_date' => ['3.8. Дата рождения', 'date', 'Приглашаемый'],
            'birth_country' => ['3.9. Государство рождения', 'text', 'Приглашаемый'],
            'birth_place' => ['3.10. Место рождения', 'text', 'Приглашаемый'],
            'citizenship' => ['3.11. Гражданство', 'text', 'Приглашаемый'],
            'residence_country' => ['3.12. Государство постоянного проживания', 'text', 'Приглашаемый'],
            'residence_region' => ['3.13. Регион', 'text', 'Приглашаемый'],
            'visa_country' => ['3.14. Место получения визы: страна', 'text', 'Приглашаемый'],
            'visa_city' => ['Место получения визы: город', 'text', 'Приглашаемый'],
            'foreign_workplace' => ['3.15. Место работы за рубежом', 'text', 'Приглашаемый'],
            'passport_series' => ['3.16. Серия', 'text', 'Документ, удостоверяющий личность'],
            'passport_number' => ['Номер', 'text', 'Документ, удостоверяющий личность'],
            'passport_issued_by' => ['Кем выдан', 'text', 'Документ, удостоверяющий личность'],
            'passport_issue_date' => ['Дата выдачи', 'date', 'Документ, удостоверяющий личность'],
            'passport_expire_date' => ['Действителен до', 'date', 'Документ, удостоверяющий личность'],
            'stay_region' => ['3.17. Область, край, республика', 'text', 'Адрес места пребывания'],
            'stay_district' => ['Район', 'text', 'Адрес места пребывания'],
            'stay_locality' => ['Населённый пункт', 'text', 'Адрес места пребывания'],
            'stay_street' => ['Улица', 'text', 'Адрес места пребывания'],
            'stay_house' => ['Дом', 'text', 'Адрес места пребывания'],
            'stay_corpus' => ['Корпус', 'text', 'Адрес места пребывания'],
            'stay_building' => ['Строение', 'text', 'Адрес места пребывания'],
            'stay_flat' => ['Квартира', 'text', 'Адрес места пребывания'],
            'stay_phone' => ['Телефон', 'text', 'Адрес места пребывания'],
            'signer_position' => ['Должность', 'text', $signer],
            'signer_short' => ['Фамилия, инициалы уполномоченного лица', 'text', $signer],
        ];
    }

    return [];
}

// Всё, что нужно для формирования документов сотрудника: карточка,
// организация (с реквизитами), регион, документы, адрес пребывания,
// подписанты организации.
function loadEmployeeContext(PDO $db, int $employeeId): ?array
{
    $stmt = $db->prepare('SELECT * FROM employees WHERE id = ?');
    $stmt->execute([$employeeId]);
    $employee = $stmt->fetch();

    if (!$employee) {
        return null;
    }

    $organization = null;
    $signers = [];

    if ($employee['organization_id']) {
        $orgStmt = $db->prepare('SELECT * FROM organizations WHERE id = ?');
        $orgStmt->execute([$employee['organization_id']]);
        $organization = $orgStmt->fetch() ?: null;

        $signersStmt = $db->prepare('SELECT * FROM organization_signers WHERE organization_id = ? ORDER BY id');
        $signersStmt->execute([$employee['organization_id']]);
        $signers = $signersStmt->fetchAll();
    }

    $region = null;

    if ($employee['region_id']) {
        $regionStmt = $db->prepare('SELECT * FROM regions WHERE id = ?');
        $regionStmt->execute([$employee['region_id']]);
        $region = $regionStmt->fetch() ?: null;
    }

    return [
        'employee' => $employee,
        'organization' => $organization,
        'region' => $region,
        'signers' => $signers,
        'documents' => getEmployeeDocumentsByType($db, $employeeId),
        'stay_address' => json_decode($employee['stay_address'] ?? '', true) ?: [],
    ];
}

function letterheadPath(?array $organization): ?string
{
    if ($organization && !empty($organization['letterhead_stored_name'])) {
        return __DIR__ . '/../uploads/organizations/' . $organization['letterhead_stored_name'];
    }

    return null;
}

function slashDate(?string $date): string
{
    if (!$date) {
        return '';
    }

    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);

    return $parsed ? $parsed->format('d/m/Y') : $date;
}

function passportNumber(array $context): string
{
    $passport = $context['documents']['passport'] ?? null;

    return $passport ? trim(($passport['series'] ?? '') . ($passport['number'] ?? '')) : '';
}

function organizationDisplayName(?array $organization): string
{
    if (!$organization) {
        return '';
    }

    return $organization['full_name'] ?: $organization['name'];
}

function employeeFullNameRu(array $employee): string
{
    return trim($employee['last_name'] . ' ' . $employee['first_name'] . ' ' . ($employee['middle_name'] ?? ''));
}

// Адрес пребывания одной строкой: "Московская область, город Раменское, ..."
// $a — значения stay_* ИЛИ ключи без префикса (region, district, ...).
function stayAddressLine(array $a): string
{
    $map = [
        'region' => '', 'district' => '', 'locality' => '', 'street' => '',
        'house' => 'дом ', 'corpus' => 'корпус ', 'building' => 'строение ', 'flat' => 'квартира ',
    ];
    $parts = [];

    foreach ($map as $key => $prefix) {
        $value = $a[$key] ?? ($a['stay_' . $key] ?? '');

        if ($value !== '') {
            $parts[] = $prefix . $value;
        }
    }

    return implode(', ', $parts);
}

// Подписант по умолчанию — первый у организации.
function defaultSigner(array $context): ?array
{
    return $context['signers'][0] ?? null;
}

// Значения полей "по умолчанию" — прямо из карточки сотрудника и организации.
function documentDefaults(string $document, array $context, ?array $signer = null): array
{
    $e = $context['employee'];
    $o = $context['organization'] ?? [];
    $p = $context['documents']['passport'] ?? [];
    $a = $context['stay_address'];
    $signer = $signer ?? defaultSigner($context);
    $orgFullName = organizationDisplayName($context['organization']);

    $all = [
        'document_date' => date('Y-m-d'),
        'region_dative' => $context['region']['name_dative'] ?? '',
        'org_short_name' => $o['name'] ?? '',
        'org_full_name' => $orgFullName,
        'org_legal_address' => $o['legal_address'] ?? '',
        'org_actual_address' => $o['actual_address'] ?? '',
        'org_inn' => $o['inn'] ?? '',
        'org_ogrn' => $o['ogrn'] ?? '',
        'org_kpp' => $o['kpp'] ?? '',
        'org_egrul_date' => $o['egrul_date'] ?? '',
        'org_phone' => $o['phone'] ?? '',
        'org_email' => $o['email'] ?? '',
        'visit_places' => '',
        'last_name' => $e['last_name'],
        'last_name_lat' => $e['last_name_lat'] ?? '',
        'first_name' => $e['first_name'],
        'first_name_lat' => $e['first_name_lat'] ?? '',
        'middle_name' => $e['middle_name'] ?? '',
        'middle_name_lat' => $e['middle_name_lat'] ?? '',
        'person_name' => trim($e['last_name'] . ' ' . $e['first_name'] . ' ' . ($e['middle_name'] ?? '')),
        'gender' => $e['gender'] ?? '',
        'birth_date' => $e['birth_date'] ?? '',
        'birth_country' => $e['birth_country'] ?? '',
        'birth_place' => $e['birth_place'] ?? '',
        'citizenship' => $e['citizenship'] ?? '',
        'residence_country' => $e['residence_country'] ?? '',
        'residence_region' => $e['birth_place'] ?? '',
        'visa_country' => $e['visa_country'] ?? '',
        'visa_city' => $e['visa_city'] ?? '',
        'foreign_workplace' => $e['foreign_workplace'] ?? '',
        'passport_series' => $p['series'] ?? '',
        'passport_number' => $document === 'guarantee_letter' ? trim(($p['series'] ?? '') . ($p['number'] ?? '')) : ($p['number'] ?? ''),
        'passport_issued_by' => $p['issued_by'] ?? '',
        'passport_issue_date' => $p['issue_date'] ?? '',
        'passport_expire_date' => $p['expire_date'] ?? '',
        'stay_address' => stayAddressLine($a),
        'stay_region' => $a['region'] ?? '',
        'stay_district' => $a['district'] ?? '',
        'stay_locality' => $a['locality'] ?? '',
        'stay_street' => $a['street'] ?? '',
        'stay_house' => $a['house'] ?? '',
        'stay_corpus' => $a['corpus'] ?? '',
        'stay_building' => $a['building'] ?? '',
        'stay_flat' => $a['flat'] ?? '',
        'stay_phone' => $a['phone'] ?? '',
        'signer_position' => $signer['position'] ?? '',
        'signer_name' => $signer['full_name'] ?? '',
        'signer_short' => $signer['short_name'] ?? '',
    ];

    return array_intersect_key($all, documentFieldDefinitions($document));
}

// Сохранённые правки пользователя (только отличия от данных карточки).
function getDocumentOverrides(PDO $db, int $employeeId, string $processType, string $document): array
{
    $stmt = $db->prepare(
        'SELECT d.fields FROM process_documents d
         JOIN employee_processes p ON p.id = d.process_id
         WHERE p.employee_id = ? AND p.process_type = ? AND d.doc_type = ?'
    );
    $stmt->execute([$employeeId, $processType, $document]);
    $row = $stmt->fetch();

    return $row ? (json_decode($row['fields'] ?? '', true) ?: []) : [];
}

function saveDocumentOverrides(PDO $db, int $employeeId, string $processType, string $document, array $overrides): void
{
    $db->prepare(
        'INSERT INTO employee_processes (employee_id, process_type, created_by) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE process_type = process_type'
    )->execute([$employeeId, $processType, currentUser()['id'] ?? null]);

    $processId = (int) $db->query(
        'SELECT id FROM employee_processes WHERE employee_id = ' . (int) $employeeId . ' AND process_type = ' . $db->quote($processType)
    )->fetchColumn();

    $db->prepare(
        'INSERT INTO process_documents (process_id, doc_type, fields) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE fields = VALUES(fields)'
    )->execute([$processId, $document, $overrides ? json_encode($overrides, JSON_UNESCAPED_UNICODE) : null]);
}

// Документы формируются по шаблонам templates/processes/*.docx (сделаны из
// образцов заказчика, см. templates/build_templates.php): шаблон повторяется
// полностью, подставляются только значения полей.

function dotDate(?string $date): string
{
    if (!$date) {
        return '';
    }

    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);

    return $parsed ? $parsed->format('d.m.Y') : $date;
}

// Бланк организации в документ процесса: применяется к гарантийному письму
// и описи; Ходатайство — установленная МВД форма, она выдаётся без бланка.
function buildGuaranteeLetter(array $v, ?array $organization): string
{
    $values = $v;
    $values['document_date'] = slashDate($v['document_date']);
    $values['birth_date'] = slashDate($v['birth_date']);
    $values['passport_issue_date'] = slashDate($v['passport_issue_date']);
    $values['passport_expire_date'] = slashDate($v['passport_expire_date']);
    [$values['stay_address_1'], $values['stay_address_2']] = addressLines($v['stay_address']);

    return docxFromTemplate('guarantee_letter', $values, letterheadPath($organization));
}

function buildPetition(array $v): string
{
    $upper = static fn(string $s): string => mb_strtoupper($s);
    $values = $v;
    $values['document_date'] = dotDate($v['document_date']);
    $values['org_egrul_date'] = dotDate($v['org_egrul_date']);
    $values['birth_date'] = slashDate($v['birth_date']);
    $values['passport_issue_date'] = slashDate($v['passport_issue_date']);
    $values['passport_expire_date'] = slashDate($v['passport_expire_date']);
    $values['gender_male'] = ($v['gender'] ?? '') === 'male' ? 'X' : '';
    $values['gender_female'] = ($v['gender'] ?? '') === 'female' ? 'X' : '';

    foreach (['birth_country', 'birth_place', 'citizenship', 'residence_country', 'residence_region', 'visa_country', 'visa_city'] as $key) {
        $values[$key] = $upper($v[$key]);
    }

    return docxFromTemplate('petition', $values);
}

// Опись документов, принятых к рассмотрению (приложение № 2 к инструкции
// МВД) — для нескольких сотрудников сразу. $header: reference_number,
// card_number, applicant, accept_date, officer, signer.
function buildInventory(array $contexts, array $header, ?array $organization, ?array $region): string
{
    $regionDative = $region['name_dative'] ?? '';
    $regionLong = preg_replace('/^г\.\s*/u', 'городу ', $regionDative);

    // Кратность — параметр процесса "Приглашение" (в образце ходатайства
    // фиксированный), одинаков для всех.
    $multiplicity = mb_strtoupper(VISA_MULTIPLICITY_LABELS[INVITATION_TRIP['multiplicity']]);
    $rows = [];

    foreach ($contexts as $i => $c) {
        $e = $c['employee'];
        $rows[] = [
            'citizenship' => mb_strtoupper((string) $e['citizenship']),
            'full_name' => mb_strtoupper(trim($e['last_name'] . ' ' . $e['first_name'])),
            'passport' => passportNumber($c),
            'multiplicity' => $multiplicity,
        ];
    }

    $values = [
        'reference_number' => $header['reference_number'],
        'card_number' => $header['card_number'],
        'applicant' => $header['applicant'] !== '' ? $header['applicant'] : str_repeat('_', 40),
        'accept_date' => slashDate($header['accept_date']),
        'officer' => $header['officer'],
        'region_dative' => $regionDative,
        'region_dative_long' => $regionLong,
        'signer_name' => $header['signer'],
    ];

    return docxFromTemplate('inventory', $values, letterheadPath($organization), $rows);
}

// Адрес в гарантийном письме пишется на двух линиях бланка. Делим по словам:
// первая линия — сколько помещается по ширине (оценка по ширине символов
// Times New Roman 12 пт, строка 41 em), остаток — на вторую линию.
function addressLines(string $address, float $lineEm = 37.0): array
{
    $width = static function (string $text): float {
        $sum = 0.0;

        foreach (mb_str_split($text) as $char) {
            $sum += match (true) {
                $char === ' ' => 0.25,
                (bool) preg_match('/[\d]/u', $char) => 0.5,
                (bool) preg_match('/[,.;:\-()«»"\/]/u', $char) => 0.27,
                mb_strtoupper($char) === $char && preg_match('/\p{L}/u', $char) => 0.7,
                default => 0.47,
            };
        }

        return $sum;
    };

    $words = preg_split('/\s+/u', trim($address), -1, PREG_SPLIT_NO_EMPTY);
    $first = '';

    foreach ($words as $i => $word) {
        $candidate = $first === '' ? $word : $first . ' ' . $word;

        if ($first !== '' && $width($candidate) > $lineEm) {
            return [$first, implode(' ', array_slice($words, $i))];
        }

        $first = $candidate;
    }

    return [$first, ''];
}
