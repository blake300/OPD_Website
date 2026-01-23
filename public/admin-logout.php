<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

opd_logout();
header('Location: /admin-login.php');
exit;
