<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/crud.php';

opd_handle_crud('content_pages', 'page', ['title', 'slug', 'status'], false, ['admin', 'manager'], ['admin']);
