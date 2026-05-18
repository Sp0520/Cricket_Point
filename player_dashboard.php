<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/repo.php';

require_login();
require_verified();
$u = current_user();
if (($u['role'] ?? '') !== 'player' || empty($u['player_id'])) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$playerId = (int)$u['player_id'];
$teamId = null;
$myTeam = fetch_player_team($playerId);
if ($myTeam) $teamId = (int)$myTeam['id'];

$player = fetch_player_totals($playerId);
$rank = fetch_player_rank($playerId);

$liveMatches = fetch_live_matches(6);
$upcomingMatches = fetch_upcoming_matches(8);
$tournaments = fetch_upcoming_tournaments_for_player($playerId, $teamId, 10);

$msg = (string)($_GET['msg'] ?? '');
$flash = '';
if ($msg === 'registered') $flash = 'Registration submitted successfully.';
if ($msg === 'already') $flash = 'You are already registered for this tournament.';
if ($msg === 'closed') $flash = 'Registration is currently closed for this tournament.';
if ($msg === 'needteam') $flash = 'Create/select your team first to register as a team.';
if ($msg === 'invalid') $flash = 'Invalid request.';
if ($msg === 'notfound') $flash = 'Tournament not found.';
if ($msg === 'error') $flash = 'Something went wrong.';

require_once __DIR__ . '/header.php';
?>

<div class="cp-card p-4 p-md-5 mb-4">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
      <?php if (!empty($player['photo_path'])): ?>
        <img class="player-photo" style="width:78px;height:78px;border-radius:18px;" src="<?= h(url_for((string)$player['photo_path'])) ?>" alt="photo">
      <?php else: ?>
        <div class="player-photo d-flex align-items-center justify-content-center cp-muted" style="width:78px;height:78px;border-radius:18px;">N/A</div>
      <?php endif; ?>
      <div>
        <h2 class="fw-bold mb-1"><?= h($player['full_name'] ?? '') ?></h2>
        <div class="cp-muted">Player ID: <?= (int)($player['id'] ?? 0) ?></div>
        <div class="cp-muted small mt-1">
          Team: <?= $myTeam ? h($myTeam['team_name']) : '<span class="cp-muted">Not created yet</span>' ?>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <div class="cp-card p-3 border-0">
        <div class="cp-muted small">Total Points</div>
        <div class="fw-bold fs-4"><?= (int)($player['total_points'] ?? 0) ?></div>
      </div>
      <div class="cp-card p-3 border-0">
        <div class="cp-muted small">Rank</div>
        <div class="fw-bold fs-4"><?= $rank ? ('#' . (int)$rank) : '—' ?></div>
      </div>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-info mt-3 mb-0"><?= h($flash) ?></div>
  <?php endif; ?>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="cp-card p-3 p-md-4 mb-3">
      <div class="d-flex justify-content-between align-items-end mb-2">
        <div>
          <div class="fw-bold">Live Scores</div>
          <div class="cp-muted small">Matches today where score data is available.</div>
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
                <th class="text-end"> </th>
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

    <div class="cp-card p-3 p-md-4">
      <div class="d-flex justify-content-between align-items-end mb-2">
        <div>
          <div class="fw-bold">Upcoming Matches</div>
          <div class="cp-muted small">Matches that are scheduled after today.</div>
        </div>
      </div>

      <?php if (!$upcomingMatches): ?>
        <div class="cp-muted py-4 text-center">No upcoming matches yet.</div>
      <?php else: ?>
        <div class="row g-3">
          <?php foreach ($upcomingMatches as $m): ?>
            <div class="col-md-6">
              <div class="cp-card p-3 h-100">
                <div class="d-flex justify-content-between align-items-start gap-2">
                  <div>
                    <div class="fw-semibold"><?= h($m['match_name']) ?></div>
                    <div class="small cp-muted">
                      <?= h($m['tournament_name'] ?? '—') ?><?= !empty($m['venue']) ? ' • ' . h($m['venue']) : '' ?>
                    </div>
                  </div>
                  <span class="badge cp-badge rounded-pill">#<?= (int)$m['id'] ?></span>
                </div>
                <hr class="border-secondary my-3">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <div class="cp-muted small">Date</div>
                    <div class="fw-semibold"><?= h($m['match_date']) ?></div>
                  </div>
                  <div class="text-end">
                    <div class="cp-muted small">Status</div>
                    <div class="fw-semibold"><?= (int)($m['stats_players'] ?? 0) > 0 ? 'Score started' : 'Scheduled' ?></div>
                  </div>
                </div>
                <div class="mt-3 d-grid">
                  <a class="btn btn-outline-success" href="match.php?id=<?= (int)$m['id'] ?>">View Scorecard</a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="cp-card p-3 p-md-4 mb-3">
      <div class="d-flex justify-content-between align-items-end mb-2">
        <div>
          <div class="fw-bold">Upcoming Tournaments</div>
          <div class="cp-muted small">Register when admin opens the registration window.</div>
        </div>
      </div>

      <?php if (!$tournaments): ?>
        <div class="cp-muted py-4 text-center">No tournaments found.</div>
      <?php else: ?>
        <div class="row g-3">
          <?php foreach ($tournaments as $t): ?>
            <div class="col-12">
              <div class="cp-card p-3 h-100">
                <div class="d-flex justify-content-between align-items-start gap-2">
                  <div>
                    <div class="fw-semibold fs-5 mb-1"><?= h($t['tournament_name']) ?></div>
                    <div class="small cp-muted">
                      <?= h($t['start_date']) ?>
                      <?= !empty($t['end_date']) ? ' - ' . h($t['end_date']) : '' ?>
                      <?= !empty($t['venue']) ? ' • ' . h($t['venue']) : '' ?>
                    </div>                    <div class="small cp-muted">Entry fees: $<?= number_format((float)$t['entry_fees'] ?? 0, 2) ?> • Max teams: <?= (int)($t['max_teams'] ?? 0) ?> • Overs: <?= (int)($t['overs_per_match'] ?? 20) ?>, Wickets: <?= (int)($t['wickets_per_team'] ?? 10) ?></div>
                    <?php if (!empty($t['description'])): ?>
                      <div class="small cp-muted"><?= h($t['description']) ?></div>
                    <?php endif; ?>                  </div>
                  <?php if ((int)$t['is_registration_open'] === 1): ?>
                    <span class="badge cp-badge rounded-pill">Open</span>
                  <?php else: ?>
                    <span class="badge bg-secondary-subtle text-light rounded-pill border">Closed</span>
                  <?php endif; ?>
                </div>

                <hr class="border-secondary my-3">

                <?php
                  $playerAlready = !empty($t['player_reg_id']);
                  $teamAlready = !empty($t['team_reg_id']);
                ?>

                <div class="small cp-muted mb-2">
                  Player: <?= $playerAlready ? h((string)$t['player_reg_status']) : 'Not registered' ?>
                </div>
                <div class="small cp-muted mb-3">
                  Team: <?= $teamAlready ? h((string)$t['team_reg_status']) : ($teamId ? 'Not registered' : 'No team') ?>
                </div>

                <div class="d-flex flex-wrap gap-2">
                  <?php if ((int)$t['is_registration_open'] === 1 && !$playerAlready): ?>
                    <form method="post" action="player_register_tournament.php" class="d-inline">
                      <input type="hidden" name="tournament_id" value="<?= (int)$t['id'] ?>">
                      <input type="hidden" name="registrant_type" value="player">
                      <button class="btn btn-cp btn-sm" type="submit">Register as Player</button>
                    </form>
                  <?php elseif ($playerAlready): ?>
                    <button class="btn btn-outline-light btn-sm" type="button" disabled>Player Registered</button>
                  <?php endif; ?>

                  <?php if ((int)$t['is_registration_open'] === 1 && $teamId && !$teamAlready): ?>
                    <a class="btn btn-outline-success btn-sm" href="team_tournament_register.php?tournament_id=<?= (int)$t['id'] ?>">Register my team</a>
                  <?php elseif ($teamId && $teamAlready): ?>
                    <button class="btn btn-outline-light btn-sm" type="button" disabled>Team Registered</button>
                  <?php elseif (!$teamId): ?>
                    <a class="btn btn-outline-success btn-sm" href="player_teams.php">Create Team</a>
                  <?php endif; ?>
                </div>

              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

