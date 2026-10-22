<?php
declare(strict_types=1);

namespace UMP\Core;

/**
 * Tiny forward-only migration runner. Files in database/migrations/*.php each return a callable(\PDO): void;
 * applied ids are recorded in `schema_migrations`. Platform migrations (platform_*) are shared by the website
 * and UMP, so whichever application is installed first applies them and the other skips them.
 */
final class Migrator
{
    /** @return string[] ids applied by this run */
    public static function run(string $dir): array
    {
        $pdo = DB::pdo();
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (id VARCHAR(120) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $done = array_column(DB::all('SELECT id FROM schema_migrations'), 'id');
        $files = glob(rtrim($dir, '/\\') . '/*.php') ?: [];
        sort($files);
        $applied = [];
        foreach ($files as $file) {
            $id = basename($file, '.php');
            if (in_array($id, $done, true)) {
                continue;
            }
            $migration = require $file;
            $migration($pdo);
            DB::run('INSERT INTO schema_migrations (id) VALUES (?)', [$id]);
            $applied[] = $id;
        }
        return $applied;
    }

    public static function hasColumn(string $table, string $column): bool
    {
        return (int) DB::val('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$table, $column]) > 0;
    }

    public static function hasTable(string $table): bool
    {
        return (int) DB::val('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]) > 0;
    }
}
