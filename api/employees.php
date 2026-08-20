<?php

require __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';

header('Content-Type: application/json; charset=utf-8');

if (!currentUser()) {
    http_response_code(401);
    echo json_encode(['error' => 'Требуется авторизация']);
    exit;
}

$db = getDb();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $q = trim($_GET['q'] ?? '');

    if ($q !== '') {
        $stmt = $db->prepare(
            'SELECT * FROM employees WHERE full_name LIKE ? OR citizenship LIKE ? OR position LIKE ? OR department LIKE ? ORDER BY full_name'
        );
        $like = "%{$q}%";
        $stmt->execute([$like, $like, $like, $like]);
    } else {
        $stmt = $db->query('SELECT * FROM employees ORDER BY full_name');
    }

    echo json_encode($stmt->fetchAll());
    exit;
}

if ($method === 'DELETE') {
    if (!in_array(currentUser()['role'], ['admin', 'hr'], true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Недостаточно прав']);
        exit;
    }

    parse_str(file_get_contents('php://input'), $params);
    $id = (int) ($params['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Некорректный id']);
        exit;
    }

    // Снимок full_name нужен до удаления — после него взять неоткуда.
    $nameStmt = $db->prepare('SELECT full_name FROM employees WHERE id = ?');
    $nameStmt->execute([$id]);
    $employee = $nameStmt->fetch();

    if ($employee) {
        logAudit('delete', 'employee', $id, $employee['full_name']);
    }

    $stmt = $db->prepare('DELETE FROM employees WHERE id = ?');
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Метод не поддерживается']);
