<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/crud.php';

opd_handle_crud('payments', 'pay', ['orderId', 'method', 'amount', 'status', 'capturedAt'], false, ['admin', 'manager'], ['admin']);
