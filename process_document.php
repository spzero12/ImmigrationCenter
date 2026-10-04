<?php

// Страница документа процесса: форма с полями, предзаполненными из
// карточки сотрудника и организации; "Сформировать" отдаёт готовый .docx
// (с бланком организации). Правки запоминаются как переопределения.

require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/processes.php';
require_once __DIR__ . '/includes/audit.php';
requireLogin();

$employeeId = (int) ($_GET['employee_id'] ?? $_POST['employee_id'] ?? 0);
$document = $_GET['document'] ?? $_POST['document'] ?? '';
$processType = 'invitation';
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

        if ($key === 'gender' && !in_array($value, ['male', 'female', ''], true)) {
            $value = $defaults[$key];
        }

        $values[$key] = $value;

        if ($value !== (string) $defaults[$key]) {
            $overrides[$key] = $value;
        }
    }

    saveDocumentOverrides($db, $employeeId, $processType, $document, $overrides);

    if ($action === 'generate') {
        $builder = $document === 'petition' ? 'buildPetition' : 'buildGuaranteeLetter';
        $tmp = docxBuild($builder($values), letterheadPath($context['organization']));
        docxSend($tmp, $definition['label'] . ' ' . employeeFullNameRu($context['employee']) . '.docx');
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
$groups = [];

foreach ($fieldDefinitions as $key => $field) {
    $groups[$field[2]][$key] = $field;
}

$pageTitle = $definition['label'];
require __DIR__ . '/includes/header.php';

function pe($value): string
{
    return htmlspecialchars((string) $value);
}
?>

<div class="page-header d-print-none mb-3">
    <div>
        <div class="text-secondary"><a href="employee_form.php?id=<?php echo (int) $employee['id']; ?>#process"><?php echo pe(employeeFullNameRu($employee)); ?></a> / <?php echo pe(PROCESS_TYPES[$processType]['label']); ?></div>
        <h2 class="page-title"><?php echo pe($definition['label']); ?></h2>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <?php if ($notice): ?>
            <div class="alert alert-success"><?php echo pe($notice); ?></div>
        <?php endif; ?>
        <?php if (!$context['organization']): ?>
            <div class="alert alert-warning">У сотрудника не указана организация — реквизиты пустые, бланк не применится.</div>
        <?php elseif (!letterheadPath($context['organization'])): ?>
            <div class="alert alert-info">У организации нет загруженного бланка — документ будет без оформления.</div>
        <?php endif; ?>
        <?php if (!$context['region']): ?>
            <div class="alert alert-warning">У сотрудника не указан регион — адресат документа будет пустым.</div>
        <?php endif; ?>
        <?php if ($overrideKeys): ?>
            <div class="alert alert-secondary d-flex justify-content-between align-items-center">
                <span>Поля, отличающиеся от данных карточки, подсвечены (<?php echo count($overrideKeys); ?>).</span>
                <form method="post" onsubmit="return confirm('Сбросить все правки к данным карточки?');">
                    <input type="hidden" name="employee_id" value="<?php echo (int) $employee['id']; ?>">
                    <input type="hidden" name="document" value="<?php echo pe($document); ?>">
                    <input type="hidden" name="action" value="reset">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Сбросить к данным карточки</button>
                </form>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="employee_id" value="<?php echo (int) $employee['id']; ?>">
            <input type="hidden" name="document" value="<?php echo pe($document); ?>">

            <?php if ($document === 'petition'): ?>
                <div class="alert alert-light border">Фиксированные параметры процесса «Приглашение» (в образце не выделены красным): цель — <?php echo pe(INVITATION_TRIP['purpose']); ?>, срок — 90 дней, виза однократная, рабочая.</div>
            <?php endif; ?>

            <?php foreach ($groups as $groupTitle => $fields): ?>
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title"><?php echo pe($groupTitle); ?></h3></div>
                    <div class="card-body">
                        <div class="row">
                            <?php if ($groupTitle === 'Подписант'): ?>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Подписант организации</label>
                                    <select class="form-select" id="signer-select">
                                        <option value="">— выбрать из списка (заполнит поля ниже) —</option>
                                        <?php foreach ($context['signers'] as $signer): ?>
                                            <option value="<?php echo (int) $signer['id']; ?>"
                                                    data-position="<?php echo pe($signer['position']); ?>"
                                                    data-name="<?php echo pe($signer['full_name']); ?>"
                                                    data-short="<?php echo pe($signer['short_name']); ?>"><?php echo pe($signer['position'] . ' — ' . $signer['full_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($context['organization'] && !$context['signers']): ?>
                                        <div class="form-hint">У организации нет подписантов — <a href="organization_form.php?id=<?php echo (int) $context['organization']['id']; ?>#signers">добавьте</a> или впишите вручную.</div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php foreach ($fields as $key => $field): $readonly = $field[3] ?? false; $changed = in_array($key, $overrideKeys, true); ?>
                                <div class="col-md-<?php echo in_array($field[1], ['date'], true) || in_array($key, ['org_inn', 'org_ogrn', 'org_kpp', 'stay_house', 'stay_corpus', 'stay_building', 'stay_flat'], true) ? '4' : '6'; ?> mb-3">
                                    <label class="form-label"><?php echo pe($field[0]); ?></label>
                                    <?php if ($field[1] === 'select'): ?>
                                        <select class="form-select<?php echo $changed ? ' border-warning' : ''; ?>" name="f[<?php echo pe($key); ?>]">
                                            <option value="">—</option>
                                            <option value="male" <?php echo $current[$key] === 'male' ? 'selected' : ''; ?>>Мужской</option>
                                            <option value="female" <?php echo $current[$key] === 'female' ? 'selected' : ''; ?>>Женский</option>
                                        </select>
                                    <?php else: ?>
                                        <input type="<?php echo $field[1] === 'date' ? 'date' : 'text'; ?>"
                                               class="form-control<?php echo $changed ? ' border-warning' : ''; ?>"
                                               name="f[<?php echo pe($key); ?>]"
                                               data-field="<?php echo pe($key); ?>"
                                               value="<?php echo pe($current[$key]); ?>"
                                               <?php echo $readonly ? 'readonly' : ''; ?>>
                                    <?php endif; ?>
                                    <?php if ($changed): ?>
                                        <div class="form-hint text-warning">В карточке: <?php echo pe($defaults[$key] !== '' ? $defaults[$key] : '— пусто —'); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="form-footer d-flex gap-2">
                <a href="employee_form.php?id=<?php echo (int) $employee['id']; ?>#process" class="btn btn-link">К карточке</a>
                <button type="submit" name="action" value="save" class="btn btn-outline-primary">Сохранить правки</button>
                <button type="submit" name="action" value="generate" class="btn btn-primary"><i class="ti ti-download"></i> Сформировать .docx</button>
            </div>
        </form>
    </div>
</div>

<script>
    $(function () {
        $('#signer-select').on('change', function () {
            const $option = $(this).find('option:selected');

            if (!$option.val()) {
                return;
            }

            $('[data-field="signer_position"]').val($option.data('position'));
            $('[data-field="signer_name"]').val($option.data('name'));
            $('[data-field="signer_short"]').val($option.data('short'));
        });
    });
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
