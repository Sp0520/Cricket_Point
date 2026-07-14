<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database/cricket_db.php';

$match_id = (int)($_GET['match_id'] ?? 0);
$innings = (int)($_GET['innings'] ?? 1);

if ($match_id <= 0) {
    die("Invalid match ID");
}

$match = get_match_details($match_id);
if (!$match) {
    die("Match not found");
}

$battingTeamId = (int)$match['batting_team_id'];
$bowlingTeamId = (int)$match['bowling_team_id'];

require_once __DIR__ . '/header.php';
?>

<!-- Include Google Font for premium UI aesthetic -->
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">

<style>
/* Glassmorphism Styles & Theme Adjustments */
body {
    font-family: 'Outfit', sans-serif;
    background-color: #080f12 !important;
    color: #e7f6ee;
}

.scoreboard-title-bar {
    background: linear-gradient(135deg, rgba(21, 209, 122, 0.15), rgba(13, 202, 240, 0.05));
    border: 1px solid rgba(21, 209, 122, 0.2);
    border-radius: 16px;
    backdrop-filter: blur(10px);
}

.glass-card {
    background: rgba(17, 29, 34, 0.45) !important;
    border: 1px solid rgba(255, 255, 255, 0.06) !important;
    backdrop-filter: blur(16px);
    border-radius: 16px !important;
    box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
    transition: transform 0.3s ease, border-color 0.3s ease;
}

.glass-card:hover {
    border-color: rgba(21, 209, 122, 0.2);
}

.neon-green-text {
    color: #15d17a;
    text-shadow: 0 0 10px rgba(21, 209, 122, 0.3);
}

.neon-cyan-text {
    color: #0dcaf0;
    text-shadow: 0 0 10px rgba(13, 202, 240, 0.3);
}

.cp-muted {
    color: #a8c4b6 !important;
}

/* Stat display grids */
.custom-stat-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #a8c4b6;
}

.custom-stat-val {
    font-size: 1.15rem;
    font-weight: 600;
}

/* Player cards */
.player-highlight-card {
    border-left: 4px solid #15d17a !important;
    background: rgba(21, 209, 122, 0.03);
}

.bowler-highlight-card {
    border-left: 4px solid #0dcaf0 !important;
    background: rgba(13, 202, 240, 0.03);
}

/* Ball dots and timeline */
.ball-timeline-container {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-start;
    overflow-x: auto;
    padding: 0.5rem 0;
}

.ball-dot {
    width: 2.2rem;
    height: 2.2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-weight: bold;
    font-size: 0.85rem;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    flex-shrink: 0;
    animation: scaleIn 0.4s ease forwards;
}

@keyframes scaleIn {
    0% { transform: scale(0); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}

.ball-dot.runs-0 { background: rgba(255, 255, 255, 0.1); color: #fff; border: 1px solid rgba(255, 255, 255, 0.2); }
.ball-dot.runs-1, .ball-dot.runs-3 { background: rgba(13, 202, 240, 0.2); color: #0dcaf0; border: 1px solid rgba(13, 202, 240, 0.4); }
.ball-dot.runs-2 { background: rgba(13, 202, 240, 0.35); color: #fff; border: 1px solid rgba(13, 202, 240, 0.6); }
.ball-dot.runs-4 { background: rgba(255, 193, 7, 0.25); color: #ffc107; border: 1px solid rgba(255, 193, 7, 0.5); box-shadow: 0 0 10px rgba(255,193,7,0.3); }
.ball-dot.runs-6 { background: rgba(220, 53, 69, 0.25); color: #ffcbd0; border: 1px solid rgba(220, 53, 69, 0.5); box-shadow: 0 0 10px rgba(220,53,69,0.3); }
.ball-dot.wicket { background: #dc3545; color: #fff; border: 1px solid #ffcbd0; box-shadow: 0 0 15px rgba(220,53,69,0.6); }
.ball-dot.wide, .ball-dot.no-ball { background: rgba(25, 135, 84, 0.25); color: #75b798; border: 1px solid rgba(25, 135, 84, 0.5); }

/* 3D Field Container */
.field3d-container {
    height: 480px;
    width: 100%;
    overflow: hidden;
    position: relative;
    border-radius: 12px;
}

.camera-controls-btn {
    border-radius: 20px !important;
    font-size: 0.75rem !important;
    font-weight: 600;
    letter-spacing: 0.05em;
    padding: 6px 14px !important;
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: #a8c4b6 !important;
    transition: all 0.2s;
}

.camera-controls-btn.active, .camera-controls-btn:hover {
    background: linear-gradient(135deg, #15d17a, #0dcaf0) !important;
    color: #06110b !important;
    border-color: transparent !important;
    box-shadow: 0 0 10px rgba(21, 209, 122, 0.4);
}

/* Accordion Lineup override */
.accordion-item {
    background: transparent !important;
    border: none !important;
}
.accordion-button {
    background: rgba(255,255,255,0.02) !important;
    color: #e7f6ee !important;
    font-size: 0.9rem;
    font-weight: 600;
    border-bottom: 1px solid rgba(255,255,255,0.05) !important;
}
.accordion-button:not(.collapsed) {
    box-shadow: none !important;
    color: #15d17a !important;
}

/* Live commentary log */
.commentary-box {
    max-height: 180px;
    overflow-y: auto;
    font-size: 0.85rem;
    line-height: 1.6;
}

.commentary-item {
    border-left: 2px solid rgba(255, 255, 255, 0.15);
    padding-left: 10px;
    margin-bottom: 8px;
    animation: fadeInCommentary 0.5s ease;
}

@keyframes fadeInCommentary {
    0% { opacity: 0; transform: translateY(-5px); }
    100% { opacity: 1; transform: translateY(0); }
}

.commentary-item.wicket { border-color: #dc3545; background: rgba(220, 53, 69, 0.05); }
.commentary-item.boundary { border-color: #ffc107; background: rgba(255, 193, 7, 0.05); }

/* Graphs Tab override */
.nav-tabs .nav-link {
    color: #a8c4b6;
    border: none;
    font-size: 0.85rem;
    font-weight: 600;
}
.nav-tabs .nav-link.active {
    background: transparent !important;
    color: #15d17a !important;
    border-bottom: 2px solid #15d17a !important;
}

#freeHitBadge {
    animation: freeHitPulse 1s infinite;
}
@keyframes freeHitPulse {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
    70% { transform: scale(1.05); box-shadow: 0 0 0 8px rgba(220, 53, 69, 0); }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
}
</style>

<div class="container-fluid py-3 px-2 px-md-4">
    <!-- Header Title Bar -->
    <div class="row mb-3 g-2 align-items-center p-3 scoreboard-title-bar">
        <div class="col-12 col-md-8">
            <span class="badge bg-success mb-2 cp-badge px-2 py-1">LIVE BROADCAST</span>
            <h2 class="fw-bold mb-0 text-white"><?= h($match['match_name']) ?></h2>
            <p class="small cp-muted mb-0">Venue: <?= h($match['venue'] ?? 'Stadium') ?> | <?= h($match['match_date']) ?></p>
        </div>
        <div class="col-12 col-md-4 text-md-end">
            <div class="d-inline-flex align-items-center gap-2 p-2 bg-dark bg-opacity-50 rounded border border-secondary">
                <span class="small cp-muted">Innings Select:</span>
                <a href="?match_id=<?= $match_id ?>&innings=1" class="btn btn-xs btn-outline-success btn-sm py-0 <?= $innings === 1 ? 'active' : '' ?>">Innings 1</a>
                <a href="?match_id=<?= $match_id ?>&innings=2" class="btn btn-xs btn-outline-success btn-sm py-0 <?= $innings === 2 ? 'active' : '' ?>">Innings 2</a>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row g-3">
        <!-- Left Side: Scoreboard & Stats -->
        <div class="col-12 col-xl-8">
            <div class="row g-3">
                
                <!-- Main Score Card -->
                <div class="col-12">
                    <div class="card glass-card p-4">
                        <div class="row align-items-center">
                            <div class="col-12 col-md-6 mb-3 mb-md-0 border-end border-secondary border-opacity-50">
                                <span class="custom-stat-label">Batting Team</span>
                                <h3 class="fw-bold text-white mb-2" id="battingTeamName">-</h3>
                                <div class="d-flex align-items-baseline gap-2">
                                    <h1 class="display-3 fw-bold neon-green-text mb-0" id="totalRuns">0</h1>
                                    <h3 class="fw-bold text-white mb-0">/ <span id="wickets">0</span></h3>
                                    <span class="badge bg-danger ms-2" id="freeHitBadge" style="display: none; font-size: 0.8rem; font-weight: bold; border: 1px solid #ffcbd0; vertical-align: middle;">FREE HIT</span>
                                </div>
                                <p class="small cp-muted mt-2 mb-0">CRR: <span id="runRate" class="fw-bold text-white">0.00</span></p>
                            </div>
                            
                            <div class="col-12 col-md-6 ps-md-4">
                                <div class="row g-3">
                                    <div class="col-6">
                                        <span class="custom-stat-label">Overs Completed</span>
                                        <div class="custom-stat-val text-white" id="overs">0.0 / 20.0</div>
                                    </div>
                                    <div class="col-6" id="targetContainer" style="display: none;">
                                        <span class="custom-stat-label">Target Score</span>
                                        <div class="custom-stat-val text-warning" id="targetScore">-</div>
                                    </div>
                                    <div class="col-12 mt-2" id="requiredContainer" style="display: none;">
                                        <div class="p-2 rounded bg-warning bg-opacity-10 border border-warning border-opacity-25">
                                            <span class="small text-warning d-block">Required to Win:</span>
                                            <span class="fw-bold text-white small" id="requiredText">Need - runs off - balls (RRR: -)</span>
                                        </div>
                                    </div>
                                    <div class="col-12" id="partnershipContainer">
                                        <span class="custom-stat-label">Current Partnership</span>
                                        <div class="custom-stat-val text-white small" id="partnershipText">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Batsmen Card -->
                <div class="col-12 col-md-6">
                    <div class="card glass-card p-3 h-100">
                        <h6 class="fw-bold mb-3 border-bottom border-secondary border-opacity-50 pb-2 text-success">
                            <i class="fas fa-baseball-bat-ball"></i> Batting stats
                        </h6>
                        <div class="d-flex flex-column gap-3">
                            <!-- Striker -->
                            <div class="p-2 rounded player-highlight-card" id="strikerCard">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-white" id="strikerName">-</span>
                                    <span class="badge bg-success">Strike</span>
                                </div>
                                <div class="row g-2 mt-1">
                                    <div class="col-3"><span class="custom-stat-label d-block">Runs</span><strong class="text-white" id="strikerRuns">0</strong></div>
                                    <div class="col-3"><span class="custom-stat-label d-block">Balls</span><span class="cp-muted" id="strikerBalls">0</span></div>
                                    <div class="col-3"><span class="custom-stat-label d-block">4s/6s</span><span class="cp-muted" id="strikerBoundaries">0/0</span></div>
                                    <div class="col-3"><span class="custom-stat-label d-block">S/R</span><span class="neon-green-text" id="strikerSR">0.0</span></div>
                                </div>
                            </div>
                            <!-- Non-Striker -->
                            <div class="p-2 rounded border border-transparent" id="nonStrikerCard" style="background: rgba(255,255,255,0.01);">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold text-white" id="nonStrikerName">-</span>
                                    <span class="cp-muted small">Batting</span>
                                </div>
                                <div class="row g-2 mt-1">
                                    <div class="col-3"><span class="custom-stat-label d-block">Runs</span><strong class="text-white" id="nonStrikerRuns">0</strong></div>
                                    <div class="col-3"><span class="custom-stat-label d-block">Balls</span><span class="cp-muted" id="nonStrikerBalls">0</span></div>
                                    <div class="col-3"><span class="custom-stat-label d-block">4s/6s</span><span class="cp-muted" id="nonStrikerBoundaries">0/0</span></div>
                                    <div class="col-3"><span class="custom-stat-label d-block">S/R</span><span class="cp-muted" id="nonStrikerSR">0.0</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Bowler Card -->
                <div class="col-12 col-md-6">
                    <div class="card glass-card p-3 h-100">
                        <h6 class="fw-bold mb-3 border-bottom border-secondary border-opacity-50 pb-2 text-info">
                            <i class="fas fa-baseball"></i> Bowling figures
                        </h6>
                        <div class="p-3 rounded bowler-highlight-card" id="bowlerCard">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold text-white" id="bowlerName">-</span>
                                <span class="badge bg-info">Active Bowler</span>
                            </div>
                            <div class="row g-2 mt-2">
                                <div class="col-4 mb-2"><span class="custom-stat-label d-block">Overs</span><strong class="text-white" id="bowlerOvers">0.0</strong></div>
                                <div class="col-4 mb-2"><span class="custom-stat-label d-block">Maidens</span><span class="cp-muted" id="bowlerMaidens">0</span></div>
                                <div class="col-4 mb-2"><span class="custom-stat-label d-block">Runs</span><span class="cp-muted" id="bowlerRunsConceded">0</span></div>
                                <div class="col-4"><span class="custom-stat-label d-block">Wickets</span><strong class="neon-cyan-text" id="bowlerWickets">0</strong></div>
                                <div class="col-4"><span class="custom-stat-label d-block">Econ</span><span class="cp-muted" id="bowlerEconomy">0.00</span></div>
                                <div class="col-4"><span class="custom-stat-label d-block">Fantasy Pts</span><span class="badge bg-dark border border-info border-opacity-50 text-info" id="bowlerFantasy">0</span></div>
                            </div>
                        </div>
                        <div class="mt-2 text-end">
                            <span class="small cp-muted" style="font-size: 11px;">Last Wicket: <span class="text-danger" id="lastWicketText">-</span></span>
                        </div>
                    </div>
                </div>

                <!-- Last 6 Balls Timeline -->
                <div class="col-12">
                    <div class="card glass-card p-3">
                        <h6 class="fw-bold mb-2 custom-stat-label"><i class="fas fa-history"></i> Last 6 Balls Timeline</h6>
                        <div id="ballTimeline" class="ball-timeline-container">
                            <span class="ball-dot runs-0">-</span>
                        </div>
                    </div>
                </div>

                <!-- Commentary & Graph Card -->
                <div class="col-12">
                    <div class="card glass-card p-3">
                        <nav>
                            <div class="nav nav-tabs border-bottom border-secondary border-opacity-25" id="nav-tab" role="tablist">
                                <button class="nav-link active" id="nav-comm-tab" data-bs-toggle="tab" data-bs-target="#nav-comm" type="button" role="tab"><i class="fas fa-comment-alt"></i> Live Commentary</button>
                                <button class="nav-link" id="nav-worm-tab" data-bs-toggle="tab" data-bs-target="#nav-worm" type="button" role="tab"><i class="fas fa-chart-line"></i> Worm Graph</button>
                                <button class="nav-link" id="nav-rate-tab" data-bs-toggle="tab" data-bs-target="#nav-rate" type="button" role="tab"><i class="fas fa-chart-bar"></i> Run Rate Graph</button>
                            </div>
                        </nav>
                        <div class="tab-content mt-3" id="nav-tabContent">
                            <!-- Live Commentary Tab -->
                            <div class="tab-pane fade show active" id="nav-comm" role="tabpanel">
                                <div class="commentary-box pr-2" id="commentaryContainer">
                                    <div class="commentary-item">
                                        <span class="cp-muted text-success">0.1</span>
                                        <strong>Bowler to Batsman:</strong> Match initialized. Waiting for first ball...
                                    </div>
                                </div>
                            </div>
                            <!-- Worm Graph Tab -->
                            <div class="tab-pane fade" id="nav-worm" role="tabpanel">
                                <div style="position: relative; height: 220px; width: 100%;">
                                    <canvas id="wormChart"></canvas>
                                </div>
                            </div>
                            <!-- Run Rate Graph Tab -->
                            <div class="tab-pane fade" id="nav-rate" role="tabpanel">
                                <div style="position: relative; height: 220px; width: 100%;">
                                    <canvas id="runRateChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Right Side: 3D Arena & Lineups -->
        <div class="col-12 col-xl-4">
            <div class="row g-3">
                
                <!-- 3D Arena Box -->
                <div class="col-12">
                    <div class="card glass-card p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0 text-white"><i class="fas fa-vr-cardboard"></i> 3D Live Field Simulator</h6>
                            <span class="badge bg-dark border border-secondary text-white small" id="stadiumPresetBadge" style="font-size: 10px;">Setup: Normal</span>
                        </div>
                        
                        <!-- 3D Ground wrapper -->
                        <div class="field3d-container bg-dark bg-opacity-70 border border-secondary" id="fieldContainer3d">
                            <!-- Three.js Canvas renders here -->
                        </div>

                        <!-- Camera controls -->
                        <div class="mt-3">
                            <span class="custom-stat-label d-block mb-2 text-center">Interactive Cameras</span>
                            <div class="d-flex flex-wrap gap-2 justify-content-center">
                                <button class="btn camera-controls-btn active" onclick="switchCamera('broadcast', this)">Broadcast</button>
                                <button class="btn camera-controls-btn" onclick="switchCamera('bowler', this)">Bowler</button>
                                <button class="btn camera-controls-btn" onclick="switchCamera('batsman', this)">Striker</button>
                                <button class="btn camera-controls-btn" onclick="switchCamera('top', this)">Top</button>
                                <button class="btn camera-controls-btn" onclick="switchCamera('free', this)">Free Cam</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lineups Accordin Cards -->
                <div class="col-12">
                    <div class="card glass-card p-3">
                        <div class="accordion" id="lineupAccordion">
                            <!-- Batting Lineup Accordion -->
                            <div class="accordion-item bg-transparent">
                                <h2 class="accordion-header" id="headingBatting">
                                    <button class="accordion-button collapsed rounded" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBatting">
                                        <i class="fas fa-users me-2"></i> Batting Lineup & Stats
                                    </button>
                                </h2>
                                <div id="collapseBatting" class="accordion-collapse collapse" data-bs-parent="#lineupAccordion">
                                    <div class="accordion-body px-0 py-2">
                                        <div id="battingLineup" class="lineup-list" style="max-height: 250px; overflow-y: auto;">
                                            <p class="cp-muted small text-center m-0">No lineup data available</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Bowling figures Accordion -->
                            <div class="accordion-item bg-transparent mt-2">
                                <h2 class="accordion-header" id="headingBowling">
                                    <button class="accordion-button collapsed rounded" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBowling">
                                        <i class="fas fa-bowling-ball me-2"></i> Bowling Lineup & Figures
                                    </button>
                                </h2>
                                <div id="collapseBowling" class="accordion-collapse collapse" data-bs-parent="#lineupAccordion">
                                    <div class="accordion-body px-0 py-2">
                                        <div id="bowlingLineup" class="lineup-list" style="max-height: 250px; overflow-y: auto;">
                                            <p class="cp-muted small text-center m-0">No bowling data available</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Load JavaScript Dependencies -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= h(url_for('js/cricket_field.js')) ?>"></script>

<script>
const matchId = <?= $match_id ?>;
const innings = <?= $innings ?>;

let field3d = null;
let lastRecordedBallId = null;
let wormChart = null;
let runRateChart = null;
let innings1GraphData = null;

// Commentary presets for variety
const commentaries = {
    wicket: [
        "STUMPS SHATTERED! {striker} is clean bowled by {bowler}! Absolute peach of a delivery!",
        "OUT! In the air and CAUGHT! {striker} tries to clear the ropes but is caught off {bowler}'s bowling!",
        "LBW! Plumb in front! {striker} is trapped right in front of stumps by {bowler}!",
        "RUN OUT! Spectacular fielder throw! {striker} has run out of crease!",
        "STUMPED! Wicket keeper makes quick work, and {striker} is gone off {bowler}!"
    ],
    six: [
        "MAMMOTH SIX! {striker} launches {bowler} deep into the stands! What a shot!",
        "OUT OF THE PARK! A towering hit over long-on by {striker}!",
        "INTO THE CROWD! A massive lofted strike, maximum points for {striker}!"
    ],
    four: [
        "CRACKING SHOT! {striker} drives {bowler} beautifully through covers for FOUR!",
        "FOUR RUNS! Excellent timing and placement by {striker} races to the boundary!",
        "ELEGANT COVER DRIVE! Pitch perfect execution from {striker} for FOUR!"
    ],
    three: [
        "Great placement! They push hard and sprint for three runs off {bowler}."
    ],
    two: [
        "Nicely tucked in the gap. They jog back comfortably for a double."
    ],
    one: [
        "Quick single. {striker} pushes it to mid-off and rotates the strike.",
        "A push and run, easy single for {striker}."
    ],
    zero: [
        "Defended. {striker} blocks it back to {bowler}.",
        "No run. Left alone to the wicket keeper."
    ],
    wide: [
        "Wide ball! {bowler} sprays it down the leg side.",
        "Wide! Straying too far outside off crease."
    ],
    no_ball: [
        "No Ball! Overstepped by {bowler}! Free Hit coming up!"
    ]
};

document.addEventListener('DOMContentLoaded', function() {
    // 1. Initialize 3D Arena
    try {
        field3d = new CricketField3D('fieldContainer3d', { isInteractive: true });
    } catch(e) {
        console.error("Three.js initialization failed:", e);
    }

    // 2. Fetch Innings 1 data once if we are in Innings 2 (for comparing worm lines)
    if (innings === 2) {
        fetch(`/api/match_state.php?match_id=${matchId}&innings=1`)
            .then(r => r.json())
            .then(data => {
                if (data && data.graph_worm) {
                    innings1GraphData = data.graph_worm;
                }
                initScoreboardRefresh();
            })
            .catch(() => initScoreboardRefresh());
    } else {
        initScoreboardRefresh();
    }
});

function initScoreboardRefresh() {
    refreshScoreboard();
    setInterval(refreshScoreboard, 3000); // Poll server every 3 seconds
}

function refreshScoreboard() {
    fetch(`/api/match_state.php?match_id=${matchId}&innings=${innings}`)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                console.error(data.error);
                return;
            }

            // Sync scoreboard details
            document.getElementById('battingTeamName').textContent = data.batting_team.name;
            document.getElementById('totalRuns').textContent = data.score.total_runs;
            document.getElementById('wickets').textContent = `${data.score.wickets}/10`;
            document.getElementById('overs').textContent = `${data.score.overs} / 20.0`;
            document.getElementById('runRate').textContent = data.score.run_rate.toFixed(2);

            // Partnership Calculation
            updatePartnership(data);

            // Innings 2 Target & RRR Sync
            if (data.score.target !== null) {
                document.getElementById('targetContainer').style.display = 'block';
                document.getElementById('requiredContainer').style.display = 'block';
                document.getElementById('targetScore').textContent = data.score.target;
                
                const needed = data.score.runs_needed;
                
                // Calculate remaining balls
                const parts = data.score.overs.split('.');
                const oversComp = parseInt(parts[0]) || 0;
                const ballsComp = parseInt(parts[1]) || 0;
                const totalBalls = (oversComp * 6) + ballsComp;
                const ballsRemaining = Math.max(0, 120 - totalBalls);

                if (needed > 0 && ballsRemaining >= 0) {
                    document.getElementById('requiredText').textContent = `Need ${needed} runs off ${ballsRemaining} balls (RRR: ${data.score.required_run_rate})`;
                } else if (needed <= 0) {
                    document.getElementById('requiredText').className = 'fw-bold text-success small';
                    document.getElementById('requiredText').textContent = 'Match Won by Batting Team!';
                } else {
                    document.getElementById('requiredText').className = 'fw-bold text-danger small';
                    document.getElementById('requiredText').textContent = 'Innings completed!';
                }
            }

            // Striker details
            if (data.next_striker && data.next_striker.id) {
                document.getElementById('strikerName').textContent = data.next_striker.name;
                document.getElementById('strikerRuns').textContent = data.next_striker.runs;
                document.getElementById('strikerBalls').textContent = data.next_striker.balls;
                
                // Fetch fours/sixes from batting lineup stats
                const strikerStats = data.batting_lineup.find(p => p.id === data.next_striker.id);
                if (strikerStats) {
                    document.getElementById('strikerBoundaries').textContent = `${strikerStats.fours || 0}/${strikerStats.sixes || 0}`;
                }
                
                if (data.next_striker.balls > 0) {
                    const sr = (data.next_striker.runs / data.next_striker.balls * 100).toFixed(1);
                    document.getElementById('strikerSR').textContent = sr;
                } else {
                    document.getElementById('strikerSR').textContent = '0.0';
                }
            }

            // Non-Striker details
            if (data.non_striker && data.non_striker.id) {
                document.getElementById('nonStrikerName').textContent = data.non_striker.name;
                document.getElementById('nonStrikerRuns').textContent = data.non_striker.runs;
                document.getElementById('nonStrikerBalls').textContent = data.non_striker.balls;
                
                const nsStats = data.batting_lineup.find(p => p.id === data.non_striker.id);
                if (nsStats) {
                    document.getElementById('nonStrikerBoundaries').textContent = `${nsStats.fours || 0}/${nsStats.sixes || 0}`;
                }
                
                if (data.non_striker.balls > 0) {
                    const sr = (data.non_striker.runs / data.non_striker.balls * 100).toFixed(1);
                    document.getElementById('nonStrikerSR').textContent = sr;
                } else {
                    document.getElementById('nonStrikerSR').textContent = '0.0';
                }
            }

            // Bowler details
            if (data.bowler && data.bowler.id) {
                document.getElementById('bowlerName').textContent = data.bowler.name;
                document.getElementById('bowlerOvers').textContent = data.bowler.overs;
                
                // Fetch extra stats from bowling figures
                const bowlerStats = data.bowling_lineup.find(p => p.id === data.bowler.id);
                if (bowlerStats) {
                    document.getElementById('bowlerMaidens').textContent = bowlerStats.maiden_overs || 0;
                    document.getElementById('bowlerRunsConceded').textContent = bowlerStats.runs_conceded || 0;
                    document.getElementById('bowlerWickets').textContent = bowlerStats.wickets || 0;
                    document.getElementById('bowlerEconomy').textContent = (bowlerStats.economy || 0).toFixed(2);
                }
                
                // Find fantasy points
                document.getElementById('bowlerFantasy').textContent = data.score.total_runs * 2; // placeholder or calculations
            }

            // Timeline Dots Sync
            const timeline = document.getElementById('ballTimeline');
            timeline.innerHTML = '';
            data.last_six_balls.forEach((ball, idx) => {
                const dot = document.createElement('span');
                dot.className = 'ball-dot';
                if (ball.is_wicket) {
                    dot.className += ' wicket';
                    dot.textContent = 'W';
                } else {
                    dot.className += ` runs-${ball.runs}`;
                    dot.textContent = ball.display;
                }
                dot.title = `${ball.strike} vs ${ball.bowler}: ${ball.display}`;
                timeline.appendChild(dot);
            });

            // Sync Accordin Lineups
            updateBattingLineupList(data.batting_lineup);
            updateBowlingLineupList(data.bowling_lineup);

            // Draw Charts
            updateGraphs(data);

            // Sync 3D Field setup name
            const presetVal = data.field_setup || 'normal';
            document.getElementById('stadiumPresetBadge').textContent = `Setup: ${presetVal.toUpperCase()}`;

            // Free Hit Badge check
            if (data.last_ball_details && data.last_ball_details.extra_type === 'no_ball') {
                document.getElementById('freeHitBadge').style.display = 'inline-block';
            } else {
                document.getElementById('freeHitBadge').style.display = 'none';
            }

            // Sync 3D State & Trigger ball-by-ball animations
            if (field3d) {
                field3d.updateState(data);
            }

            // Detect new ball event to generate commentary & highlight strike swaps
            if (data.last_ball_details && data.last_ball_details.id !== lastRecordedBallId) {
                const isNewBall = (lastRecordedBallId !== null);
                lastRecordedBallId = data.last_ball_details.id;
                
                if (isNewBall) {
                    triggerCommentaryLog(data.last_ball_details, data.next_striker, data.bowler);
                }
            }
        })
        .catch(e => console.error('Scoreboard Refresh error:', e));
}

function updatePartnership(data) {
    if (!data.graph_worm || data.graph_worm.length === 0) {
        document.getElementById('partnershipText').textContent = '0 runs off 0 balls';
        document.getElementById('lastWicketText').textContent = '-';
        return;
    }

    const worm = data.graph_worm;
    
    // Find last wicket ball index
    let lastWicketIdx = -1;
    for (let i = worm.length - 1; i >= 0; i--) {
        if (worm[i].runs_off_this_ball === 0 && worm[i].wickets > (i > 0 ? worm[i-1].wickets : 0)) {
            // Found a wicket ball!
            // Wait, runs_off_this_ball might be positive in a run-out, but is_wicket determines it
        }
    }

    // Let's sweep forwards to find the last wicket's score
    let lastWicketScore = 0;
    let lastWicketBallCount = 0;
    let lastWicketDesc = '-';

    for (let i = 0; i < worm.length; i++) {
        const ball = worm[i];
        const prevWickets = i > 0 ? worm[i-1].wickets : 0;
        if (ball.wickets > prevWickets) {
            lastWicketScore = ball.cumulative_runs;
            lastWicketBallCount = i + 1;
            lastWicketDesc = `${lastWicketScore}/${ball.wickets} (at Over ${Math.floor(i / 6)}.${(i % 6) + 1})`;
        }
    }

    document.getElementById('lastWicketText').textContent = lastWicketDesc;

    // Current partnership is score minus last wicket score
    const partnershipRuns = data.score.total_runs - lastWicketScore;
    const totalBallsBowled = worm.length;
    const partnershipBalls = totalBallsBowled - lastWicketBallCount;
    document.getElementById('partnershipText').textContent = `${partnershipRuns} runs off ${partnershipBalls} balls`;
}

function triggerCommentaryLog(ball, striker, bowler) {
    const container = document.getElementById('commentaryContainer');
    let text = '';
    let category = 'runs';

    const sName = striker.name || 'Batsman';
    const bName = bowler.name || 'Bowler';

    if (ball.is_wicket) {
        category = 'wicket';
        const list = commentaries.wicket;
        text = list[Math.floor(Math.random() * list.length)];
    } else if (ball.extra_type === 'wide') {
        text = commentaries.wide[Math.floor(Math.random() * commentaries.wide.length)];
    } else if (ball.extra_type === 'no_ball') {
        text = commentaries.no_ball[Math.floor(Math.random() * commentaries.no_ball.length)];
    } else {
        const runs = ball.runs;
        if (runs === 6) {
            category = 'boundary';
            text = commentaries.six[Math.floor(Math.random() * commentaries.six.length)];
        } else if (runs === 4) {
            category = 'boundary';
            text = commentaries.four[Math.floor(Math.random() * commentaries.four.length)];
        } else if (runs === 3) {
            text = commentaries.three[0];
        } else if (runs === 2) {
            text = commentaries.two[0];
        } else if (runs === 1) {
            text = commentaries.one[Math.floor(Math.random() * commentaries.one.length)];
        } else {
            text = commentaries.zero[Math.floor(Math.random() * commentaries.zero.length)];
        }
    }

    // Format template tags
    text = text.replace('{striker}', sName).replace('{bowler}', bName).replace('{wicket_type}', ball.wicket_type);

    // Create item
    const item = document.createElement('div');
    item.className = `commentary-item ${category}`;
    item.innerHTML = `<span class="cp-muted text-success fw-bold">${ball.over}.${ball.ball}</span> <strong>${bName} to ${sName}:</strong> ${text}`;
    
    // Insert at top of commentary box
    container.insertBefore(item, container.firstChild);
}

function updateBattingLineupList(lineup) {
    const el = document.getElementById('battingLineup');
    let html = '';

    lineup.forEach(p => {
        const runs = p.runs || 0;
        const balls = p.balls_faced || 0;
        const fours = p.fours || 0;
        const sixes = p.sixes || 0;
        const sr = balls > 0 ? ((runs / balls) * 100).toFixed(1) : '0.0';
        const outStatus = p.is_out ? '<span class="badge bg-danger ms-2">Out</span>' : '<span class="badge bg-success ms-2">Active/Yet to Bat</span>';

        html += `
            <div class="p-2 border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 text-white fw-bold">${p.full_name} ${outStatus}</h6>
                    <span class="small cp-muted">${runs} Runs off ${balls} balls (4s: ${fours} | 6s: ${sixes})</span>
                </div>
                <div class="text-end">
                    <span class="small custom-stat-label d-block">Strike Rate</span>
                    <strong class="neon-green-text">${sr}</strong>
                </div>
            </div>
        `;
    });

    el.innerHTML = html || '<p class="cp-muted small text-center m-0">No stats available</p>';
}

function updateBowlingLineupList(lineup) {
    const el = document.getElementById('bowlingLineup');
    let html = '';

    lineup.forEach(p => {
        const balls = p.balls_bowled || 0;
        const overs = Math.floor(balls / 6) + '.' + (balls % 6);
        const maidens = p.maiden_overs || 0;
        const runs = p.runs_conceded || 0;
        const wickets = p.wickets || 0;
        const econ = p.economy || 0;

        html += `
            <div class="p-2 border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 text-white fw-bold">${p.full_name}</h6>
                    <span class="small cp-muted">Overs: ${overs} | Maidens: ${maidens} | Runs Conceded: ${runs}</span>
                </div>
                <div class="text-end">
                    <span class="small custom-stat-label d-block">Wickets</span>
                    <strong class="neon-cyan-text">${wickets}W (Econ: ${econ.toFixed(2)})</strong>
                </div>
            </div>
        `;
    });

    el.innerHTML = html || '<p class="cp-muted small text-center m-0">No figures available</p>';
}

function updateGraphs(data) {
    // 1. Worm Chart (Cumulative Runs)
    const wormCtx = document.getElementById('wormChart').getContext('2d');
    const wormLabels = data.graph_worm.map(b => b.ball_display);
    const wormData = data.graph_worm.map(b => b.cumulative_runs);

    if (!wormChart) {
        const datasets = [{
            label: 'Innings ' + innings,
            data: wormData,
            borderColor: '#15d17a',
            backgroundColor: 'rgba(21, 209, 122, 0.05)',
            borderWidth: 3,
            fill: true,
            tension: 0.1
        }];

        // Add Innings 1 baseline if available
        if (innings === 2 && innings1GraphData) {
            datasets.unshift({
                label: 'Innings 1',
                data: innings1GraphData.map(b => b.cumulative_runs),
                borderColor: '#6c757d',
                borderDash: [5, 5],
                borderWidth: 2,
                fill: false,
                tension: 0.1
            });
        }

        wormChart = new Chart(wormCtx, {
            type: 'line',
            data: {
                labels: wormLabels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#a8c4b6' } } },
                scales: {
                    x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#a8c4b6', maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
                    y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#a8c4b6' } }
                }
            }
        });
    } else {
        // Update data
        const currentDatasetIndex = innings === 2 ? 1 : 0;
        wormChart.data.labels = wormLabels;
        wormChart.data.datasets[currentDatasetIndex].data = wormData;
        wormChart.update('none'); // Update smoothly without bounce
    }

    // 2. Run Rate Chart (Bar chart for runs per over)
    const rrCtx = document.getElementById('runRateChart').getContext('2d');
    const rrLabels = data.graph_run_rate.labels;
    const rrData = data.graph_run_rate.runs;

    if (!runRateChart) {
        runRateChart = new Chart(rrCtx, {
            type: 'bar',
            data: {
                labels: rrLabels,
                datasets: [{
                    label: 'Runs in Over',
                    data: rrData,
                    backgroundColor: 'rgba(13, 202, 240, 0.45)',
                    borderColor: '#0dcaf0',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#a8c4b6' } },
                    y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#a8c4b6', stepSize: 2 } }
                }
            }
        });
    } else {
        runRateChart.data.labels = rrLabels;
        runRateChart.data.datasets[0].data = rrData;
        runRateChart.update('none');
    }
}

function switchCamera(view, btn) {
    // Sync buttons
    document.querySelectorAll('.camera-controls-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    if (field3d) {
        field3d.setCameraView(view);
    }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
