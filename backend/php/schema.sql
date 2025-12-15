-- MySQL schema for Velora (XAMPP)
-- Creates database and all core tables

CREATE DATABASE IF NOT EXISTS `velora_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `velora_db`;

-- locations (for save_location.php test and generic usage)
CREATE TABLE IF NOT EXISTS `locations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `latitude` DOUBLE NOT NULL,
  `longitude` DOUBLE NOT NULL,
  `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_timestamp` (`timestamp`),
  KEY `idx_lat_lng` (`latitude`, `longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Multimodal demo tables
-- Train routes between major metros
CREATE TABLE IF NOT EXISTS `train_routes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `operator` VARCHAR(50),
  `origin` VARCHAR(100),
  `destination` VARCHAR(100),
  `departure_time` TIME,
  `arrival_time` TIME,
  `fare` INT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Flight routes between major cities
CREATE TABLE IF NOT EXISTS `flight_routes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `operator` VARCHAR(50),
  `origin` VARCHAR(100),
  `destination` VARCHAR(100),
  `departure_time` TIME,
  `arrival_time` TIME,
  `fare` INT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Demo data (idempotent inserts)
INSERT INTO `train_routes` (`operator`,`origin`,`destination`,`departure_time`,`arrival_time`,`fare`)
SELECT * FROM (
  SELECT 'IRCTC','Bengaluru','Chennai','07:30:00','12:15:00',900 UNION ALL
  SELECT 'IRCTC','Chennai','Bengaluru','16:00:00','20:30:00',900 UNION ALL
  SELECT 'IRCTC','Delhi','Jaipur','08:00:00','11:30:00',650 UNION ALL
  SELECT 'IRCTC','Mumbai','Pune','09:00:00','11:40:00',500
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM train_routes LIMIT 1);

INSERT INTO `flight_routes` (`operator`,`origin`,`destination`,`departure_time`,`arrival_time`,`fare`)
SELECT * FROM (
  SELECT 'IndiGo','Kochi','Delhi','09:30:00','12:15:00',4500 UNION ALL
  SELECT 'Air India','Delhi','Mumbai','10:00:00','11:55:00',5200 UNION ALL
  SELECT 'Vistara','Bengaluru','Kolkata','14:00:00','16:45:00',4800
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM flight_routes LIMIT 1);

-- Traffic cache for AI delay predictions
CREATE TABLE IF NOT EXISTS `traffic_cache` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `city` VARCHAR(100) NOT NULL,
  `weekday_flag` TINYINT(1) NOT NULL,
  `hour_bucket` TINYINT UNSIGNED NOT NULL,
  `distance_km` DECIMAL(8,2) NOT NULL,
  `road_type` VARCHAR(20) NOT NULL,
  `delay_min` INT NOT NULL,
  `severity` ENUM('low','medium','high') NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_city_time` (`city`,`weekday_flag`,`hour_bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- buses (for get_buses.php)
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

-- stops (used by transit planner)
CREATE TABLE IF NOT EXISTS `stops` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stop_name` VARCHAR(255),
  `operator_name` VARCHAR(255),
  `city` VARCHAR(255),
  `latitude` DOUBLE,
  `longitude` DOUBLE,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- routes (operator + name)
CREATE TABLE IF NOT EXISTS `routes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `operator_name` VARCHAR(255),
  `name` VARCHAR(255),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- trips (per route, time + fare)
CREATE TABLE IF NOT EXISTS `trips` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `route_id` INT UNSIGNED,
  `departure_time` TIME,
  `arrival_time` TIME,
  `base_fare` DECIMAL(10,2),
  PRIMARY KEY (`id`),
  KEY `idx_route_id` (`route_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- trip_stops (defines ordered stops along a route)
CREATE TABLE IF NOT EXISTS `trip_stops` (
  `route_id` INT UNSIGNED,
  `stop_id` INT UNSIGNED,
  `stop_sequence` INT UNSIGNED,
  KEY `idx_route_seq` (`route_id`, `stop_sequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- trains (for intercity trains)
CREATE TABLE IF NOT EXISTS `trains` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `train_name` VARCHAR(100) NOT NULL,
  `operator_name` VARCHAR(50) NOT NULL,
  `origin_city` VARCHAR(100) NOT NULL,
  `dest_city` VARCHAR(100) NOT NULL,
  `departure_time` TIME NOT NULL,
  `arrival_time` TIME NOT NULL,
  `base_fare` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- hotels (destination suggestions)
CREATE TABLE IF NOT EXISTS `hotels` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `city` VARCHAR(100) NOT NULL,
  `hotel_name` VARCHAR(100) NOT NULL,
  `stars` INT NOT NULL,
  `price_per_night` DECIMAL(10,2) NOT NULL,
  `latitude` DECIMAL(10,6) NULL,
  `longitude` DECIMAL(10,6) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hotels_city` (`city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
