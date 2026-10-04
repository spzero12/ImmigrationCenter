<?php

require __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/documents.php';

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

    // organization/department — джойн на справочники, с алиасами под старые
    // имена полей (весь остальной код, включая js/employees.js, ждёт именно
    // employee.organization / employee.department как текст).
    // Сортировка по организации в первую очередь — employees.php группирует
    // строки по ней; сотрудники без организации идут первой группой
    // (NULL в MySQL сортируется раньше любого значения).
    $baseSql = 'SELECT e.*, o.name AS organization, d.name AS department, r.name AS region_name
                FROM employees e
                LEFT JOIN regions r ON r.id = e.region_id
                LEFT JOIN organizations o ON o.id = e.organization_id
                LEFT JOIN departments d ON d.id = e.department_id';

    if ($q !== '') {
        $stmt = $db->prepare(
            $baseSql . ' WHERE e.full_name LIKE ? OR e.citizenship LIKE ? OR e.position LIKE ? OR d.name LIKE ? OR EXISTS (SELECT 1 FROM employee_documents p WHERE p.employee_id = e.id AND p.type = \'passport\' AND p.number LIKE ?)
                          ORDER BY o.name IS NULL, o.name, e.full_name'
        );
        $like = "%{$q}%";
        $stmt->execute([$like, $like, $like, $like, $like]);
    } else {
        $stmt = $db->query($baseSql . ' ORDER BY o.name IS NULL, o.name, e.full_name');
    }

    $employees = $stmt->fetchAll();

    // Документы всех выбранных сотрудников одним запросом; для таблицы нужны
    // номер, срок и светофор по каждому типу (один документ на тип).
    $docsByEmployee = [];

    if ($employees) {
        $placeholders = implode(',', array_fill(0, count($employees), '?'));
        $docsStmt = $db->prepare("SELECT employee_id, type, series, number, expire_date FROM employee_documents WHERE employee_id IN ({$placeholders})");
        $docsStmt->execute(array_column($employees, 'id'));

        foreach ($docsStmt->fetchAll() as $doc) {
            $docsByEmployee[$doc['employee_id']][$doc['type']] = [
                'number' => trim(($doc['series'] ?? '') . ($doc['number'] ?? '')),
                'expire_date' => $doc['expire_date'],
                'indicator' => documentIndicator($doc['type'], $doc['expire_date']),
            ];
        }
    }

    foreach ($employees as &$employee) {
        $employee['docs'] = $docsByEmployee[$employee['id']] ?? new stdClass();
        unset($employee['stay_address']);
    }
    unset($employee);

    echo json_encode($employees);
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

    // Файлы сканов удаляемых документов (записи уйдут каскадом) — чистим после удаления.
    $scansStmt = $db->prepare('SELECT scan_stored_name FROM employee_documents WHERE employee_id = ? AND scan_stored_name IS NOT NULL');
    $scansStmt->execute([$id]);
    $scanFiles = $scansStmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $db->prepare('DELETE FROM employees WHERE id = ?');
    $stmt->execute([$id]);

    require_once __DIR__ . '/../includes/scans.php';

    foreach ($scanFiles as $storedName) {
        deleteStoredScan($storedName);
    }

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Метод не поддерживается']);
