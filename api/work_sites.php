<?php

require __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!currentUser()) {
    http_response_code(401);
    echo json_encode(['error' => 'Требуется авторизация']);
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

    // У сотрудников
    // work_site_id станет NULL (ON DELETE SET NULL).
    $stmt = $db->prepare('DELETE FROM work_sites WHERE id = ?');
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Метод не поддерживается']);
