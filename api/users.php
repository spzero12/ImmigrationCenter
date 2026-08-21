<?php

require __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';

header('Content-Type: application/json; charset=utf-8');

if (!currentUser()) {
    http_response_code(401);
    echo json_encode(['error' => 'Требуется авторизация']);
    exit;
}

if (currentUser()['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Недостаточно прав']);
    exit;
}

$db = getDb();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'DELETE') {
    parse_str(file_get_contents('php://input'), $params);
    $id = (int) ($params['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Некорректный id']);
        exit;
    }

    if ($id === (int) currentUser()['id']) {
        http_response_code(400);
        echo json_encode(['error' => 'Нельзя удалить самого себя']);
        exit;
    }

    $stmt = $db->prepare('SELECT username FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if ($user) {
        logAudit('delete', 'user', $id, $user['username']);
    }

    $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Метод не поддерживается']);
