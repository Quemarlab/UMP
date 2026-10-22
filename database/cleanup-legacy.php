<?php
declare(strict_types=1);

/**
 * Remove every table the application does not use (the IMC-era tables and anything else that
 * ended up in the database), keeping only the tables defined in database/schema.sql.
 *
 *   php database/cleanup-legacy.php                    dry run: list what would be dropped
 *   php database/cleanup-legacy.php --confirm          back the tables up to storage/backups/, then drop them
 *   php database/cleanup-legacy.php --confirm --keep=data,settings   protect tables you still need
 *
 * The backup is a normal SQL file: import it to restore anything that was dropped.
 * Run the legacy data migration first (php database/install.php --migrate-legacy) if you still want that data.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../app/bootstrap.php';

use UMP\Core\Backup;
use UMP\Core\DB;
use UMP\Core\Env;

$opts = getopt('', ['confirm', 'keep::']);
$keep = array_filter(array_map('trim', explode(',', (string) ($opts['keep'] ?? ''))));

$missing = array_diff(Backup::schemaTables(), Backup::allTables());
if ($missing) {
    fwrite(STDERR, "Refusing to continue: application tables are missing (" . implode(', ', $missing) . ").\nRun  php database/install.php  first.\n");
    exit(1);
}

$drop = array_values(array_diff(Backup::legacyTables(), $keep));
$db = Env::get('DB_NAME', 'ump');

if (!$drop) {
    echo "✔ Nothing to clean: database `$db` only contains the application tables.\n";
    exit(0);
}

echo "Database `$db` — application tables kept: " . implode(', ', Backup::schemaTables()) . "\n\n";
echo "Tables that will be dropped:\n";
foreach ($drop as $t) {
    $rows = (int) DB::val('SELECT COUNT(*) FROM `' . str_replace('`', '', $t) . '`');
    printf("  - %-28s %d rows\n", $t, $rows);
}

if (!isset($opts['confirm'])) {
    echo "\nDry run only. Re-run with --confirm to back these up and drop them.\n";
    exit(0);
}

$dir = __DIR__ . '/../storage/backups';
is_dir($dir) || mkdir($dir, 0775, true);
$file = $dir . '/legacy-tables-' . date('Ymd-His') . '.sql';
$fh = fopen($file, 'wb');
Backup::dump($fh, $drop);
fclose($fh);
if (!is_file($file) || filesize($file) < 50) {
    fwrite(STDERR, "Backup failed; nothing was dropped.\n");
    exit(1);
}
echo "\n✔ Backup written: " . realpath($file) . ' (' . round(filesize($file) / 1024) . " KB)\n";

DB::pdo()->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ($drop as $t) {
    DB::pdo()->exec('DROP TABLE `' . str_replace('`', '', $t) . '`');
    echo "  dropped $t\n";
}
DB::pdo()->exec('SET FOREIGN_KEY_CHECKS=1');
echo "✔ Done. Remaining tables: " . implode(', ', Backup::allTables()) . "\n";
