USE `velora_db`;

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

INSERT INTO `hotels`(city, hotel_name, stars, price_per_night, latitude, longitude) VALUES
('Delhi', 'Grand Delhi Inn', 4, 2200.00, 28.644800, 77.216721),
('Delhi', 'Budget Stay', 2, 900.00, 28.640000, 77.220000),
('Kochi', 'Kochi Palace', 5, 3500.00, 9.931233, 76.267303),
('Kochi', 'Cochin Lodge', 3, 1500.00, 9.970000, 76.280000),
('Bengaluru', 'MG Road Comforts', 3, 2099.00, 12.971599, 77.594566),
('Bengaluru', 'Indiranagar Suites', 4, 2899.00, 12.978000, 77.640000),
('Chennai', 'Marina Grand', 4, 2600.00, 13.082680, 80.270718),
('Chennai', 'Budget Inn', 2, 950.00, 13.070000, 80.260000),
('Mumbai', 'Marine Drive Hotel', 5, 4200.00, 18.938771, 72.835335),
('Mumbai', 'Colaba Stay', 3, 1800.00, 18.906700, 72.814700);
