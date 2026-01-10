# <p align="center"><img src="icon.png" width="500" height="500" alt="Velora Logo" /><br />✨ Velora – AI-Powered Multi-Modal Travel Assistant</p>

<p align="center">
  <img src="https://readme-typing-svg.herokuapp.com?font=Fira+Code&weight=600&size=24&pause=1000&color=00FFCC&center=true&vCenter=true&width=650&lines=Smart%20Routes%20for%20Complex%20Journeys;AI%20Assisted%20Routing;Multi-Modal%20Optimization;Built%20with%20200%2B%20Hours%20of%20Dedication" alt="Animated Typing" />
</p>


<p align="center">
  <img src="https://img.shields.io/badge/STATUS-ACTIVE-success?style=for-the-badge" />
  <img src="https://img.shields.io/badge/AI-GOOGLE%20GEMINI-blue?style=for-the-badge&logo=google-gemini&logoColor=white" />
  <img src="https://img.shields.io/badge/ROUTING-GRAPHHOPPER-orange?style=for-the-badge" />
  <img src="https://img.shields.io/badge/EFFORT-200%2B%20HOURS%20INVESTED-ff69b4?style=for-the-badge&logo=d3-dot-js&logoColor=white" />
</p>

<p align="center">
  <strong>Velora is an intelligent transit planner that combines AI, smart routing, and real-world mapping to orchestrate seamless multi-modal trips.</strong>
  <br />
  From bus and train hops to last-mile taxi legs, Velora handles the ity of travel planning in one unified interface.
</p>

---

## 🚀 Feature Highlights (Complex Interactions, Simplified Experience)

| Capability | Description |
| :--- | :--- |
| 🧠 **AI-Driven Mode Selector** | Dynamically chooses optimal transport paths based on real-time factors like time, distance, and cost. |
| 📍 **Precision GraphHopper Routing** | Utilizes advanced OpenStreetMap (OSM) data for highly accurate, granular pathfinding. |
| 🤖 **Personalized AI Suggestions** | Employs Google Gemini for intuitive route suggestions, learning from user patterns. |
| ⏱️ **Real-time Cost & Time Estimates** | Provides immediate, traffic-aware breakdowns for every segment of the journey. |
| 🗺️ **Interactive Map Visualization** | Dynamic Leaflet.js maps offer clear multi-stop route previews and comparisons. |

---

## 🧱 Powering Velora: The Tech Behind the Intelligence

<p align="center">
  <img src="https://skillicons.dev/icons?i=php,js,mysql,java,maven,html,css,firebase,aws&theme=dark" />
</p>

* **Backend:** PHP 8+ (Robust & Secure PDO Architecture)
* **Frontend:** Dynamic Vanilla JS & Leaflet.js (Fluid UI/UX)
* **Router:** GraphHopper (Java/Maven) (High-Performance Routing Engine)
* **AI Engine:** Google Gemini (Intelligent Decision Making)
* **Data Layer:** OpenStreetMap (OSM) + Custom CSV Transit Data

---

## 🔐 Built with a Security-First Mindset

<p align="center">
  <img src="https://img.shields.io/badge/Security-ZERO%20LEAK-red?style=for-the-badge&logo=dependabot&logoColor=white" />
  <img src="https://img.shields.io/badge/Data-ENCRYPTED%20&%20ISOLATED-blueviolet?style=for-the-badge&logo=vault&logoColor=white" />
</p>

* **Zero-Leak Policy:** Critical API keys and secrets are never committed to version control.
* **Secure Provisioning:** Dedicated `setup.php` wizard ensures a safe, local environment setup.
* **Database Integrity:** Utilizes PDO prepared statements to actively prevent SQL Injection vulnerabilities.
* **Isolated Services:** Routing engine operates as a separate, contained microservice.

---

## ⚙️ Quick Deployment & Test Flight

### 1. Rapid Setup (One-Time)
```bash
git clone [https://github.com/JithuMon10/Velora-AI-Powered-Multi-Modal-Travel-Assistant.git](https://github.com/JithuMon10/Velora-AI-Powered-Multi-Modal-Travel-Assistant.git)
cd Velora-AI-Powered-Multi-Modal-Travel-Assistant
php setup/setup.php  # Interactive wizard for API keys & DB configuration
```

### 2. Launch Services (Two Simple Commands)

| Component | Command | Access URL |
| :--- | :--- | :--- |
| **PHP API Backend** | `php -S localhost:9000 -t backend/php` | `http://localhost:9000` |
| **GraphHopper Router** | `java -jar graphhopper/target/graphhopper-web-*.jar server config.yml` | `http://localhost:8989` |

**First Test:** Once running, navigate your browser to the PHP API URL. We recommend testing a route like **Mallappally → Kochi** to witness the multi-modal orchestration.

---

## 📂 Under the Hood: Core Architecture

```text
Velora/
├── backend/php/    # Core API, Trip Planning Logic, AI Integration
├── frontend/       # User Interface, Interactive Maps, Frontend JS Logic
├── graphhopper/    # Self-Contained Java Routing Engine
├── setup/          # Environment Setup & Database Provisioning
├── Report/         # Detailed System Documentation & Flowcharts
└── stations*.csv   # Geographic & Transit Data Sources
```

---

## 🤝 Connect & Collaborate

**Project Lead:** Jithendra V Anand (JithuMon10) – *Cyber Security & Full-Stack Development Expert*

<p align="center">
  <a href="https://www.linkedin.com/in/jithu2006/">
    <img src="https://img.shields.io/badge/LinkedIn-Connect%20with%20Jithu-0077B5?style=for-the-badge&logo=linkedin&logoColor=white" alt="LinkedIn" />
  </a>
  <a href="https://github.com/JithuMon10">
    <img src="https://img.shields.io/badge/GitHub-Follow%20My%20Work-181717?style=for-the-badge&logo=github&logoColor=white" alt="GitHub" />
  </a>
</p>

---

<p align="center">
  <i>"Velora: Turning  travel logistics into a smart, secure, and seamless journey."</i><br>
  <img src="https://komarev.com/ghpvc/?username=JithuMon10&label=PROJECT%20VIEWS&color=00CCFF&style=flat" alt="Project Views" />
</p>
