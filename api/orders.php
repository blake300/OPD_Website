<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/crud.php';

opd_handle_crud(
    'orders',
    'ord',
    ['number', 'status', 'customerName', 'total', 'currency', 'paymentStatus', 'fulfillmentStatus'],
    true,
    ['admin', 'manager'],
    ['admin']
);
