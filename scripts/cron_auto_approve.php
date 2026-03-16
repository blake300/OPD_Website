<?php

declare(strict_types=1);

// Auto-approve orders that have been waiting longer than the configured timeout.
// Run via cron every 3-5 minutes:
//   */3 * * * * /usr/bin/php /path/to/scripts/cron_auto_approve.php >> /path/to/logs/cron.log 2>&1

require __DIR__ . '/../src/db_conn.php';
require __DIR__ . '/../src/store.php';

site_ensure_approval_columns(opd_db());

$count = site_process_auto_approvals();
echo '[' . gmdate('Y-m-d H:i:s') . '] Auto-approved ' . $count . ' order(s).' . PHP_EOL;
