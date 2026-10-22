<?php
declare(strict_types=1);

use UMP\Core\Migrator;

/** Two-factor authentication columns, password age, and an `app` tag on the shared activity log. */
return function (PDO $pdo): void {
    $add = [
        'totp_secret'         => 'VARCHAR(64) NULL',
        'totp_enabled_at'     => 'DATETIME NULL',
        'totp_last_step'      => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'recovery_codes'      => 'TEXT NULL',
        'password_changed_at' => 'DATETIME NULL',
    ];
    foreach ($add as $col => $def) {
        if (!Migrator::hasColumn('users', $col)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN `$col` $def");
        }
    }
    if (!Migrator::hasColumn('audit_logs', 'app')) {
        $pdo->exec("ALTER TABLE audit_logs ADD COLUMN `app` VARCHAR(20) NOT NULL DEFAULT 'site' AFTER `id`");
        $pdo->exec('ALTER TABLE audit_logs ADD KEY `idx_audit_app` (`app`, `id`)');
    }
};
