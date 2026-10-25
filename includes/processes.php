<?php

// Процессы оформления документов: «Приглашение» и «Виза» (продление визы).
// Опись — групповой документ (несколько сотрудников из таблицы), в состав
// процесса не входит; у каждого процесса своя форма описи (INVENTORY_TYPES).
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
    'visa' => [
        'label' => 'Виза',
        'documents' => [
            'visa_petition' => ['label' => 'Ходатайство', 'file_label' => 'Ходатайство на визу', 'available' => true],
            'visa_questionnaire' => ['label' => 'Визовая анкета', 'available' => true],
            'visa_power_of_attorney' => ['label' => 'Доверенность', 'available' => false],
        ],
    ],
];

// Формы описи — по процессам. Ходатайства на приглашение и на визу, как и описи,
// разные: при формировании описи выбирается нужная.
const INVENTORY_TYPES = [
    'invitation' => ['label' => 'Опись на приглашение', 'description' => 'Справка и опись документов, принятых к рассмотрению (приложение № 2 к инструкции МВД)'],
    'visa' => ['label' => 'Опись на визу', 'description' => 'Опись документов, подаваемых в УВМ на продление визы'],
];

// Процесс, к которому относится документ ('petition' → 'invitation', 'visa_petition' → 'visa').
function processTypeOfDocument(string $document): ?string
{
    foreach (PROCESS_TYPES as $type => $definition) {
        if (isset($definition['documents'][$document])) {
            return $type;
        }
    }

    return null;
}

// Документы, которые печатаются на бланке организации (остальные — установленные
// формы МВД, выдаются без бланка).
function documentUsesLetterhead(string $document): bool
{
    return in_array($document, ['guarantee_letter', 'visa_petition'], true);
}

// Пол и параметры визы — выбор из списка: в документе отмечаются клетки «X».
const CHOICE_GENDER = ['male' => 'М', 'female' => 'Ж'];

const VISA_CATEGORY_LABELS = [
    'private' => 'частная',
    'humanitarian' => 'гуманитарная',
    'business' => 'деловая',
    'work' => 'рабочая',
    'tourist' => 'туристическая',
    'tourist_group' => 'туристическая групповая',
    'study' => 'учебная',
    'reception' => 'на въезд в целях приёма в гражданство',
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
            'gender' => ['3.7. Пол', 'select', 'Приглашаемый', false, CHOICE_GENDER],
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

    if ($document === 'visa_petition') {
        $trip = 'Сведения о ходатайстве';
        $person = 'Иностранный гражданин';

        return [
            'document_date' => ['Дата', 'date', 'Документ'],
            'region_dative' => ['УВМ ГУ МВД России по … (из региона сотрудника)', 'text', 'Документ', true],
            'org_name_genitive' => ['От … (наименование организации в родительном падеже)', 'text', $org],
            'org_inn' => ['ИНН', 'text', $org],
            'org_address_phone' => ['Адрес и телефон', 'text', $org],
            'visa_until' => ['Прошу продлить визу до', 'date', $trip],
            'last_name' => ['Фамилия рус.', 'text', $person],
            'last_name_lat' => ['Фамилия лат.', 'text', $person],
            'first_name' => ['Имя рус.', 'text', $person],
            'first_name_lat' => ['Имя лат.', 'text', $person],
            'middle_name' => ['Отчество рус.', 'text', $person],
            'middle_name_lat' => ['Отчество лат.', 'text', $person],
            'birth_date' => ['Дата рождения', 'date', $person],
            'citizenship' => ['Гражданство', 'text', $person],
            'gender' => ['Пол', 'select', $person, false, CHOICE_GENDER],
            'passport_series' => ['Паспорт: серия', 'text', 'Документ, удостоверяющий личность'],
            'passport_number' => ['Паспорт: номер', 'text', 'Документ, удостоверяющий личность'],
            'passport_issue_date' => ['Паспорт: срок действия с', 'date', 'Документ, удостоверяющий личность'],
            'passport_expire_date' => ['Паспорт: срок действия по', 'date', 'Документ, удостоверяющий личность'],
            'visa_series' => ['Виза: серия бланка', 'text', 'Сведения о визе'],
            'visa_number' => ['Виза: номер', 'text', 'Сведения о визе'],
            'visa_issue_date' => ['Виза: срок действия с', 'date', 'Сведения о визе'],
            'visa_expire_date' => ['Виза: срок действия по', 'date', 'Сведения о визе'],
            'visa_reason' => ['В связи с', 'text', $trip],
            'entry_date' => ['Дата въезда', 'date', $trip],
            'entry_checkpoint' => ['КПП въезда', 'text', $trip],
            'signer_position' => ['Должность', 'text', $signer],
            'signer_name' => ['ФИО подписанта', 'text', $signer],
        ];
    }

    if ($document === 'visa_questionnaire') {
        $kind = 'Вид визы';
        $person = 'О заявителе';
        $parties = 'Стороны, адреса, маршрут';

        return [
            'visa_multiplicity' => ['Кратность визы', 'select', $kind, false, VISA_MULTIPLICITY_LABELS],
            'visa_category' => ['Категория (вид) визы', 'select', $kind, false, VISA_CATEGORY_LABELS],
            'visa_reason' => ['В связи с', 'text', $kind],
            'last_name' => ['1. Фамилия рус.', 'text', $person],
            'last_name_lat' => ['1. Фамилия лат.', 'text', $person],
            'first_name' => ['2. Имя рус.', 'text', $person],
            'first_name_lat' => ['2. Имя лат.', 'text', $person],
            'middle_name' => ['3. Отчество рус.', 'text', $person],
            'middle_name_lat' => ['3. Отчество лат.', 'text', $person],
            'birth_date' => ['4. Дата рождения', 'date', $person],
            'birth_place' => ['5. Место рождения (страна, город)', 'text', $person],
            'gender' => ['6. Пол', 'select', $person, false, CHOICE_GENDER],
            'citizenship' => ['7. Гражданство', 'text', $person],
            'passport_series' => ['8. Паспорт: серия', 'text', $person],
            'passport_number' => ['8. Паспорт: номер', 'text', $person],
            'passport_issue_date' => ['8. Паспорт: срок действия с', 'date', $person],
            'passport_expire_date' => ['8. Паспорт: срок действия по', 'date', $person],
            'inviting_party' => ['9. Сведения о приглашающей стороне', 'text', $parties],
            'receiving_party' => ['10. Принимающая сторона: наименование, ИНН', 'text', $parties],
            'receiving_address' => ['10. Принимающая сторона: адрес', 'text', $parties],
            'registration_address' => ['11. Адрес постановки на миграционный учёт', 'text', $parties],
            'route' => ['12. Маршрут предполагаемого пребывания', 'text', $parties],
            'home_address' => ['13. Адрес в стране постоянного проживания', 'text', $parties],
            'workplace' => ['14. Место работы или учёбы, должность', 'text', $parties],
            'visa_series' => ['Имеющаяся виза: серия бланка', 'text', 'Сведения об имеющейся визе'],
            'visa_number' => ['Имеющаяся виза: номер', 'text', 'Сведения об имеющейся визе'],
            'visa_identifier' => ['Идентификатор визы', 'text', 'Сведения об имеющейся визе'],
            'visa_issue_date' => ['Имеющаяся виза: срок действия с', 'date', 'Сведения об имеющейся визе'],
            'visa_expire_date' => ['Имеющаяся виза: срок действия по', 'date', 'Сведения об имеющейся визе'],
            'invitation_number' => ['№ приглашения', 'text', 'Сведения об имеющейся визе'],
            'document_date' => ['Дата', 'date', 'Документ'],
        ];
    }

    return [];
}

// Поля-выборы документа: ключ => [значение => подпись] (в docx — клетки «X»).
function documentChoiceFields(array $definitions): array
{
    $choices = [];

    foreach ($definitions as $key => $field) {
        if (!empty($field[4])) {
            $choices[$key] = $field[4];
        }
    }

    return $choices;
}

// Значения {поле}_{вариант} = 'X' для выбранного варианта (шаблоны .docx).
function choiceMarks(array $values, array $definitions): array
{
    $marks = [];

    foreach (documentChoiceFields($definitions) as $key => $options) {
        foreach ($options as $option => $label) {
            $marks[$key . '_' . $option] = ($values[$key] ?? '') === (string) $option ? 'X' : '';
        }
    }

    return $marks;
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

    $workSite = null;

    if (!empty($employee['work_site_id'])) {
        $siteStmt = $db->prepare('SELECT * FROM work_sites WHERE id = ?');
        $siteStmt->execute([$employee['work_site_id']]);
        $workSite = $siteStmt->fetch() ?: null;
    }

    return [
        'employee' => $employee,
        'organization' => $organization,
        'region' => $region,
        'work_site' => $workSite,
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
    $visa = $context['documents']['visa'] ?? [];
    $visaExtra = json_decode($visa['extra_fields'] ?? '', true) ?: [];
    $registration = $context['documents']['registration'] ?? [];
    $registrationExtra = json_decode($registration['extra_fields'] ?? '', true) ?: [];
    $workSite = $context['work_site'] ?? [];
    $upper = static fn(string $value): string => mb_strtoupper($value);
    $join = static fn(array $parts): string => implode(', ', array_filter(array_map('trim', $parts), static fn($part) => $part !== ''));
    $orgPhone = (string) ($o['phone'] ?? '');

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
        // Процесс «Виза»
        'org_name_genitive' => organizationNameGenitive($orgFullName),
        'org_address_phone' => $join([$o['legal_address'] ?? '', $orgPhone]),
        'visa_until' => '',
        'visa_series' => $visa['series'] ?? '',
        'visa_number' => $visa['number'] ?? '',
        'visa_issue_date' => $visa['issue_date'] ?? '',
        'visa_expire_date' => $visa['expire_date'] ?? '',
        'visa_identifier' => $visaExtra['visa_identifier'] ?? '',
        'invitation_number' => $visaExtra['invitation_number'] ?? '',
        'visa_multiplicity' => visaMultiplicityFromText((string) ($visaExtra['visa_multiplicity'] ?? '')),
        'visa_category' => visaCategoryFromText((string) ($visaExtra['visa_category'] ?? '')),
        'visa_reason' => $document === 'visa_questionnaire' ? 'Заключением трудового договора' : 'Трудовая деятельность',
        'entry_date' => '',
        'entry_checkpoint' => '',
        'inviting_party' => $join([$o['name'] ?? '', ($o['inn'] ?? '') !== '' ? 'ИНН ' . $o['inn'] : '', $o['legal_address'] ?? '', $orgPhone, $o['email'] ?? '']),
        'receiving_party' => $registrationExtra['registering_party'] ?? $join([$o['name'] ?? '', ($o['inn'] ?? '') !== '' ? 'ИНН ' . $o['inn'] : '']),
        'receiving_address' => $registrationExtra['address'] ?? ($workSite['address'] ?? stayAddressLine($a)),
        'registration_address' => $registrationExtra['address'] ?? stayAddressLine($a),
        'route' => $a['locality'] ?? '',
        'home_address' => $upper($join([$e['residence_country'] ?? '' ?: ($e['birth_country'] ?? ''), $e['birth_place'] ?? ''])),
        'workplace' => $join([$o['name'] ?? '', $upper((string) ($e['position'] ?? '')), $workSite['address'] ?? ($o['actual_address'] ?? ''), $orgPhone]),
    ];

    // Анкета: место рождения «страна, город» одной строкой; без отчества — «нет».
    if ($document === 'visa_questionnaire') {
        $all['birth_place'] = $upper($join([$e['birth_country'] ?? '', $e['birth_place'] ?? '']));
    }

    // В визовых документах отсутствующие фамилия / отчество пишутся как в образцах.
    if (in_array($document, ['visa_petition', 'visa_questionnaire'], true)) {
        foreach (['last_name', 'last_name_lat'] as $key) {
            $all[$key] = $all[$key] !== '' ? $all[$key] : '-';
        }
    }

    if ($document === 'visa_questionnaire') {
        foreach (['middle_name', 'middle_name_lat'] as $key) {
            $all[$key] = $all[$key] !== '' ? $all[$key] : 'нет';
        }
    }

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

// «Общество с ограниченной ответственностью "…"» → «Общества с …» (для «От …» в ходатайстве).
function organizationNameGenitive(string $name): string
{
    $forms = [
        'Общество с ограниченной ответственностью' => 'Общества с ограниченной ответственностью',
        'Общество с дополнительной ответственностью' => 'Общества с дополнительной ответственностью',
        'Непубличное акционерное общество' => 'Непубличного акционерного общества',
        'Публичное акционерное общество' => 'Публичного акционерного общества',
        'Закрытое акционерное общество' => 'Закрытого акционерного общества',
        'Открытое акционерное общество' => 'Открытого акционерного общества',
        'Акционерное общество' => 'Акционерного общества',
        'Индивидуальный предприниматель' => 'Индивидуального предпринимателя',
        'Муниципальное унитарное предприятие' => 'Муниципального унитарного предприятия',
        'Государственное унитарное предприятие' => 'Государственного унитарного предприятия',
    ];

    foreach ($forms as $nominative => $genitive) {
        if (mb_stripos($name, $nominative) === 0) {
            return $genitive . mb_substr($name, mb_strlen($nominative));
        }
    }

    return $name;
}

// Кратность и категорию визы в карточке вводят текстом (доп. поля документа «Виза»).
function visaMultiplicityFromText(string $text): string
{
    $text = mb_strtolower($text);

    return match (true) {
        str_contains($text, 'двукр') => 'double',
        str_contains($text, 'одно') => 'single',
        default => 'multiple',
    };
}

function visaCategoryFromText(string $text): string
{
    $text = mb_strtolower($text);

    return match (true) {
        str_contains($text, 'групп') => 'tourist_group',
        str_contains($text, 'тур') => 'tourist',
        str_contains($text, 'гуман') => 'humanitarian',
        str_contains($text, 'дел') => 'business',
        str_contains($text, 'учеб') => 'study',
        str_contains($text, 'част') => 'private',
        str_contains($text, 'прием') || str_contains($text, 'приём') => 'reception',
        default => 'work',
    };
}

// Общее для визовых документов: даты через «/», ФИО, гражданство и места — заглавными.
function visaDocumentValues(array $v, string $document, array $upperKeys): array
{
    $values = $v;
    $definitions = documentFieldDefinitions($document);

    foreach ($definitions as $key => $field) {
        if ($field[1] === 'date') {
            $values[$key] = slashDate($v[$key] ?? '');
        }
    }

    foreach ($upperKeys as $key) {
        $value = (string) ($v[$key] ?? '');
        // «нет» (нет отчества) и «-» в образцах пишутся как есть.
        $values[$key] = mb_strtolower(trim($value)) === 'нет' ? $value : mb_strtoupper($value);
    }

    return $values + choiceMarks($v, $definitions);
}

// Ходатайство на продление визы — на бланке организации (в образце: «на бланке компании»).
function buildVisaPetition(array $v, ?array $organization): string
{
    $values = visaDocumentValues($v, 'visa_petition', [
        'last_name', 'last_name_lat', 'first_name', 'first_name_lat', 'middle_name', 'middle_name_lat', 'citizenship',
    ]);
    // Адрес и телефон — на двух линиях образца (первая вместе с ИНН).
    [$values['org_address_phone_1'], $values['org_address_phone_2']] = addressLines((string) $v['org_address_phone'], 29.6);

    return docxFromTemplate('visa_petition', $values, letterheadPath($organization));
}

// Визовая анкета — установленная форма, без бланка.
function buildVisaQuestionnaire(array $v): string
{
    $values = visaDocumentValues($v, 'visa_questionnaire', [
        'last_name', 'last_name_lat', 'first_name', 'first_name_lat', 'middle_name', 'middle_name_lat',
        'citizenship', 'birth_place', 'home_address',
    ]);
    [$values['inviting_party_1'], $values['inviting_party_2']] = addressLines((string) $v['inviting_party'], 26.0);
    [$values['route_1'], $values['route_2']] = addressLines((string) $v['route'], 19.0);
    [$values['workplace_1'], $values['workplace_2']] = addressLines((string) $v['workplace'], 24.5);

    return docxFromTemplate('visa_questionnaire', $values);
}

// Опись на продление визы — для нескольких сотрудников; $header: signer_position, signer_name.
function buildVisaInventory(array $contexts, array $header, ?array $organization, ?array $region): string
{
    $rows = [];

    foreach ($contexts as $c) {
        $e = $c['employee'];
        $rows[] = [
            'citizenship' => mb_strtoupper((string) $e['citizenship']),
            'full_name' => mb_strtoupper(trim(($e['last_name'] !== '-' ? $e['last_name'] : '') . ' ' . $e['first_name'])),
            'passport' => passportNumber($c),
        ];
    }

    $values = [
        'region_dative' => $region['name_dative'] ?? '',
        'org_short_name' => $organization['name'] ?? '',
        'signer_position' => $header['signer_position'] !== '' ? $header['signer_position'] : 'Генеральный директор',
        'signer_name' => $header['signer_name'],
    ];

    return docxFromTemplate('visa_inventory', $values, letterheadPath($organization), $rows);
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
