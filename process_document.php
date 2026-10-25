<?php

// Страница документа процесса: форма с полями, предзаполненными из
// карточки сотрудника и организации; "Сформировать" отдаёт готовый .docx
// (с бланком организации). Правки запоминаются как переопределения.

require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/processes.php';
require_once __DIR__ . '/includes/audit.php';
require_once __DIR__ . '/includes/document_sheet.php';
requireLogin();

$employeeId = (int) ($_GET['employee_id'] ?? $_POST['employee_id'] ?? 0);
$document = $_GET['document'] ?? $_POST['document'] ?? '';
$processType = processTypeOfDocument($document) ?? 'invitation';
$db = getDb();

$definition = PROCESS_TYPES[$processType]['documents'][$document] ?? null;
$fieldDefinitions = documentFieldDefinitions($document);

if (!$definition || !$definition['available'] || !$fieldDefinitions) {
    http_response_code(404);
    exit('Документ недоступен');
}

$context = loadEmployeeContext($db, $employeeId);

if (!$context) {
    http_response_code(404);
    exit('Сотрудник не найден');
}

$defaults = documentDefaults($document, $context);
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'reset') {
        saveDocumentOverrides($db, $employeeId, $processType, $document, []);
        header('Location: process_document.php?employee_id=' . $employeeId . '&document=' . urlencode($document) . '&reset=1');
        exit;
    }

    $values = [];
    $overrides = [];

    foreach ($fieldDefinitions as $key => $field) {
        $readonly = $field[3] ?? false;
        $value = $readonly ? $defaults[$key] : trim($_POST['f'][$key] ?? '');

        // Выбор из списка (пол, кратность и категория визы): только допустимые значения.
        if (!empty($field[4]) && $value !== '' && !array_key_exists($value, $field[4])) {
            $value = $defaults[$key];
        }

        $values[$key] = $value;

        if ($value !== (string) $defaults[$key]) {
            $overrides[$key] = $value;
        }
    }

    saveDocumentOverrides($db, $employeeId, $processType, $document, $overrides);

    if ($action === 'generate') {
        $tmp = match ($document) {
            'petition' => buildPetition($values),
            'guarantee_letter' => buildGuaranteeLetter($values, $context['organization']),
            'visa_petition' => buildVisaPetition($values, $context['organization']),
            'visa_questionnaire' => buildVisaQuestionnaire($values),
        };
        docxSend($tmp, ($definition['file_label'] ?? $definition['label']) . ' ' . employeeFullNameRu($context['employee']) . '.docx');
    }

    $notice = 'Изменения сохранены.';
    $current = $values;
} else {
    $overrides = getDocumentOverrides($db, $employeeId, $processType, $document);
    $current = array_merge($defaults, array_intersect_key($overrides, $defaults));

    if (isset($_GET['reset'])) {
        $notice = 'Значения сброшены к данным карточки сотрудника и организации.';
    }
}

$overrideKeys = array_keys(array_filter(
    $current,
    static fn($value, $key) => (string) $value !== (string) $defaults[$key],
    ARRAY_FILTER_USE_BOTH
));

$employee = $context['employee'];
$sheetHtml = renderDocumentSheet($document, $fieldDefinitions, $current, $defaults);

$pageTitle = $definition['label'];
$extraScripts = ['js/process_document.js'];
require __DIR__ . '/includes/header.php';

function pe($value): string
{
    return htmlspecialchars((string) $value);
}
?>

<div class="page-header d-print-none mb-3">
    <div>
        <div class="text-secondary"><a href="employee_form.php?id=<?php echo (int) $employee['id']; ?>#process"><?php echo pe(employeeFullNameRu($employee)); ?></a> / <?php echo pe(PROCESS_TYPES[$processType]['label']); ?></div>
        <h2 class="page-title"><?php echo pe($definition['file_label'] ?? $definition['label']); ?></h2>
    </div>
</div>

<?php if ($notice): ?>
    <div class="alert alert-success"><?php echo pe($notice); ?></div>
<?php endif; ?>
<?php if (!$context['organization']): ?>
    <div class="alert alert-warning">У сотрудника не указана организация — реквизиты пустые<?php echo documentUsesLetterhead($document) ? ', бланк не применится' : ''; ?>.</div>
<?php elseif (documentUsesLetterhead($document) && !letterheadPath($context['organization'])): ?>
    <div class="alert alert-info">У организации нет загруженного бланка — документ будет без оформления.</div>
<?php endif; ?>
<?php if (!$context['region']): ?>
    <div class="alert alert-warning">У сотрудника не указан регион — адресат документа будет пустым.</div>
<?php endif; ?>

<form method="post" id="document-form">
    <input type="hidden" name="employee_id" value="<?php echo (int) $employee['id']; ?>">
    <input type="hidden" name="document" value="<?php echo pe($document); ?>">
    <?php foreach ($fieldDefinitions as $key => $field): ?>
        <input type="hidden" name="f[<?php echo pe($key); ?>]" value="<?php echo pe($current[$key]); ?>">
    <?php endforeach; ?>

    <div class="card mb-3 sheet-toolbar">
        <div class="card-body py-2 d-flex flex-wrap align-items-center gap-3">
            <?php if (isset($fieldDefinitions['signer_name']) || isset($fieldDefinitions['signer_short'])): ?>
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label mb-0 text-nowrap" for="signer-select">Подписант</label>
                    <select class="form-select form-select-sm" id="signer-select">
                        <option value="">— выбрать из списка —</option>
                        <?php foreach ($context['signers'] as $signer): ?>
                            <option value="<?php echo (int) $signer['id']; ?>"
                                    data-position="<?php echo pe($signer['position']); ?>"
                                    data-name="<?php echo pe($signer['full_name']); ?>"
                                    data-short="<?php echo pe($signer['short_name']); ?>"><?php echo pe($signer['position'] . ' — ' . $signer['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($context['organization'] && !$context['signers']): ?>
                    <span class="form-hint">У организации нет подписантов — <a href="organization_form.php?id=<?php echo (int) $context['organization']['id']; ?>#signers">добавьте</a> или впишите вручную.</span>
                <?php endif; ?>
            <?php endif; ?>
            <span class="text-secondary small sheet-legend">
                <span class="legend-name">Красным</span> — поля для заполнения: нажмите на значение и правьте прямо в тексте (название поля — в подсказке).
                <span class="legend-changed">Жёлтым</span> — отличается от карточки (подсказка — при наведении).
            </span>
            <div class="ms-auto d-flex gap-2">
                <?php if ($overrideKeys): ?>
                    <button type="submit" name="action" value="reset" class="btn btn-outline-secondary" formnovalidate
                            onclick="return confirm('Сбросить все правки к данным карточки?');">Сбросить к карточке</button>
                <?php endif; ?>
                <a href="employee_form.php?id=<?php echo (int) $employee['id']; ?>#process" class="btn btn-link">К карточке</a>
                <button type="submit" name="action" value="save" class="btn btn-outline-primary">Сохранить правки</button>
                <button type="submit" name="action" value="generate" class="btn btn-primary"><i class="ti ti-download"></i> Сформировать .docx</button>
            </div>
        </div>
    </div>

    <div class="sheet-scroll">
    <iframe id="sheet" class="sheet-frame" title="<?php echo pe($definition['label']); ?>" srcdoc="<?php echo pe($sheetHtml); ?>"></iframe>
    </div>
</form>



<?php require __DIR__ . '/includes/footer.php'; ?>
