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

<div class="container-fluid p-2 p-md-3 bg-dark text-light">
    <!-- Header -->
    <div class="row mb-3">
        <div class="col-12">
            <h2 class="fw-bold mb-1"><?= h($match['match_name']) ?></h2>
            <p class="small cp-muted">Innings <?= $innings ?> | <?= htmlspecialchars($match['match_date']) ?></p>
        </div>
    </div>

    <div class="row g-2 g-md-3">
        <!-- Main Scoreboard -->
        <div class="col-12 col-lg-8">
            <div class="cp-card bg-dark border-secondary p-4 mb-3">
                <!-- Team Banner -->
                <div class="team-banner mb-4">
                    <h3 class="fw-bold text-success mb-1" id="battingTeamName">
                        <?= h($match['batting_team_name']) ?>
                    </h3>
                    <p class="cp-muted small">Batting Team</p>
                </div>

                <!-- Score Display -->
                <div class="score-display mb-4 p-4 bg-secondary rounded">
                    <div class="row g-4">
                        <div class="col-12 col-md-6">
                            <div class="main-score">
                                <p class="cp-muted small mb-2">Total Score</p>
                                <h1 class="fw-bold text-success display-4" id="totalRuns">0</h1>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="score-details">
                                <div class="stat-row mb-3">
                                    <span class="label">Wickets</span>
                                    <span id="wickets" class="value fw-bold">0/11</span>
                                </div>
                                <div class="stat-row mb-3">
                                    <span class="label">Overs</span>
                                    <span id="overs" class="value fw-bold">0.0/20.0</span>
                                </div>
                                <div class="stat-row">
                                    <span class="label">Run Rate</span>
                                    <span id="runRate" class="value fw-bold">0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Batsmen Section -->
                <div class="batsmen-section mb-4">
                    <h5 class="fw-bold mb-3">Batsmen</h5>
                    <div class="row g-3">
                        <!-- Striker -->
                        <div class="col-12 col-md-6">
                            <div class="batsman-card p-3 bg-secondary rounded border-start border-success border-4">
                                <p class="cp-muted small mb-1">Striker</p>
                                <h6 id="strikerName" class="fw-bold mb-2">-</h6>
                                <div class="stats-grid">
                                    <div class="stat">
                                        <span class="label small">Runs</span>
                                        <span id="strikerRuns" class="value fw-bold">0</span>
                                    </div>
                                    <div class="stat">
                                        <span class="label small">Balls</span>
                                        <span id="strikerBalls" class="value fw-bold">0</span>
                                    </div>
                                    <div class="stat">
                                        <span class="label small">SR</span>
                                        <span id="strikerSR" class="value fw-bold">0.00</span>
                                    </div>
                                    <div class="stat">
                                        <span class="label small">Fours</span>
                                        <span id="strikerFours" class="value fw-bold">0</span>
                                    </div>
                                    <div class="stat">
                                        <span class="label small">Sixes</span>
                                        <span id="strikerSixes" class="value fw-bold">0</span>
                                    </div>
                                    <div class="stat">
                                        <span class="label small">Status</span>
                                        <span id="strikerStatus" class="value fw-bold badge bg-success">In</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Non-Striker -->
                        <div class="col-12 col-md-6">
                            <div class="batsman-card p-3 bg-secondary rounded border-start border-warning border-4">
                                <p class="cp-muted small mb-1">Non-Striker</p>
                                <h6 id="nonStrikerName" class="fw-bold mb-2">-</h6>
                                <div class="stats-grid">
                                    <div class="stat">
                                        <span class="label small">Runs</span>
                                        <span id="nonStrikerRuns" class="value fw-bold">0</span>
                                    </div>
                                    <div class="stat">
                                        <span class="label small">Balls</span>
                                        <span id="nonStrikerBalls" class="value fw-bold">0</span>
                                    </div>
                                    <div class="stat">
                                        <span class="label small">SR</span>
                                        <span id="nonStrikerSR" class="value fw-bold">0.00</span>
                                    </div>
                                    <div class="stat">
                                        <span class="label small">Fours</span>
                                        <span id="nonStrikerFours" class="value fw-bold">0</span>
                                    </div>
                                    <div class="stat">
                                        <span class="label small">Sixes</span>
                                        <span id="nonStrikerSixes" class="value fw-bold">0</span>
                                    </div>
                                    <div class="stat">
                                        <span class="label small">Status</span>
                                        <span id="nonStrikerStatus" class="value fw-bold badge bg-warning">In</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bowler Section -->
                <div class="bowler-section mb-4">
                    <h5 class="fw-bold mb-3">Bowler</h5>
                    <div class="bowler-card p-3 bg-secondary rounded border-start border-danger border-4">
                        <p class="cp-muted small mb-1">Current Bowler</p>
                        <h6 id="bowlerName" class="fw-bold mb-2">-</h6>
                        <div class="stats-grid">
                            <div class="stat">
                                <span class="label small">Overs</span>
                                <span id="bowlerOvers" class="value fw-bold">0.0</span>
                            </div>
                            <div class="stat">
                                <span class="label small">Runs</span>
                                <span id="bowlerRuns" class="value fw-bold">0</span>
                            </div>
                            <div class="stat">
                                <span class="label small">Wickets</span>
                                <span id="bowlerWickets" class="value fw-bold">0</span>
                            </div>
                            <div class="stat">
                                <span class="label small">Economy</span>
                                <span id="bowlerEconomy" class="value fw-bold">0.00</span>
                            </div>
                            <div class="stat">
                                <span class="label small">Maidens</span>
                                <span id="bowlerMaidens" class="value fw-bold">0</span>
                            </div>
                            <div class="stat">
                                <span class="label small">Fantasy</span>
                                <span id="bowlerFantasy" class="value fw-bold badge bg-info">0</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Last 6 Balls -->
                <div class="last-balls-section mb-4">
                    <h5 class="fw-bold mb-3">Last 6 Balls Timeline</h5>
                    <div id="ballTimeline" class="ball-timeline-container">
                        <span class="ball-dot">-</span>
                        <span class="ball-dot">-</span>
                        <span class="ball-dot">-</span>
                        <span class="ball-dot">-</span>
                        <span class="ball-dot">-</span>
                        <span class="ball-dot">-</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar: Batting Lineup -->
        <div class="col-12 col-lg-4">
            <div class="cp-card bg-dark border-secondary p-3 mb-3">
                <h5 class="fw-bold mb-3">Batting Lineup</h5>
                <div id="battingLineup" class="lineup-list">
                    <p class="cp-muted small">Loading...</p>
                </div>
            </div>

            <div class="cp-card bg-dark border-secondary p-3 mb-3">
                <h5 class="fw-bold mb-3">Bowling Figures</h5>
                <div id="bowlingLineup" class="lineup-list">
                    <p class="cp-muted small">Loading...</p>
                </div>
            </div>

            <!-- Controls -->
            <div class="cp-card bg-dark border-secondary p-3">
                <div class="d-grid gap-2">
                    <button onclick="refreshScoreboard()" class="btn btn-info btn-sm">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                    <a href="live_score_board.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
body.bg-dark {
    background-color: #1a1a1a !important;
    color: #f0f0f0;
}

.cp-card {
    border: 1px solid #444;
}

.bg-secondary {
    background-color: #2d2d2d !important;
}

.score-display {
    background: linear-gradient(135deg, #2d2d2d, #333) !important;
}

.main-score h1 {
    font-size: 3.5rem;
    line-height: 1;
}

.stat-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.stat {
    text-align: center;
}

.stat .label {
    display: block;
    color: #aaa;
    margin-bottom: 0.25rem;
}

.stat .value {
    display: block;
    font-size: 1.1rem;
    color: #fff;
}

.batsman-card,
.bowler-card {
    transition: all 0.3s ease;
}

.batsman-card:hover,
.bowler-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.ball-timeline-container {
    display: flex;
    gap: 0.75rem;
    justify-content: space-around;
    flex-wrap: wrap;
    padding: 1rem;
    background-color: #2d2d2d;
    border-radius: 0.5rem;
}

.ball-dot {
    width: 2.5rem;
    height: 2.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #3d3d3d;
    font-weight: bold;
    font-size: 1rem;
    color: #fff;
    border: 2px solid transparent;
    transition: all 0.2s ease;
}

.ball-dot:hover {
    transform: scale(1.1);
}

.ball-dot.runs-0 { background: #3d3d3d; }
.ball-dot.runs-1,
.ball-dot.runs-3 { background: #0d47a1; color: #fff; }
.ball-dot.runs-2 { background: #00838f; color: #fff; }
.ball-dot.runs-4 { background: #f57f17; color: #fff; }
.ball-dot.runs-6 { background: #d32f2f; color: #fff; }
.ball-dot.wicket { background: #7b1fa2; color: #fff; border-color: #fff; }
.ball-dot.wide,
.ball-dot.no-ball { background: #388e3c; color: #fff; }

.lineup-list {
    max-height: 400px;
    overflow-y: auto;
}

.lineup-item {
    padding: 0.75rem;
    border-bottom: 1px solid #444;
}

.lineup-item:last-child {
    border-bottom: none;
}

.lineup-item:hover {
    background-color: #333;
}

@media (max-width: 768px) {
    .main-score h1 {
        font-size: 2.5rem;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .ball-dot {
        width: 2rem;
        height: 2rem;
        font-size: 0.85rem;
    }
}
</style>

<script>
const matchId = <?= $match_id ?>;
const innings = <?= $innings ?>;

document.addEventListener('DOMContentLoaded', function() {
    refreshScoreboard();
    setInterval(refreshScoreboard, 3000); // Auto-refresh every 3 seconds
});

function refreshScoreboard() {
    fetch(`/api/match_state.php?match_id=${matchId}&innings=${innings}`)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                console.error(data.error);
                return;
            }

            // Update team name
            document.getElementById('battingTeamName').textContent = data.batting_team.name;

            // Update score
            document.getElementById('totalRuns').textContent = data.score.total_runs;
            document.getElementById('wickets').textContent = `${data.score.wickets}/11`;
            document.getElementById('overs').textContent = data.score.overs;
            document.getElementById('runRate').textContent = data.score.run_rate.toFixed(2);

            // Update striker
            if (data.next_striker && data.next_striker.id) {
                document.getElementById('strikerName').textContent = data.next_striker.name;
                document.getElementById('strikerRuns').textContent = data.next_striker.runs;
                document.getElementById('strikerBalls').textContent = data.next_striker.balls;
                if (data.next_striker.balls > 0) {
                    const sr = (data.next_striker.runs / data.next_striker.balls * 100).toFixed(2);
                    document.getElementById('strikerSR').textContent = sr;
                }
            }

            // Update non-striker
            if (data.non_striker && data.non_striker.id) {
                document.getElementById('nonStrikerName').textContent = data.non_striker.name;
                document.getElementById('nonStrikerRuns').textContent = data.non_striker.runs;
                document.getElementById('nonStrikerBalls').textContent = data.non_striker.balls;
                if (data.non_striker.balls > 0) {
                    const sr = (data.non_striker.runs / data.non_striker.balls * 100).toFixed(2);
                    document.getElementById('nonStrikerSR').textContent = sr;
                }
            }

            // Update bowler
            if (data.bowler && data.bowler.id) {
                document.getElementById('bowlerName').textContent = data.bowler.name;
                document.getElementById('bowlerOvers').textContent = data.bowler.overs;
                document.getElementById('bowlerRuns').textContent = data.bowler.wickets;
                document.getElementById('bowlerWickets').textContent = data.bowler.wickets;
            }

            // Update timeline
            const timeline = document.getElementById('ballTimeline');
            timeline.innerHTML = '';
            data.last_six_balls.forEach(ball => {
                const dot = document.createElement('span');
                dot.className = 'ball-dot';
                if (ball.is_wicket) {
                    dot.className += ' wicket';
                    dot.textContent = 'W';
                } else {
                    dot.className += ` runs-${ball.runs}`;
                    dot.textContent = ball.display;
                }
                dot.title = `${ball.striker} vs ${ball.bowler}: ${ball.display}`;
                timeline.appendChild(dot);
            });

            // Update batting lineup
            updateLineup('battingLineup', data.batting_lineup, 'batting');

            // Update bowling lineup
            updateLineup('bowlingLineup', data.bowling_lineup, 'bowling');
        })
        .catch(e => console.error('Refresh error:', e));
}

function updateLineup(elementId, lineup, type) {
    const el = document.getElementById(elementId);
    let html = '';

    if (type === 'batting') {
        lineup.forEach(player => {
            const runs = player.runs || 0;
            const balls = player.balls_faced || 0;
            const sr = balls > 0 ? (runs / balls * 100).toFixed(0) : 0;
            const status = player.is_out ? '✗' : '○';
            html += `
                <div class="lineup-item">
                    <div class="fw-bold">${player.full_name} ${status}</div>
                    <div class="small cp-muted">${runs}(${balls}) SR: ${sr}</div>
                </div>
            `;
        });
    } else {
        lineup.forEach(player => {
            const runs = player.runs_conceded || 0;
            const overs = player.balls_bowled ? Math.floor(player.balls_bowled / 6) + '.' + (player.balls_bowled % 6) : '0.0';
            const wickets = player.wickets || 0;
            const economy = player.economy || 0;
            html += `
                <div class="lineup-item">
                    <div class="fw-bold">${player.full_name}</div>
                    <div class="small cp-muted">${overs} overs, ${runs} runs, ${wickets}W Economy: ${economy.toFixed(2)}</div>
                </div>
            `;
        });
    }

    if (!html) {
        html = '<p class="cp-muted small">No data available</p>';
    }

    el.innerHTML = html;
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
