<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/site_auth.php';

site_logout();
header('Location: /login.php');
exit;
