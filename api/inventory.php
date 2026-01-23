<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/crud.php';

opd_handle_crud('inventory', 'inv', ['sku', 'location', 'onHand', 'reserved', 'available', 'reorderPoint'], false, ['admin', 'manager'], ['admin']);
