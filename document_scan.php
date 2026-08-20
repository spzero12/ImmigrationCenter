<?php

require __DIR__ . '/includes/auth.php';
requireLogin();

$documentId = (int) ($_GET['document_id'] ?? 0);

$stmt = getDb()->prepare('SELECT scan_stored_name, scan_original_name, scan_mime_type FROM employee_documents WHERE id = ?');
$stmt->execute([$documentId]);
$document = $stmt->fetch();

if (!$document || !$document['scan_stored_name']) {
    http_response_code(404);
    exit('Скан не найден');
}

$path = __DIR__ . '/uploads/documents/' . $document['scan_stored_name'];

if (!is_file($path)) {
    http_response_code(404);
    exit('Файл не найден на диске');
}

$originalName = str_replace(["\r", "\n"], '', $document['scan_original_name'] ?? 'scan');

header('Content-Type: ' . $document['scan_mime_type']);
header('Content-Disposition: inline; filename="' . basename($originalName) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
