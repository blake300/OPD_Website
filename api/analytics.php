<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/crud.php';

opd_handle_crud('analytics_reports', 'rep', ['name', 'period', 'metric', 'value'], false, ['admin', 'manager'], ['admin']);
