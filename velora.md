Velora – Complete Project Guide
===============================

> **Velora** is a student-built, AI‑assisted multimodal transit planner prototype for India.  
> Stack: PHP (backend), MySQL, HTML/CSS/JavaScript (frontend), Leaflet, and GraphHopper (routing over OSM).

---

## 1. What Velora Is

- **Purpose**
  - Help a user plan **end‑to‑end journeys across India** using combinations of buses, trains, driving, and walking.
  - Provide a **Google‑Maps‑like UI** with search cards, an interactive map, and detailed route summaries.
  - Demonstrate how to integrate **open‑source routing (GraphHopper)**, **open map data (OSM)**, and a **PHP+MySQL backend**.

- **Core Capabilities**
  - Search for stops and locations with autocomplete.
  - Plan routes between two stops or free‑text locations.
  - Show routes on a map with polyline overlays and step‑by‑step details.
  - Estimate timing and fares from stored schedules or heuristics.
  - Optionally suggest hotels near the destination (prototype level).

- **Non‑Goals (Current Prototype Limitations)**
  - Not a production booking system.
  - Not a full GTFS integration.
  - Limited to demo datasets (sample buses/stops, India OSM graph via GraphHopper).

---

## 2. High‑Level Architecture

Velora is split into four main layers:

1. **Frontend (Browser)**
   - HTML pages (`transit.html`, `index.html`) + JS (`app.js`, `transit.js`) + CSS (inline/styles).
   - Uses **Leaflet** to display a slippy map.
   - Talks to:
     - **PHP backend APIs** on `localhost:9000`.
     - **GraphHopper routing server** on `localhost:8989` for road routes.

2. **Backend (PHP)**
   - Located at `backend/php/`.
   - Exposes REST‑style endpoints like `get_stops.php`, `plan_trip.php`, `get_buses.php`, `get_locations.php`.
   - Connects to **MySQL** via PDO using credentials in `config.php`.

3. **Database (MySQL)**
   - Schema defined in `backend/php/schema.sql` (+ `setup.sql` helpers).
   - Contains tables for:
     - **Stops** (bus/train/metro stop metadata).
     - **Routes/Trips** (prototype schedules).
     - **Buses** (imported from `bus_data.csv` via `import_buses.sql`).
     - (Optionally) hotels or saved locations.

4. **Routing Engine (GraphHopper)**
   - Cloned under `graphhopper/`.
   - Uses **India OSM extract** (`india-latest.osm.pbf`) to build `graphhopper/india-gh/`.
   - Runs an HTTP server at `http://localhost:8989` that Velora calls for road‑based routes and geocoding.

The data flow:

1. User opens `transit.html` in a browser (served by PHP dev server).
2. JS calls `get_stops.php` / `get_buses.php` to populate dropdowns/autocomplete.
3. When planning a trip:
   - If using **stop‑based mode**, `plan_trip.php` queries MySQL and returns an itinerary.
   - If using **free‑text mode**, frontend geocodes using GraphHopper and calls its `/route` API.
4. Frontend draws route lines and cards on the Leaflet map.

---

## 3. Repository Layout (Essentials)

Root: `c:\Users\jva06\Desktop\Velora\`

- **Backend**
  - `backend/php/config.php` – database connection (`host`, `user`, `pass`, `db`).
  - `backend/php/get_locations.php` – GET saved map markers.
  - `backend/php/save_location.php` – POST to save markers.
  - `backend/php/get_buses.php` – GET bus metadata (from CSV/DB).
  - `backend/php/get_stops.php` – GET transit stops.
  - `backend/php/plan_trip.php` – core trip planner endpoint.
  - `backend/php/schema.sql` – MySQL schema.
  - `backend/php/setup.sql` – helper DDL/DML setup.
  - `backend/php/import_buses.sql` – import `bus_data.csv`.
  - `backend/php/test_connection.php` – quick PDO connectivity JSON check.
  - `backend/php/frontend/`
    - `transit.html` – main Velora transit UI (side panel + map + comparison).
    - `transit.js` – JS logic for Velora transit UI.
    - `index.html` / `app.js` / `api.config.js` – earlier / alternative PHP‑served map demo.

- **Frontend (standalone)**
  - `frontend/index.html` – Leaflet + GraphHopper demo UI.
  - `frontend/app.js` – main JS for standalone frontend.
  - `frontend/api.config.js` – base URL for GraphHopper & PHP API.

- **Data**
  - `bus_data.csv` – bus operators/types input file (for import).

- **Routing / GraphHopper**
  - `graphhopper/` – upstream GraphHopper codebase (Maven+Java).
  - `graphhopper/india-gh/` – built graph for India (OSM → graph).
  - `india-latest.osm.pbf` – India OSM extract in root.

---

## 4. How Velora Is Built

### 4.1 Backend (PHP + MySQL)

- **Language & Runtime**
  - PHP 7+/8 (bundled with XAMPP).
  - Uses **PDO** for MySQL interaction and prepared statements for safety.

- **Configuration (`config.php`)**
  - Stores:
    - `DB_HOST` (e.g. `127.0.0.1`),
    - `DB_NAME` (`velora_db`),
    - `DB_USER` (`velora_user`),
    - `DB_PASS` (strong generated password).
  - Exposes a function or global to create a PDO instance.

- **Database Schema (Overview)**
  - *Stops Table*: basic schema similar to:
    - `id`, `name`, `operator`, `city`, `lat`, `lng`.
  - *Routes/Trips*: e.g.
    - `route_id`, `operator_id`, `origin_stop_id`, `dest_stop_id`, `departure_time`, `arrival_time`, `base_fare`.
  - *Buses* (from `bus_data.csv`):
    - `state`, `operator`, `category`, additional metadata columns.

- **Endpoints and Their Responsibilities**
  - `get_stops.php`
    - Method: `GET`.
    - Returns JSON: list of all stops, used for autocomplete and map markers.
  - `get_buses.php`
    - Method: `GET`.
    - Returns JSON: bus operators/types that can be used for filters or informational panels.
  - `plan_trip.php`
    - Method: `GET`.
    - Inputs (query params, typical): `origin_id`, `dest_id`, optionally `arrive_by`.
    - Logic:
      - Look up origin and destination stops.
      - Identify matching routes/trips in `velora_db`.
      - Approximate timings and fares.
      - Compose a JSON structure: `legs[]`, `summary`, `operator`, times, fares, and ordered stop list.
  - `get_locations.php` / `save_location.php`
    - Let the frontend persist custom map markers (e.g. for favourites).
  - `test_connection.php`
    - Tests DB connectivity and returns JSON result (success/error message).

### 4.2 Frontend (Transit UI)

- **Technology Stack**
  - HTML5 + CSS3 (modern responsive layout).
  - Vanilla JavaScript (no heavy framework).
  - **Leaflet** for:
    - Map tiles (OSM).
    - Marker rendering.
    - Polyline drawing for routes.

- **Key UX Elements in `transit.html`**
  - **Side search panel**
    - Origin & destination inputs with autocomplete (backed by `get_stops.php`).
    - Mode filters chips (bus/train/drive/etc.).
    - Date/time and optional advanced filters.
    - A primary **“Plan Trip”** button.
  - **Map container**
    - Full‑height map next to side panel.
    - Route overlays and markers.
    - Popups with basic stop and leg info.
  - **Comparison panel (“Your Route Options”)**
    - Slides in once routes are available.
    - Lists multiple route options with:
      - Duration, distance, cost.
      - Mode breakdown (e.g. bus + train).
      - Actions like “Choose this route”.
  - **Micro‑interactions**
    - Adjusted color theme (blue‑teal gradients).
    - Floating cards, subtle shadows, disabled state styling, loader overlays.

- **Transit Logic in `transit.js`**
  - Fetches stops and attaches them to inputs (autocomplete).
  - On “Plan Trip”, decides:
    - Use **stop IDs** → call `plan_trip.php`.
    - Use **free‑text** → call GraphHopper geocoding + routing.
  - Parses JSON responses and:
    - Draws routes on the map,
    - Renders textual itineraries,
    - Triggers the comparison UI.

### 4.3 GraphHopper Integration

- **Role of GraphHopper**
  - Provide road network routing (car/walk/bike profiles).
  - Provide geocoding (from place names to coordinates).

- **Server Setup**
  - Built from source under `graphhopper/` with Maven (`mvn clean install -DskipTests`).
  - Uses `india-latest.osm.pbf` as the input.
  - Stores graph in `graphhopper/india-gh/`.
  - Launched with:
    - High memory (`-Xmx12g` recommended).
    - Config file `graphhopper/config-example.yml`.
    - HTTP server at `http://localhost:8989`.

- **Frontend Usage**
  - **Route API**:
    - `GET /route?point=<lat1>,<lon1>&point=<lat2>,<lon2>&profile=car&points_encoded=false`
  - **Geocode API**:
    - `GET /geocode?q=<query>&limit=N`
  - Velora’s JS calls these endpoints when:
    - User enters free‑text city/place names.
    - There is no matching stop in MySQL.

---

## 5. How to Run Velora (Local Dev)

This summarizes `README.txt` in dictionary form.

### 5.1 Prerequisites

- **XAMPP** (MySQL + PHP) on Windows.
- **Java 17+** (Java 21 is OK).
- Optionally, **Maven** (to build GraphHopper).
- `india-latest.osm.pbf` in project root.

### 5.2 Database Setup (MySQL)

1. Start **MySQL** via XAMPP.
2. From PowerShell:
   - Create schema:
     - `schema.sql` is sourced:
       - `C:\xampp\mysql\bin\mysql.exe -u root -e "SOURCE c:/Users/jva06/Desktop/Velora/backend/php/schema.sql"`
   - Create user and grant privileges (both `127.0.0.1` and `localhost`).
3. Verify connection using the command from `README.txt`.
4. (Optional) Import buses:
   - Ensure `bus_data.csv` exists in root.
   - Enable `local_infile` if needed.
   - Run `import_buses.sql` via `--execute` or piping.

### 5.3 Start PHP Backend Server

- Command:
  - `C:\xampp\php\php.exe -S localhost:9000 -t c:\Users\jva06\Desktop\Velora\backend\php`

- Test Endpoints:
  - `http://localhost:9000/test_connection.php`
  - `http://localhost:9000/get_stops.php`
  - `http://localhost:9000/get_buses.php`
  - `http://localhost:9000/frontend/transit.html`

### 5.4 Start GraphHopper Server

- Option A – Prebuilt JAR:
  - Use `graphhopper-web-<version>.jar` + `config-example.yml`.
.  - Configure:
    - `datareader.file = india-latest.osm.pbf`.
    - `graph.location = graphhopper/india-gh`.
  - Launch with large heap (`-Xmx12g`).

- Option B – Build from Source:
  - In `graphhopper/`:
    - `mvn -f pom.xml -DskipTests clean install`
  - JAR is in `graphhopper/web/target/graphhopper-web-*.jar`.
  - Run with `config-example.yml` as in `README.txt`.

- Verify:
  - `http://localhost:8989/route?...` returns JSON.
  - `http://localhost:8989/geocode?...` returns JSON.

### 5.5 Use the Planner

1. Open browser at:
   - `http://localhost:9000/frontend/transit.html`
2. Choose mode:
   - **Stop‑based**:
     - Select from autocompletes (stops from MySQL).
   - **Free‑text**:
     - Type place names; GraphHopper will geocode & route.
3. Submit to plan trip:
   - Watch loader.
   - Map updates with route.
   - Comparison panel shows multiple route candidates when available.

---

## 6. API Dictionary

### 6.1 `GET /get_stops.php`

- **Description**: Returns all stops known to the planner.
- **Use Cases**:
  - Autocomplete for origin/destination.
  - Map marker initialization.

### 6.2 `GET /get_buses.php`

- **Description**: Returns bus operators/types from `velora_db`.
- **Use Cases**:
  - Display metadata (operator name, category).
  - Filtering / analytics in UI.

### 6.3 `GET /plan_trip.php`

- **Description**: Plans a journey between two stop IDs.
- **Typical Input**:
  - `origin_id` – stop ID (int).
  - `dest_id` – stop ID (int).
  - `arrive_by` (optional) – time string (e.g., `18:00:00`).
- **Output**:
  - JSON object:
    - `summary`: duration, distance, cost.
    - `legs[]`: each leg has `mode`, `from`, `to`, `eta`, etc.
    - `stops[]`: ordered stops with coordinates for polyline.

### 6.4 `GET /get_locations.php` & `POST /save_location.php`

- **Description**:
  - Simple persistence for user‑saved locations.
- **Use Cases**:
  - Bookmark favourite stops/places.
  - Show custom markers on map.

### 6.5 `GET /test_connection.php`

- **Description**:
  - Health check for DB connectivity (PDO).
- **Output**:
  - JSON with `status` and message (success or diagnostic error).

---

## 7. UI Modes and Features

### 7.1 Modes

- **Stop‑Based Routing**
  - For known bus/train stops in the database.
  - Provides structured, schedule‑like results.

- **Free‑Text Routing**
  - For arbitrary city/village/address names.
  - Uses GraphHopper geocoding and routing heuristics.

### 7.2 Comparison UI

- Slides from the left/right (depending on layout tweaks).
- Shows:
  - Number of feasible routes found.
  - Summary cards for each route:
    - Duration, cost, changes, reliability hints.
  - “Choose” or “Start navigation” actions (prototype).

### 7.3 Visual Style

- Modern, blue‑teal gradient header and CTA buttons.
- Rounded cards with drop shadows.
- Responsive layout with media queries:
  - Desktop: side panel + map + comparison panel.
  - Tablet/mobile: stacked panels and re‑flowed controls.

---

## 8. Troubleshooting Quick Reference

- **MySQL “Access denied”**
  - Ensure `velora_user` exists for both `localhost` and `127.0.0.1`.
  - Re‑run the user and grant commands from `README.txt`.

- **Frontend shows “cannot connect to DB”**
  - Check XAMPP MySQL is running.
  - Check `config.php` credentials.
  - Hit `test_connection.php` directly.

- **GraphHopper not responding**
  - Confirm Java/Maven installed.
  - Check console for memory errors.
  - Verify server is on `8989` and OSM PBF path is correct.

- **PowerShell errors with `<` redirection**
  - Use `--execute` or pipe `Get-Content` into `mysql.exe`.

- **No stops visible / empty lists**
  - Confirm `schema.sql` executed successfully.
  - Confirm data inserted (via `import_buses.sql` and any stop‑insert scripts).

---

## 9. Extensibility Ideas

- **Full GTFS Support**
  - Import official bus/train GTFS feeds.
  - Replace or extend custom schema with time‑expanded transit graphs.

- **More Modes**
  - Metro, ferry, ride‑share, bicycle.

- **Real‑Time Data**
  - Integrate live APIs (e.g., delays, crowding, weather).

- **Mobile‑First UI**
  - Responsive re‑design for handhelds.
  - PWA / hybrid app wrapper.

- **AI Enhancements**
  - Natural language trip queries (“Plan a cheap overnight trip from Kochi to Delhi next Friday”).
  - Personalized suggestions and rerouting.

---

## 10. Glossary (Velora Terms)

- **Stop**: A physical boarding/alighting point (bus stand, station, etc.), stored in MySQL.
- **Route/Trip**: A scheduled service joining multiple stops (prototype level).
- **Leg**: A contiguous stretch of travel (e.g., one bus ride or one drive).
- **Itinerary**: Full end‑to‑end plan, possibly composed of multiple legs.
- **Profile** (GraphHopper): A mode configuration (e.g., `car`, `bike`) determining speed, access, and routing.
- **Polyline**: Series of map coordinates representing the route line on Leaflet.
- **Free‑Text Mode**: Planning starting from arbitrary typed addresses instead of strictly DB stops.
- **Comparison Panel**: UI area titled “Your Route Options” listing all found itineraries.
- **Backend API**: PHP scripts returning JSON to the frontend.
- **Routing Engine**: GraphHopper server computing shortest/fastest paths over OSM data.

---

This `velora.md` is intended as the **single‑source dictionary and onboarding guide** for the Velora project.  
It should be the first file new contributors read after cloning the repository.


