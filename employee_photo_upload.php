<?php

require __DIR__ . '/includes/auth.php';
requireLogin();

const PHOTO_MAX_SIZE = 10 * 1024 * 1024;

const PHOTO_ALLOWED_MIME_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
];

$employeeId = (int) ($_POST['employee_id'] ?? 0);
$redirectTo = 'employee_form.php?id=' . $employeeId;

if ($employeeId <= 0) {
    http_response_code(400);
    exit('Не указан сотрудник');
}

$db = getDb();
$stmt = $db->prepare('SELECT photo_stored_name FROM employees WHERE id = ?');
$stmt->execute([$employeeId]);
$existing = $stmt->fetch();

if (!$existing) {
    http_response_code(404);
    exit('Сотрудник не найден');
}

if (empty($_FILES['photo']['tmp_name']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ' . $redirectTo . '&photo_error=save');
    exit;
}

if ($_FILES['photo']['size'] > PHOTO_MAX_SIZE) {
    header('Location: ' . $redirectTo . '&photo_error=size');
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($_FILES['photo']['tmp_name']);

if (!isset(PHOTO_ALLOWED_MIME_TYPES[$mimeType])) {
    header('Location: ' . $redirectTo . '&photo_error=type');
    exit;
}

$uploadDir = __DIR__ . '/uploads/employees/';

if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    header('Location: ' . $redirectTo . '&photo_error=save');
    exit;
}

$storedName = bin2hex(random_bytes(16)) . '.' . PHOTO_ALLOWED_MIME_TYPES[$mimeType];

if (!move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $storedName)) {
    header('Location: ' . $redirectTo . '&photo_error=save');
    exit;
}

$stmt = $db->prepare(
    'UPDATE employees SET photo_stored_name = ?, photo_original_name = ?, photo_mime_type = ?, photo_size = ? WHERE id = ?'
);
$stmt->execute([
    $storedName,
    $_FILES['photo']['name'],
    $mimeType,
    $_FILES['photo']['size'],
    $employeeId,
]);

if (!empty($existing['photo_stored_name'])) {
    $oldPath = $uploadDir . $existing['photo_stored_name'];

    if (is_file($oldPath)) {
        unlink($oldPath);
    }
}

header('Location: ' . $redirectTo);
exit;
