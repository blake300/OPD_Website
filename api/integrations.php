<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/crud.php';

opd_handle_crud('integrations', 'int', ['name', 'type', 'status', 'lastSync'], false, ['admin', 'manager'], ['admin']);
