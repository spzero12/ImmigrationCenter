<?php
// Подключение к ЛОКАЛЬНОЙ БД для разработки (подхватывается includes/db.php).
// Подставляется через php -d auto_prepend_file=... в scripts/local_dev.ps1,
// боевой config/config.php не трогаем. Секретов тут нет: root без пароля,
// слушает только 127.0.0.1:3307.
putenv('PORTAL_DB_HOST=127.0.0.1');
putenv('PORTAL_DB_PORT=3307');
putenv('PORTAL_DB_NAME=portal');
putenv('PORTAL_DB_USER=root');
putenv('PORTAL_DB_PASS=');
