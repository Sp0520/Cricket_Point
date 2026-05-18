<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/repo.php';

require_admin();
$pdo = db();

$err = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create_team') {
        $teamName = trim((string)($_POST['team_name'] ?? ''));
        if ($teamName === '') {
            $err = 'Team name is required.';
        } else {
            try {
                $st = $pdo->prepare("INSERT INTO teams (team_name, registration_source) VALUES (:n, 'admin')");
                $st->execute([':n' => $teamName]);
                $ok = 'Team created.';
            } catch (Throwable $e) {
                $err = $e->getMessage();
            }
        }
    }

    if ($action === 'add_member') {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $playerId = (int)($_POST['player_id'] ?? 0);
        if ($teamId <= 0 || $playerId <= 0) {
            $err = 'Invalid team/player.';
        } else {
            try {
                $st = $pdo->prepare('INSERT INTO team_players (team_id, player_id) VALUES (:tid, :pid)');
                $st->execute([':tid' => $teamId, ':pid' => $playerId]);
                $ok = 'Member added.';
            } catch (Throwable $e) {
                $err = $e->getMessage();
            }
        }
    }

    if ($action === 'remove_member') {
        $playerId = (int)($_POST['player_id'] ?? 0);
        if ($playerId <= 0) {
            $err = 'Invalid player.';
        } else {
            $st = $pdo->prepare('DELETE FROM team_players WHERE player_id = :pid');
            $st->execute([':pid' => $playerId]);
            $ok = 'Member removed.';
        }
    }
}

$teams = $pdo->query('SELECT * FROM teams ORDER BY team_name ASC')->fetchAll();
$players = $pdo->query("
  SELECT
    p.id,
    p.full_name,
    t.team_name AS current_team
  FROM players p
  LEFT JOIN team_players tp ON tp.player_id = p.id
  LEFT JOIN teams t ON t.id = tp.team_id
  ORDER BY p.full_name ASC
")->fetchAll();

$membersByTeam = [];
if ($teams) {
    $st = $pdo->query("
      SELECT
        tp.team_id,
        p.id AS player_id,
        p.full_name
      FROM team_players tp
      JOIN players p ON p.id = tp.player_id
      ORDER BY p.full_name ASC
    ");
    foreach ($st->fetchAll() as $row) {
        $tid = (int)$row['team_id'];
        if (!isset($membersByTeam[$tid])) $membersByTeam[$tid] = [];
        $membersByTeam[$tid][] = $row;
    }
}

require_once __DIR__ . '/header.php';
?>

<div class="admin-shell">
  <?php require_once __DIR__ . '/admin_sidebar.php'; ?>
  <div class="flex-grow-1">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
      <div>
        <h2 class="fw-bold mb-1">Teams</h2>
        <div class="cp-muted">Create teams and assign players. Players can then register their team for tournaments.</div>
      </div>
    </div>

    <?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?= h($ok) ?></div><?php endif; ?>

    <div class="cp-card p-4 mb-3">
      <div class="fw-bold mb-2">Create Team</div>
      <form method="post" class="row g-3 align-items-end">
        <input type="hidden" name="action" value="create_team">
        <div class="col-12 col-md-8">
          <label class="form-label">Team Name *</label>
          <input class="form-control" name="team_name" data-autofocus required>
        </div>
        <div class="col-12 col-md-4 d-grid">
          <button class="btn btn-cp" type="submit">Create</button>
        </div>
      </form>
    </div>

    <div class="cp-card p-3 p-md-4 mb-3">
      <div class="fw-bold mb-2">Add Member</div>
      <form method="post" class="row g-3 align-items-end">
        <input type="hidden" name="action" value="add_member">
        <div class="col-12 col-md-5">
          <label class="form-label">Team</label>
          <select class="form-select" name="team_id" required>
            <?php if (!$teams): ?>
              <option value="">No teams</option>
            <?php else: ?>
              <?php foreach ($teams as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= h($t['team_name']) ?></option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>
        <div class="col-12 col-md-7">
          <label class="form-label">Player</label>
          <select class="form-select" name="player_id" required>
            <?php foreach ($players as $p): ?>
              <option value="<?= (int)$p['id'] ?>">
                <?= h($p['full_name']) ?><?= $p['current_team'] ? ' (In: ' . h($p['current_team']) . ')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 d-grid">
          <button class="btn btn-outline-success" type="submit">Add Player</button>
        </div>
      </form>
    </div>

    <div class="cp-card p-3 p-md-4">
      <div class="fw-bold mb-2">All Teams</div>
      <?php if (!$teams): ?>
        <div class="cp-muted py-4 text-center">No teams yet.</div>
      <?php else: ?>
        <div class="row g-3">
          <?php foreach ($teams as $t): ?>
            <?php $tid = (int)$t['id']; ?>
            <div class="col-md-6">
              <div class="cp-card p-3 h-100">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <div class="fw-semibold fs-5"><?= h($t['team_name']) ?></div>
                    <div class="small cp-muted"><?= isset($membersByTeam[$tid]) ? count($membersByTeam[$tid]) : 0 ?> players</div>
                  </div>
                  <span class="badge cp-badge rounded-pill">#<?= $tid ?></span>
                </div>
                <hr class="border-secondary my-3">
                <?php if (empty($membersByTeam[$tid])): ?>
                  <div class="cp-muted py-2">No members yet.</div>
                <?php else: ?>
                  <div class="d-flex flex-column gap-2">
                    <?php foreach ($membersByTeam[$tid] as $m): ?>
                      <div class="d-flex justify-content-between align-items-center gap-2">
                        <div class="fw-semibold"><?= h($m['full_name']) ?></div>
                        <form method="post">
                          <input type="hidden" name="action" value="remove_member">
                          <input type="hidden" name="player_id" value="<?= (int)$m['player_id'] ?>">
                          <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                        </form>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

