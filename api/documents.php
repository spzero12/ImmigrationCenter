<?php

require __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/documents.php';
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
    $employeeId = isset($_GET['employee_id']) ? (int) $_GET['employee_id'] : null;
    $q = trim($_GET['q'] ?? '');

    $sql = 'SELECT d.*, e.full_name AS employee_name
            FROM employee_documents d
            JOIN employees e ON e.id = d.employee_id
            WHERE 1 = 1';
    $params = [];

    if ($employeeId) {
        $sql .= ' AND d.employee_id = ?';
        $params[] = $employeeId;
    }

    if ($q !== '') {
        $sql .= ' AND e.full_name LIKE ?';
        $params[] = "%{$q}%";
    }

    $sql .= ' ORDER BY d.expire_date IS NULL, d.expire_date ASC, e.full_name';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    $documents = array_map(static function (array $row): array {
        $row['type_label'] = DOCUMENT_TYPE_LABELS[$row['type']] ?? $row['type'];
        $row['status'] = documentStatus($row['expire_date']);

        return $row;
    }, $stmt->fetchAll());

    echo json_encode($documents);
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

    // Снимок для entity_label нужен до удаления, вместе с именем сотрудника.
    $labelStmt = $db->prepare(
        'SELECT d.type, d.number, e.full_name AS employee_name FROM employee_documents d JOIN employees e ON e.id = d.employee_id WHERE d.id = ?'
    );
    $labelStmt->execute([$id]);
    $document = $labelStmt->fetch();

    if ($document) {
        $typeLabel = DOCUMENT_TYPE_LABELS[$document['type']] ?? $document['type'];
        logAudit('delete', 'employee_document', $id, $typeLabel . ' ' . $document['number'] . ' — ' . $document['employee_name']);
    }

    $stmt = $db->prepare('DELETE FROM employee_documents WHERE id = ?');
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Метод не поддерживается']);
