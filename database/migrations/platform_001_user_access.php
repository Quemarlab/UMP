<?php
declare(strict_types=1);

use UMP\Core\Migrator;

/** Per-application roles: a user can have a different role (or none) in the website and in UMP. */
return function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `user_access` (
        `user_id` INT UNSIGNED NOT NULL,
        `app`     VARCHAR(20) NOT NULL,
        `role`    VARCHAR(20) NOT NULL,
        `notify`  TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (`user_id`, `app`),
        CONSTRAINT `fk_access_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Older installs kept a single role on the user row: move it to the website's access entry.
    if (Migrator::hasColumn('users', 'role')) {
        $pdo->exec("INSERT IGNORE INTO user_access (user_id, app, role) SELECT id, 'site', role FROM users");
        $pdo->exec('ALTER TABLE users DROP COLUMN role');
    }
};
