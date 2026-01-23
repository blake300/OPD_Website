<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/crud.php';

opd_handle_crud('promotions', 'promo', ['code', 'type', 'value', 'status', 'startsAt', 'endsAt', 'usageLimit', 'used'], false, ['admin', 'manager'], ['admin']);
