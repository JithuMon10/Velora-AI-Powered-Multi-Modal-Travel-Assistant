CREATE TABLE IF NOT EXISTS stations (
  id BIGINT PRIMARY KEY,
  name VARCHAR(255) NULL,
  type ENUM('train','bus') NOT NULL,
  lat DOUBLE NOT NULL,
  lon DOUBLE NOT NULL,
  KEY idx_type (type),
  KEY idx_latlon (lat, lon)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
