-- Automated setup for Velora backend (MySQL)
-- Creates database, dedicated user, grants, and ensures `locations` table

-- 1) Create database
CREATE DATABASE IF NOT EXISTS `velora_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- 2) Create dedicated user with strong password
-- Note: adjust host as needed (here limited to localhost)
CREATE USER IF NOT EXISTS 'velora_user'@'localhost' IDENTIFIED BY 'pX8uS2mD9qL4-Zr7@Vb1Yc6N';
CREATE USER IF NOT EXISTS 'velora_user'@'127.0.0.1' IDENTIFIED BY 'pX8uS2mD9qL4-Zr7@Vb1Yc6N';

-- 3) Grant privileges
GRANT ALL PRIVILEGES ON `velora_db`.* TO 'velora_user'@'localhost';
GRANT ALL PRIVILEGES ON `velora_db`.* TO 'velora_user'@'127.0.0.1';
FLUSH PRIVILEGES;

-- 4) Ensure table exists
USE `velora_db`;
CREATE TABLE IF NOT EXISTS `locations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `latitude` DOUBLE NOT NULL,
  `longitude` DOUBLE NOT NULL,
  `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_timestamp` (`timestamp`),
  INDEX `idx_lat_lng` (`latitude`, `longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
