-- Import All Stations from CSV Files
-- Run this to populate your database with 45,000+ stations

USE velora_db;

-- Clear existing data (optional - comment out if you want to keep existing)
-- TRUNCATE TABLE stations;

-- Import Train Stations (9,797 stations)
LOAD DATA LOCAL INFILE 'c:/Users/jva06/Desktop/Velora/stations_train.csv'
INTO TABLE stations
FIELDS TERMINATED BY ','
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS
(id, name, type, lat, lon);

-- Import Bus Stops (34,954 stops)
LOAD DATA LOCAL INFILE 'c:/Users/jva06/Desktop/Velora/stations_bus.csv'
INTO TABLE stations
FIELDS TERMINATED BY ','
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS
(id, name, type, lat, lon);

-- Import Airports
LOAD DATA LOCAL INFILE 'c:/Users/jva06/Desktop/Velora/stations_airport.csv'
INTO TABLE stations
FIELDS TERMINATED BY ','
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS
(id, name, type, lat, lon);

-- Verify import
SELECT 
    type,
    COUNT(*) as total_count,
    COUNT(DISTINCT name) as unique_names,
    MIN(lat) as min_lat,
    MAX(lat) as max_lat,
    MIN(lon) as min_lon,
    MAX(lon) as max_lon
FROM stations
GROUP BY type;

-- Check Kerala stations specifically
SELECT type, COUNT(*) as kerala_count
FROM stations
WHERE lat BETWEEN 8.0 AND 13.0
  AND lon BETWEEN 74.0 AND 77.5
GROUP BY type;

-- Sample data
SELECT * FROM stations LIMIT 10;
