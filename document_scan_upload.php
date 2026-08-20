<?php

require __DIR__ . '/includes/auth.php';
requireLogin();

const SCAN_MAX_SIZE = 10 * 1024 * 1024;

const SCAN_ALLOWED_MIME_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'application/pdf' => 'pdf',
];

$documentId = (int) ($_POST['document_id'] ?? 0);
$redirectTo = 'document_form.php?id=' . $documentId;

if ($documentId <= 0) {
    http_response_code(400);
    exit('Не указан документ');
}

$db = getDb();
$stmt = $db->prepare('SELECT scan_stored_name FROM employee_documents WHERE id = ?');
$stmt->execute([$documentId]);
$existing = $stmt->fetch();

if (!$existing) {
    http_response_code(404);
    exit('Документ не найден');
}

if (empty($_FILES['scan']['tmp_name']) || $_FILES['scan']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ' . $redirectTo . '&scan_error=save');
    exit;
}

if ($_FILES['scan']['size'] > SCAN_MAX_SIZE) {
    header('Location: ' . $redirectTo . '&scan_error=size');
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($_FILES['scan']['tmp_name']);

if (!isset(SCAN_ALLOWED_MIME_TYPES[$mimeType])) {
    header('Location: ' . $redirectTo . '&scan_error=type');
    exit;
}

$uploadDir = __DIR__ . '/uploads/documents/';

if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    header('Location: ' . $redirectTo . '&scan_error=save');
    exit;
}

$storedName = bin2hex(random_bytes(16)) . '.' . SCAN_ALLOWED_MIME_TYPES[$mimeType];

if (!move_uploaded_file($_FILES['scan']['tmp_name'], $uploadDir . $storedName)) {
    header('Location: ' . $redirectTo . '&scan_error=save');
    exit;
}

$stmt = $db->prepare(
    'UPDATE employee_documents SET scan_stored_name = ?, scan_original_name = ?, scan_mime_type = ?, scan_size = ? WHERE id = ?'
);
$stmt->execute([
    $storedName,
    $_FILES['scan']['name'],
    $mimeType,
    $_FILES['scan']['size'],
    $documentId,
]);

// Удаляем предыдущий файл только после того, как новый успешно сохранён
// и запись в БД обновлена — иначе при сбое можно потерять единственную
// копию скана.
if (!empty($existing['scan_stored_name'])) {
    $oldPath = $uploadDir . $existing['scan_stored_name'];

    if (is_file($oldPath)) {
        unlink($oldPath);
    }
}

header('Location: ' . $redirectTo);
exit;
