<?php
declare(strict_types=1);

namespace UMP\Core;

/** Plain-SQL database dumps (structure + data) that can be restored with the mysql client or phpMyAdmin. */
final class Backup
{
    /** Tables defined by database/schema.sql, in file order. */
    public static function schemaTables(): array
    {
        preg_match_all('/CREATE TABLE IF NOT EXISTS `(\w+)`/', (string) file_get_contents(__DIR__ . '/../../database/schema.sql'), $m);
        return $m[1];
    }

    /** Tables owned by the website that shares this database: never reported as legacy, always backed up. */
    private const SHARED_DB_TABLES = ['services', 'projects', 'posts', 'gallery_items', 'messages', 'subscribers', 'newsletter_campaigns', 'newsletter_deliveries'];

    public static function allTables(): array
    {
        return array_map('current', DB::pdo()->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(\PDO::FETCH_NUM));
    }

    /** Tables present in the database that the application does not use. */
    public static function legacyTables(): array
    {
        return array_values(array_diff(self::allTables(), self::schemaTables(), self::SHARED_DB_TABLES));
    }

    /** Write a dump of $tables to an open stream. */
    public static function dump($out, array $tables): void
    {
        $pdo = DB::pdo();
        fwrite($out, "-- UMP backup · " . date('c') . "\n-- Database: " . Env::get('DB_NAME', 'ump') . "\n\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        foreach ($tables as $table) {
            $table = str_replace('`', '', $table);
            $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(\PDO::FETCH_NUM)[1];
            fwrite($out, "DROP TABLE IF EXISTS `$table`;\n$create;\n\n");

            $rows = $pdo->query("SELECT * FROM `$table`", \PDO::FETCH_NUM);
            $batch = [];
            foreach ($rows as $row) {
                $batch[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row)) . ')';
                if (count($batch) >= 200) {
                    fwrite($out, "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch) {
                fwrite($out, "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n");
            }
            fwrite($out, "\n");
        }
        fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
    }
}
