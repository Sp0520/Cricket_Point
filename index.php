<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/repo.php';

$u = current_user();
$isPlayer = $u && ($u['role'] ?? '') === 'player' && ($u['player_id'] ?? null) !== null;

$player = [];
$rank = null;
$myTeam = null;
if ($isPlayer) {
    $pid = (int)$u['player_id'];
    $player = fetch_player_totals($pid);
    $rank = fetch_player_rank($pid);
    $myTeam = fetch_player_team($pid);
}

$liveMatches = fetch_live_matches(6);
$upcomingMatches = fetch_upcoming_matches(6);

require_once __DIR__ . '/header.php';
?>

<div class="cp-card p-4 p-md-5 mb-4">
  <div class="row align-items-center g-4">
    <div class="col-lg-7">
      <div class="d-flex align-items-center gap-2 mb-3">
        <span class="badge cp-badge rounded-pill px-3 py-2">Player Performance Points</span>
        <span class="badge bg-dark border border-success-subtle rounded-pill px-3 py-2">Auto Leaderboard</span>
      </div>
      <h1 class="display-6 fw-bold mb-3">Track runs, wickets, fielding… and rank players automatically.</h1>
      <p class="cp-muted mb-4">
        Admin enters match performance. The system calculates points instantly, updates the leaderboard, and selects Man of the Match by highest points.
      </p>
      <div class="d-flex flex-wrap gap-2">
        <?php if ($isPlayer): ?>
          <a class="btn btn-cp px-4" href="player_dashboard.php">Player Dashboard</a>
        <?php endif; ?>
        <a class="btn <?= $isPlayer ? 'btn-outline-light' : 'btn-cp' ?> px-4" href="leaderboard.php">View Leaderboard</a>
        <a class="btn btn-outline-light px-4" href="matches.php">Match List</a>
        <a class="btn btn-outline-success px-4" href="register.php">Player Registration</a>
        <?php if (!$u): ?>
          <a class="btn btn-outline-secondary px-4" href="login.php">Login</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="cp-card p-3 border-0">
        <div class="fw-bold mb-2">Scoring Rules</div>
        <div class="row g-2 small cp-muted">
          <div class="col-6">1 run = <span class="text-white fw-semibold">1</span></div>
          <div class="col-6">1 four = <span class="text-white fw-semibold">4</span></div>
          <div class="col-6">1 six = <span class="text-white fw-semibold">8</span></div>
          <div class="col-6">1 wicket = <span class="text-white fw-semibold">10</span></div>
          <div class="col-6">1 catch = <span class="text-white fw-semibold">6</span></div>
          <div class="col-6">1 runout = <span class="text-white fw-semibold">6</span></div>
          <div class="col-6">1 stumping = <span class="text-white fw-semibold">8</span></div>
          <div class="col-6">1 maiden over = <span class="text-white fw-semibold">12</span></div>
        </div>
        <hr class="border-secondary my-3">
        <div class="small cp-muted">Theme: dark + green cricket vibe, fully responsive.</div>
      </div>
    </div>
  </div>
</div>

<?php if ($isPlayer && $player): ?>
  <div class="cp-card p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div class="d-flex align-items-center gap-3">
        <?php if (!empty($player['photo_path'])): ?>
          <img class="player-photo" style="width:64px;height:64px;border-radius:16px;" src="<?= h($player['photo_path']) ?>" alt="">
        <?php else: ?>
          <div class="d-flex align-items-center justify-content-center cp-muted border border-secondary rounded-3" style="width:64px;height:64px;">—</div>
        <?php endif; ?>
        <div>
          <div class="fw-bold fs-5"><?= h($player['full_name'] ?? '') ?></div>
          <div class="small cp-muted">Team: <?= $myTeam ? h($myTeam['team_name']) : 'Not set yet' ?></div>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-3 align-items-center">
        <div class="text-center px-3">
          <div class="cp-muted small">Points</div>
          <div class="fw-bold fs-5"><?= (int)($player['total_points'] ?? 0) ?></div>
        </div>
        <div class="text-center px-3">
          <div class="cp-muted small">Rank</div>
          <div class="fw-bold fs-5"><?= $rank ? ('#' . (int)$rank) : '—' ?></div>
        </div>
        <a class="btn btn-cp" href="player_dashboard.php">Open full dashboard</a>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-12">
    <h2 class="h4 fw-bold mb-3">Match centre</h2>
  </div>
  <div class="col-lg-7">
    <div class="cp-card p-3 p-md-4 h-100">
      <div class="d-flex justify-content-between align-items-end mb-2">
        <div>
          <div class="fw-bold">Live match scores</div>
          <div class="cp-muted small">Today’s matches with score data entered.</div>
        </div>
      </div>
      <?php if (!$liveMatches): ?>
        <div class="cp-muted py-4 text-center">No live matches right now.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Match</th>
                <th>Date</th>
                <th>MoM</th>
                <th class="text-end">Players</th>
                <th class="text-end"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($liveMatches as $m): ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= h($m['match_name']) ?></div>
                    <div class="small cp-muted">
                      <?= h($m['tournament_name'] ?? '—') ?><?= !empty($m['venue']) ? ' • ' . h($m['venue']) : '' ?>
                    </div>
                  </td>
                  <td class="cp-muted"><?= h($m['match_date']) ?></td>
                  <td>
                    <div class="fw-semibold"><?= h($m['mom_name'] ?? '') ?: 'Not decided' ?></div>
                    <div class="small cp-muted">Points: <?= (int)($m['man_of_match_points'] ?? 0) ?></div>
                  </td>
                  <td class="text-end cp-muted"><?= (int)($m['stats_players'] ?? 0) ?></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-success" href="match.php?id=<?= (int)$m['id'] ?>">View</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="cp-card p-3 p-md-4 h-100">
      <div class="mb-2">
        <div class="fw-bold">Upcoming matches</div>
        <div class="cp-muted small">Scheduled after today.</div>
      </div>
      <?php if (!$upcomingMatches): ?>
        <div class="cp-muted py-4 text-center">No upcoming matches yet.</div>
      <?php else: ?>
        <div class="vstack gap-3">
          <?php foreach ($upcomingMatches as $m): ?>
            <div class="cp-card p-3 border border-secondary-subtle">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                  <div class="fw-semibold"><?= h($m['match_name']) ?></div>
                  <div class="small cp-muted">
                    <?= h($m['tournament_name'] ?? '—') ?><?= !empty($m['venue']) ? ' • ' . h($m['venue']) : '' ?>
                  </div>
                </div>
                <span class="badge cp-badge rounded-pill">#<?= (int)$m['id'] ?></span>
              </div>
              <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top border-secondary">
                <div class="small"><span class="cp-muted">Date</span> <span class="fw-semibold ms-1"><?= h($m['match_date']) ?></span></div>
                <a class="btn btn-sm btn-outline-success" href="match.php?id=<?= (int)$m['id'] ?>">Scorecard</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-4">
    <div class="cp-card p-4 h-100">
      <div class="fw-bold mb-2">Players</div>
      <div class="cp-muted">Register, login, view your match-by-match performance, total points and rank.</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="cp-card p-4 h-100">
      <div class="fw-bold mb-2">Admin</div>
      <div class="cp-muted">Add players, create matches, enter performance stats and upload player photos.</div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="cp-card p-4 h-100">
      <div class="fw-bold mb-2">Man of the Match</div>
      <div class="cp-muted">Automatically selected per match by highest points.</div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

