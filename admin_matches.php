<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/access.php';

require_admin_or_organizer();

$pdo = db();
$err = '';
$ok = '';

$teams = $pdo->query("
SELECT id, team_name
FROM teams
ORDER BY team_name
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['match_name'] ?? ''));
    $date = (string)($_POST['match_date'] ?? '');
    $time = trim((string)($_POST['match_time'] ?? ''));
    $venue = trim((string)($_POST['venue'] ?? ''));
    $teamAId = (int)($_POST['team_a_id'] ?? 0);
    $teamBId = (int)($_POST['team_b_id'] ?? 0);
    $oversLimit = max(1, (int)($_POST['overs_limit'] ?? 20));
    $wicketsLimit = max(1, (int)($_POST['wickets_limit'] ?? 10));
    $tournamentId = (int)($_POST['tournament_id'] ?? 0);
    $tournamentId = $tournamentId > 0 ? $tournamentId : null;

    if ($name === '' || $date === '' || $teamAId <= 0 || $teamBId <= 0 || $teamAId === $teamBId) {
        $err = 'Match name, date and two different teams are required.';
    } else {
        $ownerId = organizer_owner_user_id();
        if ($tournamentId !== null && is_organizer_user()) {
            $chk = $pdo->prepare('SELECT * FROM tournaments WHERE id = :id LIMIT 1');
            $chk->execute([':id' => $tournamentId]);
            $trow = $chk->fetch();
            if (!$trow || !can_access_tournament_row($trow)) {
                $err = 'You cannot attach this match to that tournament.';
            }
        }
        if ($err === '') {
            $st = $pdo->prepare('
              INSERT INTO matches (match_name, match_date, match_time, venue, tournament_id, team_a_id, team_b_id, overs_limit, wickets_limit, owner_user_id, status)
              VALUES (:n, :d, :tm, :v, :tid, :ta, :tb, :ov, :wk, :oid, :st)
            ');
            $st->execute([
                ':n' => $name,
                ':d' => $date,
                ':tm' => ($time === '' ? null : $time),
                ':v' => ($venue === '' ? null : $venue),
                ':tid' => $tournamentId,
                ':ta' => $teamAId,
                ':tb' => $teamBId,
                ':ov' => $oversLimit,
                ':wk' => $wicketsLimit,
                ':oid' => $ownerId,
                ':st' => 'setup',
            ]);
            $ok = 'Match created.';
        }
    }
}

if (is_organizer_user()) {
    $oid = organizer_owner_user_id();
    $st = $pdo->prepare('SELECT id, tournament_name FROM tournaments WHERE owner_user_id = :o ORDER BY start_date DESC, id DESC');
    $st->execute([':o' => $oid]);
    $tournaments = $st->fetchAll();
} else {
    $tournaments = $pdo->query('SELECT id, tournament_name FROM tournaments ORDER BY start_date DESC, id DESC')->fetchAll();
}

if (is_organizer_user()) {
    $oid = organizer_owner_user_id();
    $st = $pdo->prepare("
      SELECT m.*, p.full_name AS mom_name, tm.tournament_name,
             ta.team_name AS team_a_name,
             tb.team_name AS team_b_name
      FROM matches m
      LEFT JOIN players p ON p.id = m.man_of_match_player_id
      LEFT JOIN tournaments tm ON tm.id = m.tournament_id
      LEFT JOIN teams ta ON ta.id = m.team_a_id
      LEFT JOIN teams tb ON tb.id = m.team_b_id
      WHERE m.owner_user_id = :o
      ORDER BY m.match_date DESC, m.id DESC
    ");
    $st->execute([':o' => $oid]);
    $matches = $st->fetchAll();
} else {
    $matches = $pdo->query("
      SELECT m.*, p.full_name AS mom_name, tm.tournament_name,
             ta.team_name AS team_a_name,
             tb.team_name AS team_b_name
      FROM matches m
      LEFT JOIN players p ON p.id = m.man_of_match_player_id
      LEFT JOIN tournaments tm ON tm.id = m.tournament_id
      LEFT JOIN teams ta ON ta.id = m.team_a_id
      LEFT JOIN teams tb ON tb.id = m.team_b_id
      ORDER BY m.match_date DESC, m.id DESC
    ")->fetchAll();
}

require_once __DIR__ . '/header.php';
$orgShell = is_organizer_user();
?>

<div class="admin-shell">
  <?php if ($orgShell): ?>
    <?php require_once __DIR__ . '/organizer_sidebar.php'; ?>
  <?php else: ?>
    <?php require_once __DIR__ . '/admin_sidebar.php'; ?>
  <?php endif; ?>

  <div class="flex-grow-1">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
      <div>
        <h2 class="fw-bold mb-1"><?= $orgShell ? 'My matches' : 'Matches' ?></h2>
        <div class="cp-muted">Create matches; use the live score board for gully scoring.</div>
      </div>
    </div>

    <?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?= h($ok) ?></div><?php endif; ?>

    <div class="cp-card p-4 mb-3">
      <div class="fw-bold mb-2">Create Match</div>
      <form method="post" class="row g-3">
        <div class="col-md-6">
<label class="form-label">Team A *</label>

<select class="form-select" name="team_a_id" required>

<option value="">Select Team</option>

<?php foreach ($teams as $team): ?>

<option value="<?= $team['id'] ?>" <?= (int)($_POST['team_a_id'] ?? 0) === (int)$team['id'] ? 'selected' : '' ?>>
<?= $team['team_name'] ?>
</option>

<?php endforeach; ?>

</select>

</div>


<div class="col-md-6">
<label class="form-label">Team B *</label>

<select class="form-select" name="team_b_id" required>

<option value="">Select Team</option>

<?php foreach ($teams as $team): ?>

<option value="<?= $team['id'] ?>" <?= (int)($_POST['team_b_id'] ?? 0) === (int)$team['id'] ? 'selected' : '' ?>>
<?= $team['team_name'] ?>
</option>

<?php endforeach; ?>

</select>

</div>
        <div class="col-md-6">
          <label class="form-label">Match Name *</label>
          <input data-autofocus class="form-control" name="match_name" placeholder="Example: Match 1 - Sunday League" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Date *</label>
          <input class="form-control" type="date" name="match_date" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Time</label>
          <input class="form-control" type="time" name="match_time">
        </div>
        <div class="col-md-3">
          <label class="form-label">Overs Limit *</label>
          <input class="form-control" type="number" min="1" name="overs_limit" value="<?= h($_POST['overs_limit'] ?? '20') ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Wickets Limit *</label>
          <input class="form-control" type="number" min="1" name="wickets_limit" value="<?= h($_POST['wickets_limit'] ?? '10') ?>" required>
        </div>
        <div class="col-md-12">
          <label class="form-label">Venue</label>
          <input class="form-control" name="venue" placeholder="Optional">
        </div>
        <div class="col-md-12">
          <label class="form-label">Tournament (optional)</label>
          <select class="form-select" name="tournament_id">
            <option value="">No tournament</option>
            <?php foreach ($tournaments as $t): ?>
              <option value="<?= (int)$t['id'] ?>"><?= h($t['tournament_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12">
          <button class="btn btn-cp" type="submit">Create Match</button>
          <a class="btn btn-outline-light ms-2" href="live_score_board.php">Live score board</a>
        </div>
      </form>
    </div>

    <div class="cp-card p-3 p-md-4">
      <div class="fw-bold mb-2">All Matches</div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Match</th>
              <th>Tournament</th>
              <th>Date</th>
              <th>Time</th>
              <th>Teams</th>
              <th>Overs/Wkts</th>
              <th>Venue</th>
              <th>Man of the Match</th>
              <th class="text-end">MoM Points</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$matches): ?>
              <tr><td colspan="10" class="cp-muted py-4 text-center">No matches yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($matches as $m): ?>
              <tr>
                <td class="cp-muted">#<?= (int)$m['id'] ?></td>
                <td class="fw-semibold"><?= h($m['match_name']) ?></td>
                <td class="cp-muted"><?= h($m['tournament_name'] ?? '—') ?></td>
                <td><?= h($m['match_date']) ?></td>
                <td><?= h($m['match_time'] ?? '—') ?></td>
                <td class="cp-muted"><?= h($m['team_a_name'] ?? '—') ?> vs <?= h($m['team_b_name'] ?? '—') ?></td>
                <td class="cp-muted"><?= (int)($m['overs_limit'] ?? 0) ?>/<?= (int)($m['wickets_limit'] ?? 0) ?></td>
                <td class="cp-muted"><?= h($m['venue'] ?? '') ?></td>
                <td><?= h($m['mom_name'] ?? 'Not decided') ?></td>
                <td class="text-end fw-bold"><?= (int)($m['man_of_match_points'] ?? 0) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

