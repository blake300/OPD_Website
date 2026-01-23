<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/crud.php';

opd_handle_crud('settings', 'set', ['key', 'value'], false, ['admin', 'manager'], ['admin']);
