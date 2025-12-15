-- import_bus_stations.sql
-- Schema for OSM bus stations (amenity=bus_station). Populate from PBF.

CREATE TABLE IF NOT EXISTS bus_stations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255),
  lat DOUBLE,
  lon DOUBLE,
  city VARCHAR(100),
  state VARCHAR(100),
  KEY idx_bus_stations_city (city),
  KEY idx_bus_stations_state (state),
  SPATIAL INDEX idx_bus_stations_point ((POINT(lon,lat)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ingestion notes:
-- Filter: nodes/ways with amenity=bus_station
-- Example pipeline (pseudo):
--   osmium tags-filter india-latest.osm.pbf n/amenity=bus_station -o bus_stations.osm.pbf
--   osmium export bus_stations.osm.pbf -f geojsonseq -o bus_stations.geojsonl
--   ogr2ogr -f MySQL MYSQL:"velora_db,host=127.0.0.1,user=...,password=..." bus_stations.geojsonl -nln bus_stations_tmp -lco engine=InnoDB
--   INSERT INTO bus_stations(name,lat,lon,city,state)
--     SELECT COALESCE(name,''), ST_Y(geom), ST_X(geom), NULL, NULL FROM bus_stations_tmp;
-- Add city/state via reverse geocoding or preprocessed tags if available.
