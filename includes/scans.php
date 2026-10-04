<?php

// Сохранение скана документа сотрудника (JPG/PNG/PDF до 10 МБ).

const SCAN_MAX_SIZE = 10 * 1024 * 1024;

const SCAN_ALLOWED_MIME_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'application/pdf' => 'pdf',
];

// Принимает элемент из $_FILES; возвращает [данные для БД|null, ошибка|null].
function storeUploadedScan(array $file): array
{
    if ($file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
        return [null, 'Не удалось загрузить скан'];
    }

    if ($file['size'] > SCAN_MAX_SIZE) {
        return [null, 'Скан больше 10 МБ'];
    }

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);

    if (!isset(SCAN_ALLOWED_MIME_TYPES[$mimeType])) {
        return [null, 'Скан: допустимы только JPG, PNG или PDF'];
    }

    $uploadDir = __DIR__ . '/../uploads/documents/';

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        return [null, 'Не удалось сохранить скан на сервере'];
    }

    $storedName = bin2hex(random_bytes(16)) . '.' . SCAN_ALLOWED_MIME_TYPES[$mimeType];

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $storedName)) {
        return [null, 'Не удалось сохранить скан на сервере'];
    }

    return [[
        'scan_stored_name' => $storedName,
        'scan_original_name' => $file['name'],
        'scan_mime_type' => $mimeType,
        'scan_size' => $file['size'],
    ], null];
}

function deleteStoredScan(?string $storedName): void
{
    if ($storedName) {
        $path = __DIR__ . '/../uploads/documents/' . $storedName;

        if (is_file($path)) {
            unlink($path);
        }
    }
}
