-- import_trains.sql
-- Schema for OSM railway stations (railway=station). Populate from PBF.

CREATE TABLE IF NOT EXISTS railway_stations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255),
  lat DOUBLE,
  lon DOUBLE,
  city VARCHAR(100),
  state VARCHAR(100),
  KEY idx_railway_stations_city (city),
  KEY idx_railway_stations_state (state),
  SPATIAL INDEX idx_railway_stations_point ((POINT(lon,lat)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ingestion notes:
-- Filter: nodes/ways with railway=station
-- Example pipeline (pseudo):
--   osmium tags-filter india-latest.osm.pbf n/railway=station -o railway_stations.osm.pbf
--   osmium export railway_stations.osm.pbf -f geojsonseq -o railway_stations.geojsonl
--   ogr2ogr -f MySQL MYSQL:"velora_db,host=127.0.0.1,user=...,password=..." railway_stations.geojsonl -nln railway_stations_tmp -lco engine=InnoDB
--   INSERT INTO railway_stations(name,lat,lon,city,state)
--     SELECT COALESCE(name,''), ST_Y(geom), ST_X(geom), NULL, NULL FROM railway_stations_tmp;
