-- Schema per table site_keys
CREATE TABLE IF NOT EXISTS `site_keys` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_url` VARCHAR(255) NOT NULL,
  `name` VARCHAR(191) DEFAULT NULL,
  `api_key` VARCHAR(255) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `providers` JSON DEFAULT NULL,
  `active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_site_url` (`site_url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users table for admin users
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(191) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `name` VARCHAR(191) DEFAULT NULL,
  `failed_attempts` INT DEFAULT 0,
  `last_failed_at` DATETIME DEFAULT NULL,
  `locked_until` DATETIME DEFAULT NULL,
  `is_admin` TINYINT(1) DEFAULT 1,
  `reset_token` VARCHAR(255) DEFAULT NULL,
  `reset_expires_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Logs table for audit
CREATE TABLE IF NOT EXISTS `logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `site_key_id` INT UNSIGNED DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `source` VARCHAR(255) DEFAULT NULL,
  `provider` VARCHAR(100) DEFAULT NULL,
  `action` VARCHAR(100) DEFAULT NULL,
  `payload` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_logs_created_at` (`created_at`),
  INDEX `idx_logs_user_id` (`user_id`),
  INDEX `idx_logs_site_key_id` (`site_key_id`),
  INDEX `idx_logs_ip` (`ip`),
  CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_logs_site_key` FOREIGN KEY (`site_key_id`) REFERENCES `site_keys`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
