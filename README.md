# ✨ Velora – AI-Powered Multi-Modal Travel Assistant

```
           __     __          _
__   __  / /__  / /_  ____  (_)___  ____ _
\ \ / / / / _ \/ __ \/ __ \/ / __ \/ __ `/
 \ V / / /  __/ /_/ / / / / / / / / /_/ /
  \_/ /_/\___/_.___/_/ /_/_/_/ /_/\__, /
                                /____/
```

Velora plans 🚍 bus, 🚆 train, ✈️ flight, and 🚕 taxi hops in one intelligent route.  
It plugs AI reasoning + GraphHopper routing + TomTom geocoding into a PHP backend with a sleek JS frontend.

---

## 🚀 Feature Highlights

| Capability | Description |
| --- | --- |
| **Smart Mode Selector** | Auto-picks the best transport combo based on distance & cost heuristics. |
| **GraphHopper Routing** | Precise pathfinding with OSM data + TomTom legs for last-mile taxi legs. |
| **AI Personalization** | `smart_ai.js` learns frequent routes/time windows and nudges suggestions. |
| **Fare & Time Simulator** | Breakdown per leg with live traffic-aware TomTom estimates. |
| **Interactive Transit UI** | Leaflet map overlays, comparison charts, and multi-stop previews. |

---

## 🧱 Tech Stack

- **Backend**: PHP 8+, PDO, custom helpers (`backend/php`)
- **Frontend**: Vanilla JS modules, Leaflet, custom UI components
- **Routing**: GraphHopper (Java, Maven) with bundled configs
- **Data**: MySQL (`velora_db`) + optional SQLite fallback for quick demos
- **AI**: Google Gemini (JSON-only wrapper) for insights & summaries

---

## 🗂️ Project Layout

```
Velora/
├── backend/
│   ├── php/
│   │   ├── plan_trip.php        # Main API controller
│   │   ├── config.php           # Loads .env (no secrets committed)
│   │   ├── helpers/             # DB, TomTom, AI glue code
│   │   └── frontend/            # JS app (app.js, transit.js, smart_ai.js, etc.)
│   └── tests/ (optional local harnesses)
├── graphhopper/                 # Embedded routing engine (Java/Maven)
├── setup/setup.php              # NEW interactive wizard (creates .env, bootstraps DB)
├── .env.example                 # Template for API keys & DB settings
├── Report/                      # Project documentation + flowcharts
└── stations*.csv                # Station datasets (ignored in git by default)
```

---

## 🔐 Zero-Leak Setup (New!)

No API keys or passwords ship in this repo. Follow these steps:

```bash
git clone https://github.com/JithuMon10/Velora-AI-Powered-Multi-Modal-Travel-Assistant
cd velora
php setup/setup.php          # interactive wizard
```

The wizard will:
1. Ask for TomTom + Gemini API keys.
2. Collect MySQL host/user/password (or SQLite path).
3. Generate `.env`.
4. (Optional) Create the database + import `import_all_stations.sql`.

> `.env` is git-ignored. Commit only `.env.example`.

---

## ⚙️ Running the App

```bash
# Terminal 1 – PHP API
php -S localhost:9000 -t backend/php

# Terminal 2 – GraphHopper
cd graphhopper
mvn clean install -DskipTests    # first time only
java -jar web/target/graphhopper-web-*.jar server config-example.yml

# Open in browser
http://localhost:9000/frontend/transit.html
```

Recommended first test: **Mallappally → Kochi** (short bus demo already seeded).

---

## 🛠️ Customization & Extensibility

- **New data sources**: drop CSVs in root + reference via import scripts.
- **Modes**: extend `plan_trip.php` decision tree (`$decision` + legs array).
- **UI Themes**: edit `frontend/styles.css` + component JS for views.
- **Analytics**: hook into `helpers/ai.php` and `smart_ai.js` event emitters.

---

## 🤝 Contributing

1. Fork + clone
2. Run `php setup/setup.php`
3. Create feature branch: `git checkout -b feat/<name>`
4. Open a PR with before/after screenshots or curl logs

Issues / ideas welcome via GitHub Issues.

---

## 📚 Documentation

- `VELORA_MASTER_GUIDE.md` – in-depth architecture
- `velora_abstract.md` – academic abstract with chapter breakdown
- `Report/velora_flowchart.md` – Mermaid diagrams

---

## 🧾 License

📌 Developed as part of the **Velora** college project.  
Reuse for educational purposes is encouraged—credit appreciated!

---

**Built with ❤️ for smarter Indian transit.**
