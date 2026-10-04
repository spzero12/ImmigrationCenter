<?php

require __DIR__ . '/includes/auth.php';
requireLogin();

$stmt = getDb()->prepare('SELECT letterhead_stored_name, letterhead_original_name FROM organizations WHERE id = ?');
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$organization = $stmt->fetch();

if (!$organization || !$organization['letterhead_stored_name']) {
    http_response_code(404);
    exit('Бланк не найден');
}

$path = __DIR__ . '/uploads/organizations/' . $organization['letterhead_stored_name'];

if (!is_file($path)) {
    http_response_code(404);
    exit('Файл не найден на диске');
}

$originalName = str_replace(["\r", "\n", '"'], '', $organization['letterhead_original_name'] ?? 'letterhead.docx');

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . basename($originalName) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
