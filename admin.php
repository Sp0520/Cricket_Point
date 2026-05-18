<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/repo.php';

require_admin();

require_once __DIR__ . '/header.php';

$counts = [
    'players' => (int)db()->query('SELECT COUNT(*) c FROM players')->fetch()['c'],
    'matches' => (int)db()->query('SELECT COUNT(*) c FROM matches')->fetch()['c'],
    'stats' => (int)db()->query('SELECT COUNT(*) c FROM player_match_stats')->fetch()['c'],
];
?>

<div class="admin-shell">
  <?php require_once __DIR__ . '/admin_sidebar.php'; ?>

  <div class="flex-grow-1">
    <div class="cp-card p-4 p-md-5 mb-3">
      <h2 class="fw-bold mb-1">Admin Dashboard</h2>
      <div class="cp-muted">Manage players, matches and performance points.</div>
      <hr class="border-secondary my-4">
      <div class="row g-3">
        <div class="col-md-4">
          <div class="cp-card p-4 border-0">
            <div class="cp-muted small">Players</div>
            <div class="fw-bold fs-3"><?= (int)$counts['players'] ?></div>
            <a class="btn btn-sm btn-outline-success mt-2" href="admin_players.php">Manage</a>
          </div>
        </div>
        <div class="col-md-4">
          <div class="cp-card p-4 border-0">
            <div class="cp-muted small">Matches</div>
            <div class="fw-bold fs-3"><?= (int)$counts['matches'] ?></div>
            <a class="btn btn-sm btn-outline-success mt-2" href="admin_matches.php">Manage</a>
          </div>
        </div>
        <div class="col-md-4">
          <div class="cp-card p-4 border-0">
            <div class="cp-muted small">Stats Rows</div>
            <div class="fw-bold fs-3"><?= (int)$counts['stats'] ?></div>
            <a class="btn btn-sm btn-outline-success mt-2" href="live_score_board.php">Live Boards</a>
          </div>
        </div>
      </div>

      <hr class="border-secondary my-4">

      <h5 class="fw-bold mb-3">Live Match Scoring System</h5>
      <div class="row g-3">
        <div class="col-md-6">
          <div class="cp-card p-4 border-0 bg-light">
            <div class="fw-bold mb-2"><i class="fas fa-cog"></i> Match Setup</div>
            <p class="small cp-muted mb-3">Configure batting/bowling teams and select players for match.</p>
            <a class="btn btn-sm btn-primary" href="admin/scoring/setup.php">Go to Setup</a>
          </div>
        </div>
        <div class="col-md-6">
          <div class="cp-card p-4 border-0 bg-light">
            <div class="fw-bold mb-2"><i class="fas fa-broadcast-tower"></i> Live Scoring</div>
            <p class="small cp-muted mb-3">Record ball-by-ball scoring with automatic fantasy points calculation.</p>
            <a class="btn btn-sm btn-danger" href="admin/scoring/live_scoring.php">Start Scoring</a>
          </div>
        </div>
      </div>
    </div>

    <div class="cp-card p-4">
      <div class="fw-bold mb-1">Quick Tips</div>
      <ul class="cp-muted mb-0">
        <li>Create players first.</li>
        <li>Create a match.</li>
        <li>Use the Live score board for ball-by-ball scoring on each match.</li>
        <li>Classic player stats / leaderboard are unchanged if you add data elsewhere.</li>
      </ul>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

