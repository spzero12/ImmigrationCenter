<?php

// Заглушка API для фичи "Миграционный учёт" (документы и сроки).
// Спецификация: .agent-os/specs/2026-08-20-migration-documents/spec.md
// Реализация — по чек-листу в tasks.md той же папки.

require __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!currentUser()) {
    http_response_code(401);
    echo json_encode(['error' => 'Требуется авторизация']);
    exit;
}

$db = getDb();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // TODO: список документов, фильтры employee_id / status
        // (см. tasks.md — вычисление статуса по expire_date).
        http_response_code(501);
        echo json_encode(['error' => 'Not implemented']);
        break;

    case 'POST':
        // TODO: создание документа сотрудника.
        http_response_code(501);
        echo json_encode(['error' => 'Not implemented']);
        break;

    case 'PUT':
        // TODO: обновление документа.
        http_response_code(501);
        echo json_encode(['error' => 'Not implemented']);
        break;

    case 'DELETE':
        // TODO: удаление документа, роли admin/hr.
        http_response_code(501);
        echo json_encode(['error' => 'Not implemented']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Метод не поддерживается']);
}
