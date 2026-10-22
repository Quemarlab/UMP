<?php
declare(strict_types=1);

/**
 * Scheduled tasks. Run every hour from cron (cPanel → Cron Jobs):
 *   0 * * * *  /usr/local/bin/php /home/USER/public_html/ump/bin/cron.php
 * Sends the daily summary, low-stock alerts and "no record yet" reminders (each at most once a day).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../app/bootstrap.php';

foreach (UMP\Core\Notifier::run() as $line) {
    echo date('c') . '  ' . $line . "\n";
}