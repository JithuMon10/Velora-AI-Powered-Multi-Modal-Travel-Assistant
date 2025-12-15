-- import_airports.sql
-- Schema for OSM airports (aeroway=aerodrome). Populate from PBF.

CREATE TABLE IF NOT EXISTS airports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255),
  lat DOUBLE,
  lon DOUBLE,
  city VARCHAR(100),
  state VARCHAR(100),
  KEY idx_airports_city (city),
  KEY idx_airports_state (state),
  SPATIAL INDEX idx_airports_point ((POINT(lon,lat)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ingestion notes:
-- Filter: nodes/ways with aeroway=aerodrome
-- Example pipeline (pseudo):
--   osmium tags-filter india-latest.osm.pbf n/aeroway=aerodrome -o airports.osm.pbf
--   osmium export airports.osm.pbf -f geojsonseq -o airports.geojsonl
--   ogr2ogr -f MySQL MYSQL:"velora_db,host=127.0.0.1,user=...,password=..." airports.geojsonl -nln airports_tmp -lco engine=InnoDB
--   INSERT INTO airports(name,lat,lon,city,state)
--     SELECT COALESCE(name,''), ST_Y(geom), ST_X(geom), NULL, NULL FROM airports_tmp;
