USE `velora_db`;

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

INSERT INTO `trains`(train_name, operator_name, origin_city, dest_city, departure_time, arrival_time, base_fare) VALUES
('Rajdhani Express', 'IRCTC', 'Delhi', 'Mumbai', '08:00:00', '20:00:00', 2500.00),
('Shatabdi Express', 'IRCTC', 'Chennai', 'Bengaluru', '07:30:00', '12:00:00', 1200.00),
('Kerala Express', 'IRCTC', 'Kochi', 'Trivandrum', '09:00:00', '13:00:00', 800.00);
