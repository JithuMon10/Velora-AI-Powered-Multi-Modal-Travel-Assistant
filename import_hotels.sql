-- import_hotels.sql
-- Schema for OSM-based hotels. Populate using your OSM pipeline (osmium/ogr2ogr/osm2pgsql).
-- Expected fields: name, lat, lon, city (optional), price_per_night (synthetic ₹1500–₹5000 assigned later by app or via SQL below).

CREATE TABLE IF NOT EXISTS hotels (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255),
  lat DOUBLE,
  lon DOUBLE,
  city VARCHAR(100),
  price_per_night INT,
  KEY idx_hotels_city (city),
  SPATIAL INDEX idx_hotels_point ((POINT(lon,lat)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional helper to synthesize prices if NULL (₹1500–₹5000)
-- UPDATE hotels SET price_per_night = FLOOR(1500 + RAND()*3501) WHERE price_per_night IS NULL;

-- Example ingestion notes (choose one external tool):
-- 1) osmium export india-latest.osm.pbf -f geojsonseq --overwrite -o hotels.geojsonl \
--    -c osmium-hotels.json
--    (where osmium-hotels.json filters nodes with tourism=hotel or tourism=guest_house)
-- 2) ogr2ogr -f MySQL MYSQL:"velora_db,host=127.0.0.1,user=...,password=..." hotels.geojsonl \
--    -nln hotels_tmp -lco engine=InnoDB
--    Then: INSERT INTO hotels(name,lat,lon,city) SELECT name, ST_Y(geom), ST_X(geom), NULL FROM hotels_tmp;
-- Clean up temp tables as needed.
