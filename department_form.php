<?php

require __DIR__ . '/includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Метод не поддерживается');
}

$db = getDb();

$organizationId = (int) ($_POST['organization_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$id = isset($_POST['id']) ? (int) $_POST['id'] : null;

$redirectTo = 'organization_form.php?id=' . $organizationId;

if ($organizationId <= 0) {
    http_response_code(400);
    exit('Не указана организация');
}

$orgStmt = $db->prepare('SELECT id FROM organizations WHERE id = ?');
$orgStmt->execute([$organizationId]);

if (!$orgStmt->fetch()) {
    http_response_code(404);
    exit('Организация не найдена');
}

if ($name === '') {
    // Пустое название при переименовании — просто игнорируем правку,
    // при добавлении нового — поле и так required на форме.
    header('Location: ' . $redirectTo);
    exit;
}

if ($id) {
    $stmt = $db->prepare('UPDATE departments SET name = ? WHERE id = ? AND organization_id = ?');
    $stmt->execute([$name, $id, $organizationId]);
} else {
    $dupStmt = $db->prepare('SELECT id FROM departments WHERE organization_id = ? AND name = ?');
    $dupStmt->execute([$organizationId, $name]);

    if (!$dupStmt->fetch()) {
        $stmt = $db->prepare('INSERT INTO departments (organization_id, name) VALUES (?, ?)');
        $stmt->execute([$organizationId, $name]);
    }
}

header('Location: ' . $redirectTo);
exit;
