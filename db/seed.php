<?php

// Создаёт первого администратора для входа в систему.
// Запуск: php db/seed.php

require __DIR__ . '/../includes/db.php';

$username = 'admin';
$password = 'admin123';
$fullName = 'Администратор';

$db = getDb();

$stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
$stmt->execute([$username]);

if ($stmt->fetch()) {
    echo "Пользователь '$username' уже существует.\n";
    exit;
}

$stmt = $db->prepare(
    'INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)'
);
$stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullName, 'admin']);

echo "Создан пользователь: {$username} / {$password} (роль: admin)\n";
