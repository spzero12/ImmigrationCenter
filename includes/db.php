<?php

function getDb(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $config = require __DIR__ . '/../config/config.php';
        $db = $config['db'];

        // Для локальной разработки подключение можно переопределить
        // переменными окружения, не трогая config/config.php (в нём боевые
        // данные): PORTAL_DB_HOST/PORT/NAME/USER/PASS.
        $host = getenv('PORTAL_DB_HOST') ?: $db['host'];
        $port = getenv('PORTAL_DB_PORT');
        $name = getenv('PORTAL_DB_NAME') ?: $db['name'];
        $user = getenv('PORTAL_DB_USER') !== false ? getenv('PORTAL_DB_USER') : $db['user'];
        $pass = getenv('PORTAL_DB_PASS') !== false ? getenv('PORTAL_DB_PASS') : $db['pass'];

        $dsn = "mysql:host={$host};" . ($port ? "port={$port};" : '') . "dbname={$name};charset={$db['charset']}";

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    return $pdo;
}
