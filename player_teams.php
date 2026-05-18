<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/repo.php';
require_once __DIR__ . '/contact_util.php';

require_login();
$u = current_user();
if (($u['role'] ?? '') !== 'player' || empty($u['player_id'])) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}
$playerId = (int)$u['player_id'];

$sessionKey = 'team_creation_' . $playerId;

$err = '';
$ok = '';
$step = 1; // Step 1: Team Details, Step 2: Add Players

// Check if user already has a team
$myTeam = fetch_player_team($playerId);
if ($myTeam) {
    $step = 'existing';
}

// Initialize session data if not present
if (!isset($_SESSION[$sessionKey])) {
    $_SESSION[$sessionKey] = [
        'team_name' => '',
        'logo_url' => '',
        'contact_phone' => '',
        'players' => []
    ];
}

// HANDLE: Start team creation (Step 1)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start_team_creation') {
    $teamName = trim((string)($_POST['team_name'] ?? ''));
    $logoUrl = trim((string)($_POST['logo_url'] ?? ''));
    $contactPhone = normalized_contact_phone((string)($_POST['contact_phone'] ?? ''));

    if ($teamName === '') {
        $err = 'Team name is required.';
    } elseif ($contactPhone === null) {
        $err = 'A valid contact number is required (at least 8 digits).';
    } else {
        // Store in session
        $_SESSION[$sessionKey]['team_name'] = $teamName;
        $_SESSION[$sessionKey]['logo_url'] = $logoUrl;
        $_SESSION[$sessionKey]['contact_phone'] = $contactPhone;
        $step = 2;
    }
}

// HANDLE: Add player to temporary list
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_temp_player') {
    $playerName = trim((string)($_POST['player_name'] ?? ''));
    $playerRole = (string)($_POST['player_role'] ?? 'Batsman');
    $jerseyNum = trim((string)($_POST['jersey_number'] ?? ''));
    $allowedRoles = ['Batsman', 'Bowler', 'All-rounder', 'Wicketkeeper'];

    if ($playerName === '') {
        $err = 'Player name is required.';
    } elseif (!in_array($playerRole, $allowedRoles, true)) {
        $err = 'Invalid player role.';
    } else {
        // Check for duplicate names in this team
        $isDuplicate = false;
        foreach ($_SESSION[$sessionKey]['players'] as $p) {
            if (mb_strtolower($p['name'], 'UTF-8') === mb_strtolower($playerName, 'UTF-8')) {
                $isDuplicate = true;
                break;
            }
        }

        if ($isDuplicate) {
            $err = 'Player with this name already added to team.';
        } else {
            $captainName = (string)($u['name'] ?? '');
            if ($captainName !== '' && mb_strtolower($playerName, 'UTF-8') === mb_strtolower($captainName, 'UTF-8')) {
                $err = 'You are already the captain and will be added automatically; do not add yourself here.';
            } else {
                // Ensure this existing player isn't already in another team
                $existingTeamCheck = db()->prepare('SELECT tp.team_id FROM team_players tp JOIN players p ON p.id = tp.player_id WHERE LOWER(p.full_name) = LOWER(:name) LIMIT 1');
                $existingTeamCheck->execute([':name' => $playerName]);
                $existingTeam = $existingTeamCheck->fetch();
                if ($existingTeam) {
                    $err = 'Player "' . $playerName . '" is already assigned to another team and cannot be added again.';
                } else {
                    $_SESSION[$sessionKey]['players'][] = [
                        'name' => $playerName,
                        'role' => $playerRole,
                        'jersey' => $jerseyNum === '' ? null : (int)$jerseyNum
                    ];
                    $ok = 'Player added to team (Total: ' . count($_SESSION[$sessionKey]['players']) . ' / 3)';
                }
            }
        }
    }
    $step = 2;
}

// HANDLE: Remove player from temporary list
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove_temp_player') {
    $indexToRemove = (int)($_POST['player_index'] ?? -1);
    if ($indexToRemove >= 0 && $indexToRemove < count($_SESSION[$sessionKey]['players'])) {
        array_splice($_SESSION[$sessionKey]['players'], $indexToRemove, 1);
        $ok = 'Player removed from team.';
    }
    $step = 2;
}

// HANDLE: Final team creation (save to database)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'finalize_team_creation') {
    $teamData = $_SESSION[$sessionKey];
    $playerCount = count($teamData['players']);

    if ($playerCount < 3) {
        $err = "Please add minimum 3 players to create team (currently {$playerCount}).";
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // 0. Ensure captain player exists in players table
            $checkCaptain = $pdo->prepare('SELECT id FROM players WHERE id = :id LIMIT 1');
            $checkCaptain->execute([':id' => $playerId]);
            if (!$checkCaptain->fetch()) {
                // Create player record for captain if doesn't exist
                $captainName = (string)($u['name'] ?? 'Player_' . $playerId);
                $insertCaptain = $pdo->prepare('INSERT INTO players (id, full_name) VALUES (:id, :name)');
                $insertCaptain->execute([':id' => $playerId, ':name' => $captainName]);
            }

            // 1. Create team
            $st = $pdo->prepare('
              INSERT INTO teams (team_name, created_by_player_id, registration_source, contact_phone, logo_path)
              VALUES (:n, :pid, \'captain\', :ph, :logo)
            ');
            $st->execute([
                ':n' => $teamData['team_name'],
                ':pid' => $playerId,
                ':ph' => $teamData['contact_phone'],
                ':logo' => ($teamData['logo_url'] === '' ? null : $teamData['logo_url'])
            ]);
            $teamId = (int)$pdo->lastInsertId();

            // 2. Add captain as first player
            $captainPlayerRow = $pdo->prepare('SELECT full_name FROM players WHERE id = :id LIMIT 1');
            $captainPlayerRow->execute([':id' => $playerId]);
            $captainInfo = $captainPlayerRow->fetch();
            
            $st = $pdo->prepare('INSERT INTO team_players (team_id, player_id, role, jersey_number) VALUES (:tid, :pid, :role, :jer)');
            $st->execute([
                ':tid' => $teamId,
                ':pid' => $playerId,
                ':role' => 'All-rounder',
                ':jer' => null
            ]);

            // 3. Add all temporary players to database
            $tempPlayerCount = count($teamData['players']);
            $addedPlayers = 1; // captain already added
            foreach ($teamData['players'] as $tempPlayer) {
                // Create player record if doesn't exist
                $checkPlayer = $pdo->prepare('SELECT id FROM players WHERE LOWER(full_name) = LOWER(:name) LIMIT 1');
                $checkPlayer->execute([':name' => $tempPlayer['name']]);
                $existingPlayer = $checkPlayer->fetch();

                if ($existingPlayer) {
                    $newPlayerId = (int)$existingPlayer['id'];
                } else {
                    $insertPlayer = $pdo->prepare('INSERT INTO players (full_name) VALUES (:name)');
                    $insertPlayer->execute([':name' => $tempPlayer['name']]);
                    $newPlayerId = (int)$pdo->lastInsertId();
                }

                // Skip if this is the captain (already linked)
                if ($newPlayerId === $playerId) {
                    continue;
                }

                // Check if player is already in another team (unique constraint on team_players.player_id)
                $checkExisting = $pdo->prepare('SELECT team_id FROM team_players WHERE player_id = :pid LIMIT 1');
                $checkExisting->execute([':pid' => $newPlayerId]);
                $existingTeam = $checkExisting->fetch();
                if ($existingTeam) {
                    throw new RuntimeException('Player "' . $tempPlayer['name'] . '" is already a member of another team. Remove or choose a different player.');
                }

                // Avoid inserting duplicate team-player relationships (just in case)
                $checkTeamPlayer = $pdo->prepare('SELECT 1 FROM team_players WHERE team_id = :tid AND player_id = :pid LIMIT 1');
                $checkTeamPlayer->execute([':tid' => $teamId, ':pid' => $newPlayerId]);
                if ($checkTeamPlayer->fetch()) {
                    continue;
                }

                // Link player to team
                $st = $pdo->prepare('INSERT INTO team_players (team_id, player_id, role, jersey_number) VALUES (:tid, :pid, :role, :jer)');
                $st->execute([
                    ':tid' => $teamId,
                    ':pid' => $newPlayerId,
                    ':role' => $tempPlayer['role'],
                    ':jer' => $tempPlayer['jersey']
                ]);

                $addedPlayers++;
            }

            $pdo->commit();

            // Clear session
            unset($_SESSION[$sessionKey]);

            $ok = "Team created successfully with " . $addedPlayers . " players!";
            $step = 'existing';
            $myTeam = fetch_player_team($playerId);

        } catch (Throwable $e) {
            $pdo->rollBack();
            $err = $e->getMessage();
            $step = 2;
        }
    }
}

// HANDLE: Cancel team creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_team_creation') {
    unset($_SESSION[$sessionKey]);
    header('Location: player_dashboard.php');
    exit;
}

require_once __DIR__ . '/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-9">
    <div class="cp-card p-4 p-md-5 mb-3">
      <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
          <h2 class="fw-bold mb-1">My Team</h2>
          <div class="cp-muted">Create your team with minimum 3 players, then register in tournaments.</div>
        </div>
        <a class="btn btn-outline-light" href="player_dashboard.php">Back to Dashboard</a>
      </div>

      <hr class="border-secondary my-4">

      <?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>
      <?php if ($ok): ?><div class="alert alert-success"><?= h($ok) ?></div><?php endif; ?>

      <!-- EXISTING TEAM VIEW -->
      <?php if ($step === 'existing' && $myTeam): ?>
        <div class="cp-card p-3 border-0">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
              <div class="cp-muted small">Your team</div>
              <div class="fw-bold fs-4"><?= h($myTeam['team_name']) ?></div>
              <?php if (!empty($myTeam['logo_path'])): ?>
                <img src="<?= h(url_for($myTeam['logo_path'])) ?>" alt="Team Logo" style="max-height: 70px; margin-top: 8px;">
              <?php endif; ?>
            </div>
          </div>

          <?php
            $st = db()->prepare('SELECT full_name FROM players WHERE id = :id LIMIT 1');
            $st->execute([':id' => $playerId]);
            $captainDisplay = (string)($st->fetch()['full_name'] ?? '');
          ?>
          <div class="mt-3">
            <div class="cp-muted small mb-1">Captain</div>
            <div class="fw-semibold"><?= h($captainDisplay) ?></div>
          </div>

          <div class="mt-3">
            <div class="cp-muted small mb-1">Contact Number</div>
            <div class="fw-semibold"><?= h((string)$myTeam['contact_phone']) ?></div>
          </div>

          <?php
            $squadSt = db()->prepare("
              SELECT p.id, p.full_name, tp.role, tp.jersey_number
              FROM team_players tp
              JOIN players p ON p.id = tp.player_id
              WHERE tp.team_id = :tid
              ORDER BY p.full_name ASC
            ");
            $squadSt->execute([':tid' => (int)$myTeam['id']]);
            $squadMembers = $squadSt->fetchAll();
          ?>

          <?php if ($squadMembers): ?>
            <div class="mt-4">
              <div class="cp-muted small mb-2">Squad (' . count($squadMembers) . ' players)</div>
              <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Player Name</th>
                      <th>Role</th>
                      <th>Jersey #</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($squadMembers as $member): ?>
                      <tr>
                        <td class="fw-semibold"><?= h($member['full_name']) ?></td>
                        <td><span class="badge bg-secondary"><?= h($member['role']) ?></span></td>
                        <td><?= !empty($member['jersey_number']) ? (int)$member['jersey_number'] : '—' ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php endif; ?>

          <?php
            $regSt = db()->prepare("
              SELECT tr.status, t.tournament_name, t.id AS tournament_id
              FROM tournament_registrations tr
              JOIN tournaments t ON t.id = tr.tournament_id
              WHERE tr.team_id = :tid AND tr.registrant_type = 'team'
              ORDER BY t.start_date DESC
            ");
            $regSt->execute([':tid' => (int)$myTeam['id']]);
            $tournamentTeamRegs = $regSt->fetchAll();
          ?>

          <?php if ($tournamentTeamRegs): ?>
            <div class="mt-4">
              <div class="cp-muted small mb-2">Tournament Registrations</div>
              <ul class="mb-0 ps-3">
                <?php foreach ($tournamentTeamRegs as $tr): ?>
                  <li class="mb-1">
                    <?= h($tr['tournament_name']) ?>
                    <span class="badge rounded-pill" style="background-color: #6c757d; color: #fff;"><?= h((string)$tr['status']) ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </div>

      <!-- STEP 1: TEAM DETAILS FORM -->
      <?php elseif ($step === 1): ?>
        <div class="cp-card p-4 border-0">
          <h4 class="fw-bold mb-3">Step 1: Enter Team Information</h4>
          <div class="cp-muted small mb-4">Fill in your team details below. After submission, you'll add players to your team.</div>

          <form method="post" class="row g-3">
            <input type="hidden" name="action" value="start_team_creation">

            <div class="col-12">
              <label class="form-label">Team Name *</label>
              <input class="form-control form-control-lg" data-autofocus name="team_name" 
                     value="<?= h($_SESSION[$sessionKey]['team_name'] ?? '') ?>" required
                     placeholder="e.g. Thunder Warriors">
            </div>

            <div class="col-12">
              <label class="form-label">Team Logo URL</label>
              <input class="form-control" type="url" name="logo_url"
                     value="<?= h($_SESSION[$sessionKey]['logo_url'] ?? '') ?>"
                     placeholder="https://example.com/logo.png">
            </div>

            <div class="col-12">
              <label class="form-label">Contact Number *</label>
              <input class="form-control" type="tel" name="contact_phone"
                     value="<?= h($_SESSION[$sessionKey]['contact_phone'] ?? '') ?>"
                     placeholder="e.g. +91 98765 43210" required autocomplete="tel">
              <div class="form-text cp-muted">At least 8 digits required.</div>
            </div>

            <div class="col-12 d-grid gap-2">
              <button class="btn btn-cp btn-lg" type="submit">Next: Add Players</button>
              <a class="btn btn-outline-secondary" href="player_dashboard.php">Cancel</a>
            </div>
          </form>
        </div>

      <!-- STEP 2: ADD PLAYERS -->
      <?php elseif ($step === 2): ?>
        <div class="cp-card p-4 border-0">
          <!-- TEAM INFO SUMMARY -->
          <div class="alert alert-info mb-4">
            <strong>Team Information:</strong> <?= h($_SESSION[$sessionKey]['team_name']) ?><br>
            <small class="cp-muted">Contact: <?= h($_SESSION[$sessionKey]['contact_phone']) ?></small>
          </div>

          <h4 class="fw-bold mb-2">Step 2: Add Team Players</h4>
          <div class="cp-muted small mb-3">You must add minimum <strong>3 players</strong> to create your team.</div>

          <!-- PLAYER COUNTER -->
          <div class="alert alert-warning d-flex justify-content-between align-items-center mb-4">
            <span>Players Added: <strong><?= count($_SESSION[$sessionKey]['players']) ?> / 3</strong></span>
            <?php if (count($_SESSION[$sessionKey]['players']) >= 3): ?>
              <span class="badge bg-success">✓ Ready to Create Team</span>
            <?php else: ?>
              <span class="badge bg-secondary">Add <?= 3 - count($_SESSION[$sessionKey]['players']) ?> more</span>
            <?php endif; ?>
          </div>

          <!-- ADD PLAYER FORM -->
          <form method="post" class="row g-3 mb-4 p-3 bg-light border rounded">
            <input type="hidden" name="action" value="add_temp_player">
            <h5 class="col-12">Add a Player</h5>

            <div class="col-12 col-md-5">
              <label class="form-label small">Player Name *</label>
              <input class="form-control" type="text" name="player_name" placeholder="Full name" required>
            </div>

            <div class="col-12 col-md-3">
              <label class="form-label small">Role *</label>
              <select class="form-select" name="player_role" required>
                <option value="Batsman">Batsman</option>
                <option value="Bowler">Bowler</option>
                <option value="All-rounder">All-rounder</option>
                <option value="Wicketkeeper">Wicketkeeper</option>
              </select>
            </div>

            <div class="col-12 col-md-2">
              <label class="form-label small">Jersey #</label>
              <input class="form-control" type="number" name="jersey_number" min="1" max="99" placeholder="Optional">
            </div>

            <div class="col-12 col-md-2 d-grid">
              <button class="btn btn-success" type="submit">Add Player</button>
            </div>
          </form>

          <!-- PLAYERS LIST TABLE -->
          <?php if (!empty($_SESSION[$sessionKey]['players'])): ?>
            <div class="mt-4 mb-4">
              <h5 class="fw-bold mb-2">Added Players (<?= count($_SESSION[$sessionKey]['players']) ?>)</h5>
              <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th class="text-center" style="width: 50px;">#</th>
                      <th>Player Name</th>
                      <th>Role</th>
                      <th>Jersey #</th>
                      <th class="text-center" style="width: 80px;">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($_SESSION[$sessionKey]['players'] as $idx => $player): ?>
                      <tr>
                        <td class="text-center cp-muted"><?= $idx + 1 ?></td>
                        <td class="fw-semibold"><?= h($player['name']) ?></td>
                        <td><span class="badge bg-info"><?= h($player['role']) ?></span></td>
                        <td><?= !empty($player['jersey']) ? (int)$player['jersey'] : '—' ?></td>
                        <td class="text-center">
                          <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="remove_temp_player">
                            <input type="hidden" name="player_index" value="<?= $idx ?>">
                            <button class="btn btn-sm btn-danger" type="submit" title="Remove player">✕</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php else: ?>
            <div class="alert alert-info">No players added yet. Use the form above to add players.</div>
          <?php endif; ?>

          <!-- FINAL CREATION BUTTON -->
          <div class="d-grid gap-2 mt-4">
            <?php $playerCount = count($_SESSION[$sessionKey]['players']); ?>
            <?php if ($playerCount >= 3): ?>
              <form method="post">
                <input type="hidden" name="action" value="finalize_team_creation">
                <button class="btn btn-success btn-lg" type="submit">
                  ✓ Create Team with <?= $playerCount ?> Players
                </button>
              </form>
            <?php else: ?>
              <button class="btn btn-secondary btn-lg" type="button" disabled>
                Create Team (<?= $playerCount . ' / 3' ?> players) - Add More Players
              </button>
            <?php endif; ?>

            <form method="post" class="mt-2">
              <input type="hidden" name="action" value="cancel_team_creation">
              <button class="btn btn-outline-secondary" type="submit">Cancel & Go Back</button>
            </form>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

