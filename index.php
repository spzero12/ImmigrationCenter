<?php

require __DIR__ . '/includes/auth.php';

header('Location: ' . (currentUser() ? 'employees.php' : 'login.php'));
exit;
