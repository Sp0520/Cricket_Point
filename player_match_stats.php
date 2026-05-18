<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database/cricket_db.php';
require_once __DIR__ . '/database/points_calculator.php';

$player_id = (int)($_GET['player_id'] ?? 0);
$match_id = (int)($_GET['match_id'] ?? 0);
$innings = (int)($_GET['innings'] ?? 1);

// If no player specified but user is logged in as player, use that player
if ($player_id === 0 && isset($_SESSION['player_id'])) {
    $player_id = (int)$_SESSION['player_id'];
}

if ($player_id <= 0 || $match_id <= 0) {
    die("Invalid player or match ID");
}

$pdo = db();

// Get player info
$st = $pdo->prepare("SELECT id, full_name, photo_path FROM players WHERE id = :id");
$st->execute([':id' => $player_id]);
$player = $st->fetch();

if (!$player) {
    die("Player not found");
}

// Get match info
$match = get_match_details($match_id);
if (!$match) {
    die("Match not found");
}

// Get player's stats for this match
$stats = get_batsman_stats($match_id, $player_id, $innings);

// Get fantasy points
$fantasyPoints = FantasyPointsCalculator::get_player_fantasy_points($match_id, $player_id, $innings);

// Get leaderboard for this match (top 10 fantasy scorers)
$st = $pdo->prepare("
    SELECT pp.player_id, p.full_name, pp.total_pts
    FROM player_points pp
    JOIN players p ON p.id = pp.player_id
    WHERE pp.match_id = :match_id AND pp.innings = :innings
    ORDER BY pp.total_pts DESC
    LIMIT 10
");
$st->execute([':match_id' => $match_id, ':innings' => $innings]);
$leaderboard = $st->fetchAll();

require_once __DIR__ . '/header.php';
?>

<div class="container-fluid p-2 p-md-4">
    <div class="row g-3">
        <!-- Main Content -->
        <div class="col-12 col-lg-8">
            <!-- Player Card -->
            <div class="cp-card p-4 mb-4">
                <div class="row g-4">
                    <div class="col-auto">
                        <?php if ($player['photo_path']): ?>
                            <img src="<?= h($player['photo_path']) ?>" alt="<?= h($player['full_name']) ?>" class="rounded-circle" style="width: 100px; height: 100px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                                <i class="fas fa-user text-light" style="font-size: 2rem;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col">
                        <h2 class="fw-bold mb-2"><?= h($player['full_name']) ?></h2>
                        <p class="cp-muted mb-0"><?= h($match['match_name']) ?></p>
                        <p class="small cp-muted">Innings <?= $innings ?> | <?= htmlspecialchars($match['match_date']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Fantasy Points Card -->
            <div class="cp-card p-4 mb-4 bg-success text-white">
                <div class="row g-3">
                    <div class="col-12">
                        <p class="mb-1">Total Fantasy Points</p>
                        <h1 class="fw-bold display-3 mb-0"><?= $fantasyPoints ?></h1>
                    </div>
                </div>
            </div>

            <!-- Batting Stats -->
            <div class="cp-card p-4 mb-4">
                <h4 class="fw-bold mb-3">Batting Performance</h4>
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div class="stat-box text-center">
                            <p class="cp-muted small mb-1">Runs</p>
                            <h3 class="fw-bold"><?= (int)$stats['runs'] ?></h3>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box text-center">
                            <p class="cp-muted small mb-1">Balls Faced</p>
                            <h3 class="fw-bold"><?= (int)$stats['balls_faced'] ?></h3>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box text-center">
                            <p class="cp-muted small mb-1">Strike Rate</p>
                            <h3 class="fw-bold"><?= number_format((float)$stats['strike_rate'], 2) ?></h3>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box text-center">
                            <p class="cp-muted small mb-1">Status</p>
                            <h3 class="fw-bold">
                                <span class="badge <?= (int)$stats['is_out'] ? 'bg-danger' : 'bg-success' ?>">
                                    <?= (int)$stats['is_out'] ? 'Out' : 'In' ?>
                                </span>
                            </h3>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div class="stat-box">
                            <p class="cp-muted small">Fours</p>
                            <p class="fw-bold fs-5"><?= (int)$stats['fours'] ?></p>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box">
                            <p class="cp-muted small">Sixes</p>
                            <p class="fw-bold fs-5"><?= (int)$stats['sixes'] ?></p>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box">
                            <p class="cp-muted small">Caught</p>
                            <p class="fw-bold fs-5"><?= (int)$stats['catches'] ?></p>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box">
                            <p class="cp-muted small">Run Out</p>
                            <p class="fw-bold fs-5"><?= (int)$stats['runouts'] ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bowling Stats -->
            <?php if ((int)$stats['balls_bowled'] > 0 || (int)$stats['wickets'] > 0): ?>
                <div class="cp-card p-4 mb-4">
                    <h4 class="fw-bold mb-3">Bowling Performance</h4>
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <div class="stat-box text-center">
                                <p class="cp-muted small mb-1">Overs</p>
                                <h3 class="fw-bold"><?php
                                    $ballsBowled = (int)$stats['balls_bowled'];
                                    $overs = intdiv($ballsBowled, 6);
                                    $balls = $ballsBowled % 6;
                                    echo $overs . '.' . $balls;
                                ?></h3>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-box text-center">
                                <p class="cp-muted small mb-1">Runs Conceded</p>
                                <h3 class="fw-bold"><?= (int)$stats['runs_conceded'] ?></h3>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-box text-center">
                                <p class="cp-muted small mb-1">Wickets</p>
                                <h3 class="fw-bold"><?= (int)$stats['wickets'] ?></h3>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-box text-center">
                                <p class="cp-muted small mb-1">Economy</p>
                                <h3 class="fw-bold"><?= number_format((float)$stats['economy'], 2) ?></h3>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <div class="stat-box">
                                <p class="cp-muted small">Maiden Overs</p>
                                <p class="fw-bold fs-5"><?= (int)$stats['maiden_overs'] ?></p>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="stat-box">
                                <p class="cp-muted small">Stumpings</p>
                                <p class="fw-bold fs-5"><?= (int)$stats['stumpings'] ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Points Breakdown -->
            <div class="cp-card p-4">
                <h4 class="fw-bold mb-3">Fantasy Points Breakdown</h4>

                <?php
                // Calculate individual components
                $batting_points = FantasyPointsCalculator::calculate_batsman_points(
                    (int)$stats['runs'],
                    (int)$stats['fours'],
                    (int)$stats['sixes'],
                    (int)$stats['balls_faced'],
                    (int)$stats['is_out'] === 1,
                    ((int)$stats['runs'] === 0 && (int)$stats['is_out'] === 1)
                );

                $bowling_points = FantasyPointsCalculator::calculate_bowler_points(
                    (int)$stats['wickets'],
                    (int)$stats['runs_conceded'],
                    (int)$stats['balls_bowled'],
                    (int)$stats['maiden_overs']
                );

                $fielding_points = FantasyPointsCalculator::calculate_fielding_points(
                    (int)$stats['catches'],
                    (int)$stats['runouts'],
                    (int)$stats['stumpings']
                );
                ?>

                <div class="points-breakdown">
                    <div class="breakdown-item">
                        <span class="label">Batting</span>
                        <span class="points bg-info"><?= $batting_points ?></span>
                    </div>
                    <div class="breakdown-item">
                        <span class="label">Bowling</span>
                        <span class="points bg-warning"><?= $bowling_points ?></span>
                    </div>
                    <div class="breakdown-item">
                        <span class="label">Fielding</span>
                        <span class="points bg-secondary"><?= $fielding_points ?></span>
                    </div>
                    <div class="breakdown-item border-top pt-2 mt-2">
                        <span class="label fw-bold">Total</span>
                        <span class="points bg-success fw-bold"><?= $batting_points + $bowling_points + $fielding_points ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-12 col-lg-4">
            <!-- Leaderboard -->
            <div class="cp-card p-4 mb-4">
                <h5 class="fw-bold mb-3">Fantasy Points Leaderboard</h5>
                <div class="leaderboard">
                    <?php foreach ($leaderboard as $idx => $leader): ?>
                        <div class="leaderboard-item <?= $leader['player_id'] === $player_id ? 'active' : '' ?>">
                            <div class="rank"><?= $idx + 1 ?></div>
                            <div class="player-name">
                                <?= h($leader['full_name']) ?>
                                <?php if ($leader['player_id'] === $player_id): ?>
                                    <span class="badge bg-success ms-2">You</span>
                                <?php endif; ?>
                            </div>
                            <div class="points fw-bold"><?= (int)$leader['total_pts'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Match Info -->
            <div class="cp-card p-4">
                <h5 class="fw-bold mb-3">Match Info</h5>
                <div class="match-info">
                    <div class="info-item">
                        <span class="label">Match</span>
                        <span class="value"><?= h($match['match_name']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Date</span>
                        <span class="value"><?= htmlspecialchars($match['match_date']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Innings</span>
                        <span class="value"><?= $innings ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stat-box {
    padding: 1.5rem;
    background-color: #f8f9fa;
    border-radius: 0.5rem;
    border: 1px solid #dee2e6;
}

.stat-box h3 {
    font-size: 1.75rem;
    color: #28a745;
}

.stat-box p {
    margin: 0;
}

.points-breakdown {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.breakdown-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background-color: #f8f9fa;
    border-radius: 0.5rem;
}

.breakdown-item .label {
    font-weight: 600;
    font-size: 1rem;
}

.breakdown-item .points {
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    color: white;
    font-weight: bold;
    min-width: 50px;
    text-align: center;
}

.leaderboard {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.leaderboard-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    background-color: #f8f9fa;
    border-radius: 0.5rem;
    border-left: 4px solid #dee2e6;
}

.leaderboard-item.active {
    background-color: #d4edda;
    border-left-color: #28a745;
}

.leaderboard-item .rank {
    font-weight: bold;
    font-size: 1.25rem;
    color: #666;
    min-width: 2rem;
}

.leaderboard-item .player-name {
    flex: 1;
    font-weight: 600;
}

.leaderboard-item .points {
    font-weight: bold;
    font-size: 1.1rem;
    color: #28a745;
}

.match-info {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.info-item {
    padding-bottom: 1rem;
    border-bottom: 1px solid #dee2e6;
}

.info-item:last-child {
    border-bottom: none;
}

.info-item .label {
    display: block;
    color: #666;
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
}

.info-item .value {
    display: block;
    font-weight: 600;
}

@media (max-width: 768px) {
    .stat-box {
        padding: 1rem;
    }

    .stat-box h3 {
        font-size: 1.5rem;
    }

    .breakdown-item {
        padding: 0.75rem;
    }

    .points-breakdown {
        gap: 0.5rem;
    }
}
</style>

<?php require_once __DIR__ . '/footer.php'; ?>
