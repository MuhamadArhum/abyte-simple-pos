-- ==========================================================================
--  Simple POS — database schema (MySQL 8.x)
--  Run:  mysql -u root -p < database/schema.sql
-- ==========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `abyte-simple-pos`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `abyte-simple-pos`;

-- ------------------------------------------------------------------ users
CREATE TABLE IF NOT EXISTS `users` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(100) NOT NULL,
  `email`        VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at`   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ------------------------------------------------------------- menu_items
CREATE TABLE IF NOT EXISTS `menu_items` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sku`        VARCHAR(50) DEFAULT NULL,
  `name`       VARCHAR(150) NOT NULL,
  `category`   VARCHAR(80) NOT NULL DEFAULT 'General',
  `price`      DECIMAL(10,2) NOT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_menu_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------------- shifts
CREATE TABLE IF NOT EXISTS `shifts` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED DEFAULT NULL,
  `cashier_name`  VARCHAR(100) NOT NULL,
  `opened_at`     DATETIME NOT NULL,
  `closed_at`     DATETIME DEFAULT NULL,
  `opening_cash`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `closing_cash`  DECIMAL(10,2) DEFAULT NULL,
  `status`        ENUM('OPEN','CLOSED') NOT NULL DEFAULT 'OPEN',
  PRIMARY KEY (`id`),
  KEY `idx_shifts_status` (`status`),
  KEY `fk_shifts_user` (`user_id`),
  CONSTRAINT `fk_shifts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------------- orders
CREATE TABLE IF NOT EXISTS `orders` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shift_id`       INT UNSIGNED DEFAULT NULL,
  `order_no`       VARCHAR(40) NOT NULL,
  `payment_method` ENUM('CASH','CARD','ONLINE') NOT NULL DEFAULT 'CASH',
  `total_amount`   DECIMAL(10,2) NOT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_order_no` (`order_no`),
  KEY `idx_orders_created` (`created_at`),
  KEY `idx_orders_shift` (`shift_id`),
  CONSTRAINT `fk_orders_shift` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ----------------------------------------------------------- order_items
CREATE TABLE IF NOT EXISTS `order_items` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`   INT UNSIGNED NOT NULL,
  `item_id`    INT UNSIGNED DEFAULT NULL,
  `item_name`  VARCHAR(150) NOT NULL,
  `qty`        INT NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `line_total` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_item` (`item_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_item`  FOREIGN KEY (`item_id`)  REFERENCES `menu_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

SET FOREIGN_KEY_CHECKS = 1;
