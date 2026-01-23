<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/crud.php';

opd_handle_crud('customers', 'cust', ['name', 'email', 'phone', 'status', 'ltv', 'tags'], true, ['admin', 'manager'], ['admin']);
