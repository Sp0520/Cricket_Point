# Cricket Points Management System (CPMS)

A professional, broadcast-style cricket scoring, live analytics, and fantasy points management platform. This system features an interactive, real-time **3D Cricket Field Simulator** beside a glassmorphism dark-themed scoreboard.

🔗 **Live Link:** [https://cricket-point.onrender.com](https://cricket-point.onrender.com)

---

## 🌟 Key Features

### 1. 3D Live Field Simulator
* **Interactive 3D Arena**: Beautifully detailed 3D stadium built with **Three.js** featuring pitch creases, boundary rope, ad-boards, wickets, and floodlights.
* **Fielder Presets**: Admins can configure fielder setups (**Powerplay**, **Normal**, **Defensive**, **Attacking**, **Custom**). Fielders glide smoothly into new coordinates using **GSAP** transition tweens.
* **Physics-based Animations**: Live-triggered animations for:
  - Bowler run-up and 360-degree arm rotation.
  - White ball parabolic trajectory lines showing release, bounce, impact, and final points (fades in 3s).
  - Shot execution (defends, soft singles, boundaries, and sixes) with fielders chasing the ball and batsmen running.
  - Wickets falling: Stumps shatter and bails fly on bowled-out wickets, accompanied by a flashing red "OUT!" alert.
* **Interactive Cameras**: Five smooth camera views (Broadcast, Bowler, Striker, Top, and Free camera) to view the simulator from different angles.
* **Automatic Over Swaps**: The entire stadium group rotates 180 degrees at the end of each over to simulate the camera switching ends.

### 2. Modern Glassmorphic Scoreboard
* **Real-Time Data**: Polls state every 3s via AJAX `/api/match_state.php` without page reloads.
* **Live Stats**: Displays Team score, Overs, Run Rates (CRR, RRR), Batsmen details, Bowler figures, and Partnership metrics.
* **Timeline**: Interactive ball timeline showing color-coded run badges and a pulsing red **FREE HIT** badge on No Balls.
* **Worm and Run Rate Graphs**: Custom neon statistics drawn by **Chart.js** showing cumulative runs (comparing Innings 1 and Innings 2) and runs scored per over.
* **Live commentary log**: Automatic generator displaying descriptions of match occurrences.

### 3. Admin Live Scoring Panel
* Real-time controls to input runs (0-6), extras (Wide, No Ball, Bye, Leg Bye), and wickets.
* Fielder preset radio buttons and an embedded mini 3D simulation preview.

---

## 🛠️ Technology Stack

* **Frontend**: HTML5, CSS3, JavaScript ES6, Three.js, GSAP, OrbitControls, Chart.js, Bootstrap 5, FontAwesome.
* **Backend**: PHP 8.x, AJAX.
* **Database**: MySQL.
* **Hosting**: Docker, Render.

---

## 📂 Project Directory Structure

```
/
├── admin/
│   └── scoring/
│       ├── setup.php           # Match configuration & setup
│       └── live_scoring.php    # Admin live scoring panel with 3D preview
├── api/
│   ├── ball_entry.php          # Actions handler (runs, wickets, field setups)
│   ├── match_state.php         # Pulls current score, lineups, timeline, & graphs
│   ├── player_stats.php        # Query player stats
│   └── get_team_players.php    # Pulls roster by team ID
├── database/
│   ├── cricket_db.php          # Database helper functions
│   └── points_calculator.php   # Dream11 fantasy points calculator
├── js/
│   └── cricket_field.js        # Reusable Three.js 3D Cricket Field simulator
├── migrations/
│   └── upgrade_20260401_live_scoring.sql # DB setup schema
├── match_scoreboard.php        # Redesigned public glassmorphism scoreboard
├── style.css                   # Custom global CSS styles
├── render.yaml                 # Render infrastructure deployment setup
└── Dockerfile                  # PHP-Apache environment configuration
```

---

## 🚀 Installation & Local Setup

### 1. Database Setup
1. Set up a MySQL database on your local host (e.g. phpMyAdmin / XAMPP).
2. Import the database schema and seed data:
   ```bash
   mysql -u root -p cricket_points < schema.sql
   ```
3. Run any pending migration scripts in the `/migrations/` folder.

### 2. Configure Environment
Create a `.env` file at the root or update `config.php` to define your database credentials:
```env
DB_HOST=127.0.0.1
DB_NAME=cricket_points
DB_USER=root
DB_PASS=
```

### 3. Run Locally (XAMPP)
1. Move the repository folder into your local web root (e.g., `C:\xampp\htdocs\CricketPoints`).
2. Start the Apache and MySQL modules inside the XAMPP Control Panel.
3. Access the Scoreboard page at:
   `http://localhost/CricketPoints/match_scoreboard.php?match_id=1&innings=1`
4. Access the Admin scoring panel (Admin role required) at:
   `http://localhost/CricketPoints/admin/scoring/setup.php`

---

## ☁️ Deployment on Render

This project is fully ready for deployment on **Render** as a web service utilizing Docker:
1. Connect your GitHub repository to Render.
2. Select **Web Service** as the resource type.
3. Render will read the `render.yaml` specification and build the environment automatically using the root `Dockerfile`.
4. Define your `DATABASE_URL` or database credentials in Render's Environment variables.
