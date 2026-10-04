<?php

// Опись документов для нескольких сотрудников: GET ?ids=1,2,3 — форма
// шапки справки, POST — выгрузка .docx. Не хранится (каждый раз заново).

require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/processes.php';
requireLogin();

$db = getDb();
$rawIds = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['ids'] ?? '') : ($_GET['ids'] ?? '');
$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $rawIds)))));

$contexts = [];

foreach ($ids as $id) {
    $context = loadEmployeeContext($db, $id);

    if ($context) {
        $contexts[] = $context;
    }
}

$error = null;

if (!$contexts) {
    $error = 'Не выбраны сотрудники.';
} else {
    $organizationIds = array_unique(array_map(static fn($c) => (int) $c['employee']['organization_id'], $contexts));
    $regionIds = array_unique(array_map(static fn($c) => (int) $c['employee']['region_id'], $contexts));

    if (count($organizationIds) > 1 || count($regionIds) > 1) {
        $error = 'Опись можно сформировать только для сотрудников одной организации и одного региона — у выбранных они различаются.';
    }
}

$organization = $contexts[0]['organization'] ?? null;
$region = $contexts[0]['region'] ?? null;
$signers = [];

if ($organization) {
    $signersStmt = $db->prepare('SELECT * FROM organization_signers WHERE organization_id = ? ORDER BY id');
    $signersStmt->execute([$organization['id']]);
    $signers = $signersStmt->fetchAll();
}

if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $signerStmt = $db->prepare('SELECT * FROM organization_signers WHERE id = ? AND organization_id = ?');
    $signerStmt->execute([(int) ($_POST['signer_id'] ?? 0), $organization['id'] ?? 0]);
    $signer = $signerStmt->fetch();

    $header = [
        'reference_number' => trim($_POST['reference_number'] ?? ''),
        'card_number' => trim($_POST['card_number'] ?? ''),
        'applicant' => trim($_POST['applicant'] ?? '') ?: ($signer['full_name'] ?? ''),
        'accept_date' => $_POST['accept_date'] ?: date('Y-m-d'),
        'officer' => trim($_POST['officer'] ?? ''),
        'signer' => $signer['full_name'] ?? '',
    ];

    $bodyXml = buildInventory($contexts, $header, $organization, $region);
    docxSend(docxBuild($bodyXml, letterheadPath($organization)), 'Опись ' . date('d.m.Y') . '.docx');
}

$pageTitle = 'Опись';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header d-print-none mb-3">
    <h2 class="page-title">Опись документов</h2>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <a href="employees.php" class="btn btn-link">К списку сотрудников</a>
        <?php else: ?>
        <div class="card">
            <div class="card-body">
                <p class="text-secondary">Сотрудники (<?php echo count($contexts); ?>) — организация «<?php echo htmlspecialchars(organizationDisplayName($organization)); ?>», регион: <?php echo htmlspecialchars($region['name'] ?? 'не указан'); ?>:</p>
                <ul>
                    <?php foreach ($contexts as $c): ?>
                        <li>
                            <?php echo htmlspecialchars(employeeFullNameRu($c['employee'])); ?>
                            <?php if (passportNumber($c) === ''): ?><span class="text-danger"> — нет паспорта</span><?php endif; ?>
                            <?php if (empty($c['process']['multiplicity'])): ?><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="form-hint">Кратность берётся из данных процесса «Приглашение» каждого сотрудника.</p>

                <form method="post">
                    <input type="hidden" name="ids" value="<?php echo htmlspecialchars(implode(',', $ids)); ?>">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Справка №</label>
                            <input type="text" class="form-control" name="reference_number">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Номер учётной карточки</label>
                            <input type="text" class="form-control" name="card_number">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Дата приёма заявления</label>
                            <input type="date" class="form-control" name="accept_date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Подписант (ответственное лицо)</label>
                        <select class="form-select" name="signer_id">
                            <option value="">— не выбран —</option>
                            <?php foreach ($signers as $signer): ?>
                                <option value="<?php echo (int) $signer['id']; ?>"><?php echo htmlspecialchars($signer['position'] . ' — ' . $signer['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">«Дана» — ФИО заявителя</label>
                        <input type="text" class="form-control" name="applicant" placeholder="по умолчанию — ФИО подписанта">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Должность, ФИО сотрудника УВМ</label>
                        <input type="text" class="form-control" name="officer">
                    </div>
                    <div class="form-footer">
                        <a href="employees.php" class="btn btn-link">Назад</a>
                        <button type="submit" class="btn btn-primary"><i class="ti ti-download"></i> Сформировать опись (.docx)</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
