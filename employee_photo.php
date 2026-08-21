<?php

require __DIR__ . '/includes/auth.php';
requireLogin();

$employeeId = (int) ($_GET['employee_id'] ?? 0);

$stmt = getDb()->prepare('SELECT photo_stored_name, photo_original_name, photo_mime_type FROM employees WHERE id = ?');
$stmt->execute([$employeeId]);
$employee = $stmt->fetch();

if (!$employee || !$employee['photo_stored_name']) {
    http_response_code(404);
    exit('Фото не найдено');
}

$path = __DIR__ . '/uploads/employees/' . $employee['photo_stored_name'];

if (!is_file($path)) {
    http_response_code(404);
    exit('Файл не найден на диске');
}

$originalName = str_replace(["\r", "\n"], '', $employee['photo_original_name'] ?? 'photo');

header('Content-Type: ' . $employee['photo_mime_type']);
header('Content-Disposition: inline; filename="' . basename($originalName) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
