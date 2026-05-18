<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../access.php';
require_once __DIR__ . '/../../database/cricket_db.php';

require_admin_or_organizer();

$pdo = db();
$match_id = (int)($_GET['match_id'] ?? 0);
$innings = (int)($_GET['innings'] ?? 1);

if ($match_id <= 0) {
    header("Location: setup.php");
    exit;
}

$match = get_match_details($match_id);
if (!$match) {
    die("Match not found");
}

$battingTeamId = (int)$match['batting_team_id'];
$bowlingTeamId = (int)$match['bowling_team_id'];

if (!$battingTeamId || !$bowlingTeamId) {
    header("Location: setup.php?match_id={$match_id}");
    exit;
}

$battingPlayers = get_team_players($battingTeamId);
$bowlingPlayers = get_team_players($bowlingTeamId);
$score = get_match_score($match_id, $innings);

require_once __DIR__ . '/../../header.php';
?>

<div class="container-fluid p-2 p-md-3">
    <div class="row g-2 g-md-3">
        <!-- Left Column: Scoreboard -->
        <div class="col-12 col-lg-4">
            <div class="cp-card p-3 mb-3">
                <h4 class="fw-bold mb-3">
                    <i class="fas fa-tachometer-alt"></i> Live Scoreboard
                </h4>

                <div id="scoreboard" class="scoreboard-display">
                    <div class="score-main mb-3">
                        <div class="team-score">
                            <h2 id="totalRuns" class="fw-bold">0</h2>
                            <p class="small cp-muted" id="gameStatus">Setup...</p>
                        </div>
                        <div class="score-details">
                            <div class="stat-item">
                                <span class="label">Wickets</span>
                                <span id="wickets" class="value fw-bold">0</span>
                            </div>
                            <div class="stat-item">
                                <span class="label">Overs</span>
                                <span id="overs" class="value fw-bold">0.0</span>
                            </div>
                            <div class="stat-item">
                                <span class="label">Run Rate</span>
                                <span id="runRate" class="value fw-bold">0.00</span>
                            </div>
                        </div>
                    </div>

                    <!-- Batsmen Info -->
                    <div class="batsmen-info mb-3">
                        <div class="batsman">
                            <strong>Striker</strong>
                            <p id="strikerInfo" class="small">-</p>
                        </div>
                        <div class="batsman">
                            <strong>Non-Striker</strong>
                            <p id="nonStrikerInfo" class="small">-</p>
                        </div>
                    </div>

                    <!-- Bowler Info -->
                    <div class="bowler-info mb-3">
                        <strong>Bowler</strong>
                        <p id="bowlerInfo" class="small">-</p>
                    </div>

                    <!-- Last 6 Balls -->
                    <div class="last-balls">
                        <strong class="d-block mb-2">Last 6 Balls</strong>
                        <div id="ballTimeline" class="ball-timeline">
                            <span class="ball-dot">-</span>
                            <span class="ball-dot">-</span>
                            <span class="ball-dot">-</span>
                            <span class="ball-dot">-</span>
                            <span class="ball-dot">-</span>
                            <span class="ball-dot">-</span>
                        </div>
                    </div>
                </div>

                <hr class="my-3">

                <!-- Controls -->
                <div class="controls">
                    <button class="btn btn-sm btn-info w-100 mb-2" onclick="refreshScoreboard()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                    <a href="setup.php?match_id=<?= $match_id ?>" class="btn btn-sm btn-warning w-100 mb-2">
                        <i class="fas fa-cog"></i> Configure
                    </a>
                    <a href="../../live_score_board.php" class="btn btn-sm btn-secondary w-100">
                        <i class="fas fa-eye"></i> View Scoreboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Center Column: Ball Entry -->
        <div class="col-12 col-lg-4">
            <div class="cp-card p-3 mb-3">
                <h4 class="fw-bold mb-3">
                    <i class="fas fa-cricket"></i> Ball Entry
                </h4>

                <!-- Player Selection -->
                <div class="mb-3">
                    <label class="form-label small">Striker</label>
                    <select id="striker" class="form-select form-select-sm">
                        <option value="">-- Select --</option>
                        <?php foreach ($battingPlayers as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= h($p['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Non-Striker</label>
                    <select id="nonStriker" class="form-select form-select-sm">
                        <option value="">-- Select --</option>
                        <?php foreach ($battingPlayers as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= h($p['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small">Bowler</label>
                    <select id="bowler" class="form-select form-select-sm">
                        <option value="">-- Select --</option>
                        <?php foreach ($bowlingPlayers as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= h($p['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <hr class="my-3">

                <!-- Scoring Buttons -->
                <div class="scoring-buttons mb-3">
                    <div class="button-group mb-2">
                        <button onclick="recordBall(0)" class="btn btn-outline-secondary btn-sm flex-fill">0</button>
                        <button onclick="recordBall(1)" class="btn btn-outline-secondary btn-sm flex-fill">1</button>
                        <button onclick="recordBall(2)" class="btn btn-outline-secondary btn-sm flex-fill">2</button>
                        <button onclick="recordBall(3)" class="btn btn-outline-secondary btn-sm flex-fill">3</button>
                    </div>
                    <div class="button-group mb-2">
                        <button onclick="recordBall(4)" class="btn btn-outline-warning btn-sm flex-fill fw-bold">4</button>
                        <button onclick="recordBall(6)" class="btn btn-outline-danger btn-sm flex-fill fw-bold">6</button>
                        <button onclick="recordBall(0, 'wide')" class="btn btn-outline-info btn-sm flex-fill">Wide</button>
                        <button onclick="recordBall(0, 'no_ball')" class="btn btn-outline-info btn-sm flex-fill">No Ball</button>
                    </div>
                    <div class="button-group mb-2">
                        <button onclick="recordBall(0, 'bye')" class="btn btn-outline-info btn-sm flex-fill">Bye</button>
                        <button onclick="recordBall(0, 'leg_bye')" class="btn btn-outline-info btn-sm flex-fill">Leg Bye</button>
                        <button onclick="recordWicket()" class="btn btn-outline-danger btn-sm flex-fill fw-bold">Wicket</button>
                    </div>
                    <div class="button-group">
                        <button onclick="undoLastBall()" class="btn btn-warning btn-sm flex-fill">
                            <i class="fas fa-undo"></i> Undo
                        </button>
                        <button onclick="endOver()" class="btn btn-info btn-sm flex-fill">
                            <i class="fas fa-forward"></i> End Over
                        </button>
                    </div>
                </div>

                <div class="button-group">
                    <button onclick="endInnings()" class="btn btn-danger btn-sm w-100 mb-2">
                        <i class="fas fa-stop"></i> End Innings
                    </button>
                </div>

                <div id="ballMessage" class="alert alert-info small d-none" role="alert"></div>
                <div id="ballError" class="alert alert-danger small d-none" role="alert"></div>
            </div>
        </div>

        <!-- Right Column: Stats -->
        <div class="col-12 col-lg-4">
            <div class="cp-card p-3 mb-3">
                <h4 class="fw-bold mb-3">
                    <i class="fas fa-chart-bar"></i> Recent Activity
                </h4>
                <div id="activityLog" class="activity-log" style="height: 500px; overflow-y: auto; font-size: 0.85rem;">
                    <p class="cp-muted small">Waiting for first ball...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Wicket Modal -->
<div class="modal fade" id="wicketModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Wicket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Wicket Type</label>
                    <select id="wicketType" class="form-select">
                        <option value="bowled">Bowled</option>
                        <option value="caught">Caught</option>
                        <option value="lbw">LBW</option>
                        <option value="run_out">Run Out</option>
                        <option value="stumped">Stumped</option>
                        <option value="hit_wicket">Hit Wicket</option>
                    </select>
                </div>
                <div class="mb-3" id="fielderDiv" style="display:none;">
                    <label class="form-label">Fielder</label>
                    <select id="fielder" class="form-select">
                        <option value="0">-- Select --</option>
                        <?php foreach ($bowlingPlayers as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= h($p['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Extra Runs (if any)</label>
                    <input type="number" id="wicketExtraRuns" class="form-control" value="0" min="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="submitWicket()">Record Wicket</button>
            </div>
        </div>
    </div>
</div>

<style>
.scoreboard-display {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 1rem;
    border-radius: 0.5rem;
}

.score-main {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.team-score h2 {
    font-size: 2.5rem;
    color: #28a745;
}

.score-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    flex: 1;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
}

.stat-item .label {
    color: #666;
}

.stat-item .value {
    color: #28a745;
}

.batsmen-info,
.bowler-info {
    background: white;
    padding: 0.75rem;
    border-radius: 0.375rem;
    border-left: 4px solid #0d6efd;
}

.batsman {
    margin-bottom: 0.5rem;
}

.batsman:last-child {
    margin-bottom: 0;
}

.scoring-buttons .button-group {
    display: flex;
    gap: 0.5rem;
}

.scoring-buttons .btn {
    flex: 1 !important;
    padding: 0.75rem 0.5rem !important;
    font-size: 0.9rem !important;
    font-weight: 600;
    border-radius: 0.375rem;
}

.last-balls .ball-timeline {
    display: flex;
    gap: 0.5rem;
    justify-content: space-around;
}

.ball-dot {
    width: 2rem;
    height: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #f0f0f0;
    font-weight: bold;
    font-size: 0.85rem;
}

.ball-dot.runs-0 { background: #dee2e6; }
.ball-dot.runs-1,
.ball-dot.runs-3 { background: #cfe2ff; color: #084298; }
.ball-dot.runs-2 { background: #cff4fc; color: #055160; }
.ball-dot.runs-4 { background: #fff3cd; color: #664d03; }
.ball-dot.runs-6 { background: #f8d7da; color: #7d2e2e; }
.ball-dot.wicket { background: #842029; color: white; }
.ball-dot.wide,
.ball-dot.no-ball { background: #d1e7dd; color: #0f5132; }

.activity-log {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 0.75rem;
}

.activity-log .log-item {
    padding: 0.5rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.activity-log .log-item:last-child {
    border-bottom: none;
}

@media (max-width: 768px) {
    .scoring-buttons .btn {
        padding: 0.5rem 0.25rem !important;
        font-size: 0.8rem !important;
    }

    .team-score h2 {
        font-size: 2rem;
    }
}
</style>

<script>
const matchId = <?= $match_id ?>;
const innings = <?= $innings ?>;
let wicketModal;

document.addEventListener('DOMContentLoaded', function() {
    wicketModal = new bootstrap.Modal(document.getElementById('wicketModal'));
    refreshScoreboard();
    setInterval(refreshScoreboard, 2000); // Refresh every 2 seconds

    // Show fielder input based on wicket type
    document.getElementById('wicketType').addEventListener('change', function() {
        const fielderDiv = document.getElementById('fielderDiv');
        const fielderRequired = ['caught', 'stumped', 'run_out'].includes(this.value);
        fielderDiv.style.display = fielderRequired ? 'block' : 'none';
    });
});

function recordBall(runs, extraType = 'none') {
    const striker = document.getElementById('striker').value;
    const nonStriker = document.getElementById('nonStriker').value;
    const bowler = document.getElementById('bowler').value;

    if (!striker || !nonStriker || !bowler) {
        showError('Please select all players');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'record_ball');
    formData.append('match_id', matchId);
    formData.append('innings', innings);
    formData.append('striker_id', striker);
    formData.append('non_striker_id', nonStriker);
    formData.append('bowler_id', bowler);
    formData.append('runs', runs);
    formData.append('extra_type', extraType);
    formData.append('extra_runs', extraType !== 'none' ? (runs || 1) : 0);

    fetch('/api/ball_entry.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showMessage('Ball recorded!');
                refreshScoreboard();
            } else {
                showError(data.error);
            }
        })
        .catch(e => showError('Network error'));
}

function recordWicket() {
    wicketModal.show();
}

function submitWicket() {
    const striker = document.getElementById('striker').value;
    const nonStriker = document.getElementById('nonStriker').value;
    const bowler = document.getElementById('bowler').value;
    const wicketType = document.getElementById('wicketType').value;
    const fielder = document.getElementById('fielder').value || 0;
    const extraRuns = parseInt(document.getElementById('wicketExtraRuns').value) || 0;

    if (!striker || !nonStriker || !bowler) {
        showError('Please select all players');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'wicket_details');
    formData.append('match_id', matchId);
    formData.append('innings', innings);
    formData.append('striker_id', striker);
    formData.append('non_striker_id', nonStriker);
    formData.append('bowler_id', bowler);
    formData.append('wicket_type', wicketType);
    formData.append('fielder_id', fielder);
    formData.append('extra_runs', extraRuns);

    fetch('/api/ball_entry.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                wicketModal.hide();
                showMessage('Wicket recorded!');
                refreshScoreboard();
            } else {
                showError(data.error);
            }
        })
        .catch(e => showError('Network error'));
}

function undoLastBall() {
    if (!confirm('Undo last ball?')) return;

    const formData = new FormData();
    formData.append('action', 'undo_last_ball');
    formData.append('match_id', matchId);
    formData.append('innings', innings);

    fetch('/api/ball_entry.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showMessage('Ball undone!');
                refreshScoreboard();
            } else {
                showError(data.error);
            }
        })
        .catch(e => showError('Network error'));
}

function endOver() {
    const formData = new FormData();
    formData.append('action', 'end_over');
    formData.append('match_id', matchId);
    formData.append('innings', innings);

    fetch('/api/ball_entry.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showMessage('Over ended!');
                refreshScoreboard();
            } else {
                showError(data.error);
            }
        })
        .catch(e => showError('Network error'));
}

function endInnings() {
    if (!confirm('End current innings?')) return;

    const formData = new FormData();
    formData.append('action', 'end_innings');
    formData.append('match_id', matchId);
    formData.append('innings', innings);

    fetch('/api/ball_entry.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showMessage('Innings ended!');
                location.href = `setup.php?match_id=${matchId}&innings=2`;
            } else {
                showError(data.error);
            }
        })
        .catch(e => showError('Network error'));
}

function refreshScoreboard() {
    fetch(`/api/match_state.php?match_id=${matchId}&innings=${innings}`)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                showError(data.error);
                return;
            }

            // Update score
            document.getElementById('totalRuns').textContent = data.score.total_runs;
            document.getElementById('wickets').textContent = data.score.wickets;
            document.getElementById('overs').textContent = data.score.overs;
            document.getElementById('runRate').textContent = data.score.run_rate.toFixed(2);

            // Update batsmen
            const striker = data.next_striker;
            const nonStriker = data.non_striker;
            if (striker && striker.id) {
                document.getElementById('strikerInfo').innerHTML = `<strong>${striker.name}</strong><br>${striker.runs}(${striker.balls})`;
            }
            if (nonStriker && nonStriker.id) {
                document.getElementById('nonStrikerInfo').innerHTML = `<strong>${nonStriker.name}</strong><br>${nonStriker.runs}(${nonStriker.balls})`;
            }

            // Update bowler
            const bowler = data.bowler;
            if (bowler && bowler.id) {
                document.getElementById('bowlerInfo').innerHTML = `<strong>${bowler.name}</strong><br>${bowler.overs} overs, ${bowler.wickets} wickets`;
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
                dot.title = `${ball.striker} vs ${ball.bowler}`;
                timeline.appendChild(dot);
            });

            // Update activity log
            updateActivityLog(data);
        })
        .catch(e => console.error('Refresh error:', e));
}

function updateActivityLog(data) {
    const log = document.getElementById('activityLog');
    let html = '';

    data.last_six_balls.forEach((ball, idx) => {
        html += `<div class="log-item">
            <strong>Ball ${data.last_six_balls.length - idx}:</strong> ${ball.striker} vs ${ball.bowler}<br>
            <small>${ball.display} (${ball.runs} runs)</small>
        </div>`;
    });

    if (!html) {
        html = '<p class="cp-muted small">No balls bowled yet...</p>';
    }

    log.innerHTML = html;
}

function showMessage(msg) {
    const el = document.getElementById('ballMessage');
    el.textContent = msg;
    el.classList.remove('d-none');
    setTimeout(() => el.classList.add('d-none'), 3000);
}

function showError(msg) {
    const el = document.getElementById('ballError');
    el.textContent = msg;
    el.classList.remove('d-none');
    setTimeout(() => el.classList.add('d-none'), 4000);
}

function h(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>

<?php require_once __DIR__ . '/../../footer.php'; ?>
