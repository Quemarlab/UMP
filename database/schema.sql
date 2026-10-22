-- UMP (Unprocessed Materials Production) schema (MySQL 5.7+/MariaDB 10.4+)
-- Safe to run repeatedly. The platform tables (users, user_access, login_attempts, password_resets, rate_limits,
-- site_settings, audit_logs, schema_migrations) are shared with the website admin and MUST stay identical in both
-- repositories: both applications run against the same database.
-- Install:  php database/install.php   (see that file for options)

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(120) NOT NULL,
  `email`         VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `status`        ENUM('active','disabled') NOT NULL DEFAULT 'active',
  `totp_secret`         VARCHAR(64) NULL,
  `totp_enabled_at`     DATETIME NULL,
  `totp_last_step`      INT UNSIGNED NOT NULL DEFAULT 0,
  `recovery_codes`      TEXT NULL,
  `password_changed_at` DATETIME NULL,
  `last_login_at` DATETIME NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- What each account may do in each application ('site' = website admin, 'ump' = production system).
CREATE TABLE IF NOT EXISTS `user_access` (
  `user_id` INT UNSIGNED NOT NULL,
  `app`     VARCHAR(20) NOT NULL,
  `role`    VARCHAR(20) NOT NULL,
  `notify`  TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`, `app`),
  CONSTRAINT `fk_access_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`      VARCHAR(190) NOT NULL,
  `ip`         VARCHAR(45) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_email` (`email`, `created_at`),
  KEY `idx_attempts_ip` (`ip`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at`    DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_resets_token` (`token_hash`),
  CONSTRAINT `fk_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rate_limits` (
  `bucket`       VARCHAR(120) NOT NULL,
  `hits`         INT UNSIGNED NOT NULL DEFAULT 1,
  `window_start` DATETIME NOT NULL,
  PRIMARY KEY (`bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_settings` (
  `skey`  VARCHAR(60) NOT NULL,
  `value` TEXT NOT NULL,
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `app`        VARCHAR(20) NOT NULL DEFAULT 'site',
  `user_id`    INT UNSIGNED NULL,
  `action`     VARCHAR(60) NOT NULL,
  `entity`     VARCHAR(60) NOT NULL DEFAULT '',
  `entity_id`  INT UNSIGNED NULL,
  `detail`     VARCHAR(255) NOT NULL DEFAULT '',
  `ip`         VARCHAR(45) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_app` (`app`, `id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `id`         VARCHAR(120) NOT NULL,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- UMP
-- One row per calendar day. Quantities are in the unit set in Settings (kg by default).
CREATE TABLE IF NOT EXISTS `stock_records` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `record_date`    DATE NOT NULL,
  `opening`        DECIMAL(14,2) NOT NULL DEFAULT 0,
  `received_day`   DECIMAL(14,2) NOT NULL DEFAULT 0,
  `received_night` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `received_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `issued`         DECIMAL(14,2) NOT NULL DEFAULT 0,
  `closing`        DECIMAL(14,2) NOT NULL DEFAULT 0,
  `grv_day`        VARCHAR(60) NOT NULL DEFAULT '',
  `grv_night`      VARCHAR(60) NOT NULL DEFAULT '',
  `giv`            VARCHAR(60) NOT NULL DEFAULT '',
  `notes`          VARCHAR(500) NOT NULL DEFAULT '',
  `legacy_code`    VARCHAR(40) NULL,
  `created_by`     INT UNSIGNED NULL,
  `updated_by`     INT UNSIGNED NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stock_date` (`record_date`),
  CONSTRAINT `fk_stock_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_stock_updated` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every change to a record keeps the values it replaced and the reason given.
CREATE TABLE IF NOT EXISTS `stock_revisions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `record_id`  INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NULL,
  `action`     ENUM('create','update','rechain') NOT NULL,
  `before_json` TEXT NULL,
  `reason`     VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rev_record` (`record_id`, `created_at`),
  CONSTRAINT `fk_rev_record` FOREIGN KEY (`record_id`) REFERENCES `stock_records` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rev_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scanned delivery notes / GRV / GIV documents attached to a day's record. Files live in storage/uploads (never web-accessible).
CREATE TABLE IF NOT EXISTS `stock_attachments` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `record_id`     INT UNSIGNED NOT NULL,
  `original_name` VARCHAR(190) NOT NULL,
  `stored_name`   CHAR(40) NOT NULL,
  `mime`          VARCHAR(80) NOT NULL,
  `size`          INT UNSIGNED NOT NULL,
  `uploaded_by`   INT UNSIGNED NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attach_stored` (`stored_name`),
  KEY `idx_attach_record` (`record_id`),
  CONSTRAINT `fk_attach_record` FOREIGN KEY (`record_id`) REFERENCES `stock_records` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_attach_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;