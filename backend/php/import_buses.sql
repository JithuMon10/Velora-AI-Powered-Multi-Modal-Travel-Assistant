-- MySQL import for buses (XAMPP)
-- Creates table and loads bus_data.csv into velora_db.buses

USE `velora_db`;

CREATE TABLE IF NOT EXISTS `buses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `state` VARCHAR(100) NOT NULL,
  `operator` VARCHAR(150) NOT NULL,
  `category` VARCHAR(150) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_state` (`state`),
  KEY `idx_operator` (`operator`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET NAMES utf8mb4;
LOAD DATA LOCAL INFILE 'C:\\Users\\jva06\\Desktop\\Velora\\bus_data.csv'
INTO TABLE `buses`
CHARACTER SET utf8mb4
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(`state`, `operator`, `category`);
