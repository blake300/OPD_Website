<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/crud.php';
require_once __DIR__ . '/../src/catalog.php';

opd_handle_crud(
    'products',
    'prod',
    ['name', 'sku', 'imageUrl', 'price', 'status', 'inventory', 'category'],
    true,
    ['admin', 'manager'],
    ['admin'],
    [
        'category' => [
            'required' => true,
            'allowed' => opd_product_categories(),
        ],
    ]
);
