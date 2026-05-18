<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/repo.php';
require_once __DIR__ . '/contact_util.php';

require_login();
require_verified();
$u = current_user();
if (($u['role'] ?? '') !== 'player' || empty($u['player_id'])) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}
$playerId = (int)$u['player_id'];

$tournamentId = (int)($_GET['tournament_id'] ?? 0);
if ($tournamentId <= 0) {
    header('Location: player_dashboard.php?msg=invalid');
    exit;
}

$t = fetch_tournament($tournamentId);
if (!$t) {
    header('Location: player_dashboard.php?msg=notfound');
    exit;
}

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$from = $t['registration_open_from'] ?? null;
$to = $t['registration_open_to'] ?? null;
$open = $from !== null
    && $from <= $today
    && ($to === null || $to >= $today);

if (!$open) {
    header('Location: player_dashboard.php?msg=closed');
    exit;
}

$myTeam = fetch_player_team($playerId);
if (!$myTeam) {
    header('Location: player_teams.php?msg=needteam');
    exit;
}

$teamId = (int)$myTeam['id'];
$pdo = db();
$st = $pdo->prepare('SELECT contact_phone, created_by_player_id FROM teams WHERE id = :id LIMIT 1');
$st->execute([':id' => $teamId]);
$teamRow = $st->fetch() ?: ['contact_phone' => '', 'created_by_player_id' => null];

$st = $pdo->prepare('SELECT full_name FROM players WHERE id = :id LIMIT 1');
$st->execute([':id' => $playerId]);
$captainName = (string)($st->fetch()['full_name'] ?? $u['name'] ?? '');

$st = $pdo->prepare("
  SELECT id FROM tournament_registrations
  WHERE tournament_id = :tid AND registrant_type = 'team' AND team_id = :teamId
  LIMIT 1
");
$st->execute([':tid' => $tournamentId, ':teamId' => $teamId]);
if ($st->fetch()) {
    header('Location: player_dashboard.php?msg=already');
    exit;
}

$flash = (string)($_GET['msg'] ?? '');
$flashText = '';
if ($flash === 'badphone') {
    $flashText = 'Enter a valid contact number (at least 8 digits).';
}

$prefillPhone = (string)($teamRow['contact_phone'] ?? '');

require_once __DIR__ . '/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="cp-card p-4 p-md-5">
      <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
        <div>
          <h2 class="fw-bold mb-1">Register team in tournament</h2>
          <div class="cp-muted"><?= h((string)$t['tournament_name']) ?></div>
        </div>
        <a class="btn btn-outline-light btn-sm" href="player_dashboard.php">Back</a>
      </div>

      <?php if ($flashText): ?>
        <div class="alert alert-warning"><?= h($flashText) ?></div>
      <?php endif; ?>

      <div class="cp-card p-3 border-0 mb-4">
        <div class="small cp-muted mb-1">Captain (your player name)</div>
        <div class="fw-semibold fs-5"><?= h($captainName) ?></div>
        <div class="small cp-muted mt-3 mb-1">Team</div>
        <div class="fw-semibold"><?= h($myTeam['team_name']) ?></div>
        <div class="cp-muted small mt-2">Your name is on the squad as captain. Contact number is required for organizers to reach you.</div>
      </div>

      <form method="post" action="player_register_tournament.php" class="row g-3">
        <input type="hidden" name="tournament_id" value="<?= (int)$tournamentId ?>">
        <input type="hidden" name="registrant_type" value="team">
        <div class="col-12">
          <label class="form-label">Contact number *</label>
          <input class="form-control" type="tel" name="contact_phone" value="<?= h($prefillPhone) ?>"
                 placeholder="e.g. +91 98765 43210" required autocomplete="tel">
          <div class="form-text cp-muted">At least 8 digits. Saved on your team and with this tournament entry.</div>
        </div>
        <div class="col-12 d-grid">
          <button class="btn btn-cp btn-lg" type="submit">Confirm and register team</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
