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
require_once __DIR__ . '/docx_builder.php';

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

function genderMark(array $v, string $gender): string
{
    return ($v['gender'] ?? '') === $gender ? '[X]' : '[ ]';
}

// Гарантийное письмо — по образцу заказчика (samples/).
function buildGuaranteeLetter(array $v): string
{
    $requisites = trim($v['org_full_name'] . ', ' . $v['org_legal_address'] . ",\nИНН: " . $v['org_inn'] . ' ОГРН: ' . $v['org_ogrn'] . ' КПП: ' . $v['org_kpp'] . ' ' . $v['org_phone']);

    $body = docxPara('Начальнику (руководителю)', ['indent' => 5529, 'after' => 0]);
    $body .= docxPara('УВМ ГУ МВД России по ' . $v['region_dative'], ['indent' => 5529, 'after' => 0, 'bold' => true]);
    $body .= docxCaption('(наименование уполномоченного подразделения, подразделения по вопросам миграции территориального органа МВД России)', ['indent' => 5529, 'size' => 7]);
    $body .= docxPara('', ['indent' => 5529, 'after' => 0]);
    $body .= docxCaption('(Ф.И.О.)', ['indent' => 5529, 'size' => 7]);
    $body .= docxPara($requisites, ['align' => 'center', 'size' => 9, 'after' => 0, 'indent' => 680, 'right_indent' => 680]);
    $body .= docxCaption('(наименование, организационно-правовая форма, юридический адрес, ИНН/ОГРН/КПП, телефон)', ['indent' => 680, 'right_indent' => 680, 'size' => 7]);
    $body .= docxPara('Исх. № б/н от ' . slashDate($v['document_date']) . ' г.', ['after' => 12]);
    $body .= docxPara('ГАРАНТИЙНОЕ ПИСЬМО', ['align' => 'center', 'bold' => true, 'size' => 14, 'after' => 12]);
    $body .= docxPara($v['org_short_name'] . ', выступая приглашающей стороной в отношении приглашаемого иностранного гражданина (лица без гражданства):', ['align' => 'both']);

    $person = 'гр. ' . $v['citizenship'] . ', ' . $v['person_name'] . ', ' . slashDate($v['birth_date']) . ' г.р., паспорт ' . $v['passport_number']
        . ($v['passport_issue_date'] !== '' ? ' выдан ' . slashDate($v['passport_issue_date']) : '')
        . ($v['passport_expire_date'] !== '' ? ' до ' . slashDate($v['passport_expire_date']) : '');
    $body .= docxPara($person, ['align' => 'both', 'after' => 0, 'bold' => true]);
    $body .= docxCaption('(гражданство, фамилия, имя, отчество (при наличии), дата рождения, сведения о документе, удостоверяющем личность)');
    $body .= docxPara('гарантирует ему материальное, медицинское и жилищное обеспечение, а именно:', ['align' => 'both']);
    $body .= docxPara('1. Обязуется предоставить возможность для его проживания по адресу: ' . $v['stay_address'], ['align' => 'both', 'after' => 0]);
    $body .= docxCaption('(указывается адрес, где предполагается пребывание приглашаемого иностранного гражданина (лица без гражданства))');
    $body .= docxPara('2. Обеспечить медицинской помощью на период его пребывания в Российской Федерации на основании договора (полиса) добровольного медицинского страхования либо договора о предоставлении платных медицинских услуг, если иное не предусмотрено международным договором Российской Федерации и (или) федеральными законами Российской Федерации, или предоставить, при необходимости, денежные средства для получения им медицинской помощи.', ['align' => 'both']);
    $body .= docxPara('3. Обязуется предоставить денежные средства, необходимые для выезда из Российской Федерации по окончании срока его пребывания в Российской Федерации.', ['align' => 'both', 'after' => 24]);
    $body .= docxPara($v['signer_position'], ['after' => 0, 'bold' => true]);
    $body .= docxPara('____________________   ' . $v['signer_name'], ['after' => 0, 'align' => 'right']);
    $body .= docxCaption('(подпись, расшифровка подписи)', ['indent' => 4000]);

    return $body;
}

// Ходатайство о выдаче приглашения (приложение № 1 к приказу МВД № 677) —
// структура формы (разделы 1–3, подпись) в табличной вёрстке, не копия
// бланка. Сопровождающие лица (раздел 4) пока не заполняются.
function buildPetition(array $v): string
{
    $trip = INVITATION_TRIP;
    $mark = static fn(bool $on): string => $on ? '[X]' : '[ ]';
    $multiplicity = implode('   ', array_map(
        static fn($key, $label) => $mark($trip['multiplicity'] === $key) . ' ' . $label,
        array_keys(VISA_MULTIPLICITY_LABELS),
        VISA_MULTIPLICITY_LABELS
    ));
    $visaKind = implode('   ', array_map(
        static fn($key, $label) => $mark($trip['visa_kind'] === $key) . ' ' . $label,
        array_keys(VISA_KIND_LABELS),
        VISA_KIND_LABELS
    ));
    $gender = genderMark($v, 'male') . ' мужской   ' . genderMark($v, 'female') . ' женский';
    $upper = static fn(string $s): string => mb_strtoupper($s);

    $row = static fn(string $label, string $value): array => [$label, $value];
    $section = static fn(string $title): array => [['text' => $title, 'bold' => true, 'span' => 2, 'shade' => 'D9D9D9']];

    $body = docxPara('Приложение № 1 к Административному регламенту МВД России по предоставлению государственной услуги по оформлению и выдаче приглашений на въезд в Российскую Федерацию иностранных граждан и лиц без гражданства, утверждённому приказом МВД России от 29.09.2020 № 677', ['size' => 8, 'align' => 'right', 'indent' => 5000, 'after' => 8]);
    $body .= docxPara('ХОДАТАЙСТВО', ['align' => 'center', 'bold' => true, 'size' => 14, 'after' => 0]);
    $body .= docxPara('о выдаче приглашения на въезд в Российскую Федерацию для приглашающей организации', ['align' => 'center', 'after' => 4]);
    $body .= docxPara('УВМ ГУ МВД России по ' . $v['region_dative'], ['align' => 'center', 'size' => 10, 'after' => 8]);

    $body .= docxTable([
        $section('1. Сведения о приглашающей организации'),
        $row('Исх. №', ''),
        $row('Дата', slashDate($v['document_date'])),
        $row('1.1. Полное наименование', $v['org_full_name']),
        $row('ИНН', $v['org_inn']),
        $row('ОГРН', $v['org_ogrn']),
        $row('Дата внесения записи в ЕГРЮЛ', slashDate($v['org_egrul_date'])),
        $row('1.2. Место нахождения (почтовый)', $v['org_legal_address']),
        $row('Фактический', $v['org_actual_address']),
        $row('Телефон', $v['org_phone']),
        $row('Адрес электронной почты', $v['org_email']),
        $section('2. Сведения о поездке'),
        $row('2.1. Цель поездки', $trip['purpose']),
        $row('2.2. На срок (дней)', $trip['stay_days']),
        $row('2.3. Предполагаемый въезд с', ''),
        $row('2.4. Пребывание по', ''),
        $row('2.5. Кратность визы', $multiplicity),
        $row('2.6. Вид визы', $visaKind),
        $row('2.7. Пункты посещения в РФ', $v['visit_places']),
        $section('3. Сведения о приглашаемом лице'),
        $row('3.1. Фамилия рус.', $v['last_name']),
        $row('3.2. Фамилия лат.', $v['last_name_lat']),
        $row('3.3. Имя рус.', $v['first_name']),
        $row('3.4. Имя лат.', $v['first_name_lat']),
        $row('3.5. Отчество рус.', $v['middle_name']),
        $row('3.6. Отчество лат.', $v['middle_name_lat']),
        $row('3.7. Пол', $gender),
        $row('3.8. Дата рождения', slashDate($v['birth_date'])),
        $row('3.9. Государство рождения', $upper($v['birth_country'])),
        $row('3.10. Место рождения', $upper($v['birth_place'])),
        $row('3.11. Гражданство (подданство)', $upper($v['citizenship'])),
        $row('3.12. Государство постоянного проживания', $upper($v['residence_country'])),
        $row('3.13. Регион', $upper($v['residence_region'])),
        $row('3.14. Место получения визы', trim($upper($v['visa_country']) . ', ' . $upper($v['visa_city']), ', ')),
        $row('3.15. Место работы за рубежом', $v['foreign_workplace']),
        $row('3.16. Документ, удостоверяющий личность', 'Серия: ' . $v['passport_series'] . '   Номер: ' . $v['passport_number']
            . "\nКем выдан: " . $v['passport_issued_by']
            . "\nДата выдачи: " . slashDate($v['passport_issue_date']) . '   Действителен до: ' . slashDate($v['passport_expire_date'])),
        $row('3.17. Адрес предполагаемого места пребывания', stayAddressLine($v) . ($v['stay_phone'] !== '' ? "\nТелефон: " . $v['stay_phone'] : '')),
        $row('3.18. Ранее имевшиеся установочные данные', 'нет'),
    ], [3300, 6200]);

    $body .= docxPara('ОБ ОТВЕТСТВЕННОСТИ ЗА СООБЩЕНИЕ ЛОЖНЫХ СВЕДЕНИЙ В ХОДАТАЙСТВЕ ИЛИ ПРЕДСТАВЛЕНИЕ ПОДДЕЛЬНЫХ ДОКУМЕНТОВ ПРЕДУПРЕЖДЁН. С СОДЕРЖАНИЕМ СТАТЕЙ 26, 27 ФЕДЕРАЛЬНОГО ЗАКОНА «О ПОРЯДКЕ ВЫЕЗДА ИЗ РОССИЙСКОЙ ФЕДЕРАЦИИ И ВЪЕЗДА В РОССИЙСКУЮ ФЕДЕРАЦИЮ» ОЗНАКОМЛЕН. С АВТОМАТИЗИРОВАННОЙ ОБРАБОТКОЙ, ПЕРЕДАЧЕЙ И ХРАНЕНИЕМ ДАННЫХ, УКАЗАННЫХ В ХОДАТАЙСТВЕ, СОГЛАСЕН.', ['size' => 9, 'align' => 'both', 'before' => 6]);
    $body .= docxPara('[X] Подтверждаю достоверность указанных в заявлении сведений на приглашаемое лицо.', ['size' => 10]);
    $body .= docxPara($v['signer_position'] . ', ____________________   ' . $v['signer_short'], ['after' => 0, 'before' => 8]);
    $body .= docxCaption('(фамилия, инициалы уполномоченного лица, подпись)  М.П.', ['align' => 'left']);

    return $body;
}

// Опись документов, принятых к рассмотрению (приложение № 2 к инструкции
// МВД) — для нескольких сотрудников сразу. $header: reference_number,
// card_number, applicant, accept_date, officer, signer.
function buildInventory(array $contexts, array $header, ?array $organization, ?array $region): string
{
    $regionDative = $region['name_dative'] ?? '';

    $body = docxPara('Приложение № 2 к инструкции об организации деятельности органов внутренних дел РФ по оформлению и выдаче приглашений на въезд в РФ иностранных граждан и лиц без гражданства, учёту и хранению бланков приглашений', ['size' => 8, 'align' => 'right', 'indent' => 5000]);
    $body .= docxPara('СПРАВКА № ' . $header['reference_number'], ['bold' => true, 'align' => 'center', 'size' => 13, 'after' => 0]);
    $body .= docxPara('НОМЕР УЧЁТНОЙ КАРТОЧКИ: ' . $header['card_number'], ['align' => 'center', 'after' => 10]);
    $body .= docxPara('Дана: ' . $header['applicant'], ['after' => 0]);
    $body .= docxCaption('(Ф.И.О. заявителя — уполномоченного представителя организации)', ['align' => 'left']);
    $body .= docxPara('в том, что ' . slashDate($header['accept_date']) . ' от него (неё) принято к рассмотрению заявление о выдаче приглашения.', ['after' => 0]);
    $body .= docxCaption('(дата приёма заявления о приглашении)', ['align' => 'left']);
    $body .= docxPara($header['officer'], ['after' => 0]);
    $body .= docxCaption('(должность, Ф.И.О. сотрудника УВМ ГУ МВД России по ' . $regionDative . ')', ['align' => 'left']);
    $body .= docxPara('Приложение согласно описи:', ['after' => 10]);
    $body .= docxPara('ОПИСЬ', ['bold' => true, 'align' => 'center', 'size' => 13, 'after' => 0]);
    $body .= docxPara('документов, принятых к рассмотрению в УВМ ГУ МВД России по ' . $regionDative . ' на приглашение иностранных граждан', ['align' => 'center', 'after' => 8]);

    $rows = [[
        ['text' => '№ п/п', 'bold' => true, 'align' => 'center'],
        ['text' => 'Гражданство', 'bold' => true, 'align' => 'center'],
        ['text' => 'Фамилия, Имя', 'bold' => true, 'align' => 'center'],
        ['text' => '№ паспорта', 'bold' => true, 'align' => 'center'],
        ['text' => 'Кратность', 'bold' => true, 'align' => 'center'],
    ]];

    // Кратность — параметр процесса "Приглашение" (в образце ходатайства
    // фиксированный), одинаков для всех.
    $multiplicity = mb_strtoupper(VISA_MULTIPLICITY_LABELS[INVITATION_TRIP['multiplicity']]);

    foreach ($contexts as $i => $c) {
        $e = $c['employee'];
        $rows[] = [
            ['text' => (string) ($i + 1), 'align' => 'center'],
            mb_strtoupper((string) $e['citizenship']),
            mb_strtoupper(trim($e['last_name'] . ' ' . $e['first_name'])),
            passportNumber($c),
            $multiplicity,
        ];
    }

    $body .= docxTable($rows, [800, 2000, 3300, 1700, 1700]);
    $body .= docxPara('М.П.', ['before' => 12, 'after' => 0]);
    $body .= docxPara('Приглашающая организация: ' . organizationDisplayName($organization), ['after' => 0]);
    $body .= docxPara('____________________   ' . $header['signer'], ['after' => 0]);
    $body .= docxCaption('(ФИО ответственного лица)', ['align' => 'left']);

    return $body;
}
