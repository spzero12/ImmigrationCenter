<?php

require __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/documents.php';
requireLogin();

$vendorAutoload = __DIR__ . '/vendor/autoload.php';

if (!is_file($vendorAutoload)) {
    http_response_code(500);
    exit(
        'PHPWord не установлен (нет vendor/autoload.php). composer install ' .
        'нужно выполнить локально и залить vendor/ на сервер — см. ' .
        '.agent-os/specs/2026-08-20-document-generation/tasks.md.'
    );
}

require_once $vendorAutoload;

$documentId = (int) ($_GET['document_id'] ?? 0);

$db = getDb();
$stmt = $db->prepare('SELECT * FROM employee_documents WHERE id = ?');
$stmt->execute([$documentId]);
$document = $stmt->fetch();

if (!$document) {
    http_response_code(404);
    exit('Документ не найден');
}

$employeeStmt = $db->prepare(
    'SELECT e.*, o.name AS organization, d.name AS department
     FROM employees e
     LEFT JOIN organizations o ON o.id = e.organization_id
     LEFT JOIN departments d ON d.id = e.department_id
     WHERE e.id = ?'
);
$employeeStmt->execute([$document['employee_id']]);
$employee = $employeeStmt->fetch();

if (!$employee) {
    http_response_code(404);
    exit('Сотрудник не найден');
}

$templatePath = __DIR__ . '/templates/' . $document['type'] . '.docx';

if (!is_file($templatePath)) {
    http_response_code(404);
    exit(
        "Шаблон для типа документа «{$document['type']}» не найден в " .
        'templates/. Положите .docx-шаблон под этим именем — см. ' .
        'templates/README.md.'
    );
}

$passport = getLinkedPassport($db, $document['passport_document_id']);
$fields = documentTemplatePlaceholders($employee, $document, $passport);

$templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);

foreach ($fields as $placeholder => $value) {
    $templateProcessor->setValue($placeholder, (string) $value);
}

// TemplateProcessor::saveAs() пишет через ZipArchive, которому нужен
// реальный путь на диске (не php://output) — поэтому временный файл
// неизбежен, но он существует считанные миллисекунды и сразу удаляется,
// персональные данные на диске не остаются.
$tmpPath = tempnam(sys_get_temp_dir(), 'doc');
$templateProcessor->saveAs($tmpPath);

$downloadName = 'document_' . $document['id'] . '.docx';

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($tmpPath));
readfile($tmpPath);
unlink($tmpPath);
exit;
