<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/repo.php';
require_once __DIR__ . '/access.php';

require_admin_or_organizer();
$pdo = db();
$err = '';
$ok = '';
$today = (new DateTimeImmutable('today'))->format('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        $name = trim((string)($_POST['tournament_name'] ?? ''));
        $startDate = (string)($_POST['start_date'] ?? '');
        $endDate = trim((string)($_POST['end_date'] ?? ''));
        $venue = trim((string)($_POST['venue'] ?? ''));
        $entryFees = (float)($_POST['entry_fees'] ?? 0);
        $description = trim((string)($_POST['description'] ?? ''));
        $maxTeams = max(0, (int)($_POST['max_teams'] ?? 16));
        $oversPerMatch = max(1, (int)($_POST['overs_per_match'] ?? 20));
        $wicketsPerTeam = max(1, (int)($_POST['wickets_per_team'] ?? 10));
        $registrationEnabled = ($_POST['registration_enabled'] ?? '') === '1';
        $regFrom = trim((string)($_POST['registration_open_from'] ?? ''));
        $regTo = trim((string)($_POST['registration_open_to'] ?? ''));

        if ($name === '' || $startDate === '') {
            $err = 'Tournament name and start date are required.';
        } else {
            $pdo->beginTransaction();
            try {
                $ownerId = organizer_owner_user_id();
                $st = $pdo->prepare("
                  INSERT INTO tournaments
                    (tournament_name, owner_user_id, start_date, end_date, venue, entry_fees, description, max_teams, overs_per_match, wickets_per_team, registration_open_from, registration_open_to)
                  VALUES
                    (:n, :oid, :sd, :ed, :v, :ef, :desc, :mt, :opm, :wpt, :rf, :rt)
                ");
                $st->execute([
                    ':n' => $name,
                    ':oid' => $ownerId,
                    ':sd' => $startDate,
                    ':ed' => $endDate === '' ? null : $endDate,
                    ':v' => $venue === '' ? null : $venue,
                    ':ef' => $entryFees,
                    ':desc' => $description === '' ? null : $description,
                    ':mt' => $maxTeams,
                    ':opm' => $oversPerMatch,
                    ':wpt' => $wicketsPerTeam,
                    ':rf' => $registrationEnabled ? ($regFrom === '' ? $today : $regFrom) : null,
                    ':rt' => $registrationEnabled ? ($regTo === '' ? null : $regTo) : null,
                ]);
                $pdo->commit();
                $ok = 'Tournament created.';
            } catch (Throwable $e) {
                $pdo->rollBack();
                $err = $e->getMessage();
            }
        }
    }

    if ($action === 'update_registration') {
        $tournamentId = (int)($_POST['tournament_id'] ?? 0);
        $registrationEnabled = ($_POST['registration_enabled'] ?? '') === '1';
        $regFrom = trim((string)($_POST['registration_open_from'] ?? ''));
        $regTo = trim((string)($_POST['registration_open_to'] ?? ''));

        if ($tournamentId <= 0) {
            $err = 'Invalid tournament.';
        } else {
            $chk = $pdo->prepare('SELECT * FROM tournaments WHERE id = :id LIMIT 1');
            $chk->execute([':id' => $tournamentId]);
            $trow = $chk->fetch();
            if (!$trow || !can_access_tournament_row($trow)) {
                $err = 'You cannot edit this tournament.';
            } else {
                try {
                    $rf = $registrationEnabled ? ($regFrom === '' ? $today : $regFrom) : null;
                    $rt = $registrationEnabled ? ($regTo === '' ? null : $regTo) : null;
                    $st = $pdo->prepare("
                      UPDATE tournaments
                      SET registration_open_from = :rf,
                          registration_open_to = :rt
                      WHERE id = :id
                    ");
                    $st->execute([':rf' => $rf, ':rt' => $rt, ':id' => $tournamentId]);
                    $ok = 'Registration window updated.';
                } catch (Throwable $e) {
                    $err = $e->getMessage();
                }
            }
        }
    }

    if ($action === 'close_registration') {
        $tournamentId = (int)($_POST['tournament_id'] ?? 0);
        if ($tournamentId <= 0) {
            $err = 'Invalid tournament.';
        } else {
            $chk = $pdo->prepare('SELECT * FROM tournaments WHERE id = :id LIMIT 1');
            $chk->execute([':id' => $tournamentId]);
            $trow = $chk->fetch();
            if (!$trow || !can_access_tournament_row($trow)) {
                $err = 'You cannot edit this tournament.';
            } else {
                $st = $pdo->prepare("
                  UPDATE tournaments
                  SET registration_open_from = NULL,
                      registration_open_to = NULL
                  WHERE id = :id
                ");
                $st->execute([':id' => $tournamentId]);
                $ok = 'Registration closed.';
            }
        }
    }
}

if (is_organizer_user()) {
    $oid = organizer_owner_user_id();
    $st = $pdo->prepare("
      SELECT
        t.*,
        COUNT(r.id) AS registration_rows
      FROM tournaments t
      LEFT JOIN tournament_registrations r ON r.tournament_id = t.id
      WHERE t.owner_user_id = :oid
      GROUP BY t.id
      ORDER BY t.start_date DESC, t.id DESC
    ");
    $st->execute([':oid' => $oid]);
    $tournaments = $st->fetchAll();
} else {
    $tournaments = $pdo->query("
      SELECT
        t.*,
        COUNT(r.id) AS registration_rows
      FROM tournaments t
      LEFT JOIN tournament_registrations r ON r.tournament_id = t.id
      GROUP BY t.id
      ORDER BY t.start_date DESC, t.id DESC
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
        <h2 class="fw-bold mb-1"><?= $orgShell ? 'My tournaments' : 'Tournaments' ?></h2>
        <div class="cp-muted">Create tournaments and open/close player/team registrations.</div>
      </div>
    </div>

    <?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?= h($ok) ?></div><?php endif; ?>

    <div class="cp-card p-4 mb-3">
      <div class="fw-bold mb-2">Create Tournament</div>
      <form method="post" class="row g-3">
        <input type="hidden" name="action" value="create">

        <div class="col-12 col-md-6">
          <label class="form-label">Tournament Name *</label>
          <input class="form-control" name="tournament_name" value="<?= h($_POST['tournament_name'] ?? '') ?>" required data-autofocus>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Start Date *</label>
          <input type="date" class="form-control" name="start_date" value="<?= h($_POST['start_date'] ?? '') ?>" required>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">End Date</label>
          <input type="date" class="form-control" name="end_date" value="<?= h($_POST['end_date'] ?? '') ?>">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Entry Fees (USD)</label>
          <input type="number" step="0.01" min="0" class="form-control" name="entry_fees" value="<?= h($_POST['entry_fees'] ?? '0.00') ?>">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Max Teams</label>
          <input type="number" min="1" class="form-control" name="max_teams" value="<?= h($_POST['max_teams'] ?? '16') ?>">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Overs per Match</label>
          <input type="number" min="1" class="form-control" name="overs_per_match" value="<?= h($_POST['overs_per_match'] ?? '20') ?>">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Wickets per Team</label>
          <input type="number" min="1" class="form-control" name="wickets_per_team" value="<?= h($_POST['wickets_per_team'] ?? '10') ?>">
        </div>

        <div class="col-12">
          <label class="form-label">Tournament Description</label>
          <textarea class="form-control" name="description" rows="3"><?= h($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Venue</label>
          <input class="form-control" name="venue" value="<?= h($_POST['venue'] ?? '') ?>">
        </div>

        <div class="col-12">
          <div class="cp-muted mb-2">Registration Window (for player + team)</div>
          <div class="d-flex flex-wrap gap-3 align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="registration_enabled" value="1" id="regEnabled" <?= (($_POST['registration_enabled'] ?? '') === '1') ? 'checked' : '' ?>>
              <label class="form-check-label" for="regEnabled">Open registration</label>
            </div>
            <div>
              <label class="form-label small cp-muted mb-1">Open From</label>
              <input type="date" class="form-control" name="registration_open_from" value="<?= h($_POST['registration_open_from'] ?? $today) ?>">
            </div>
            <div>
              <label class="form-label small cp-muted mb-1">Open To (optional)</label>
              <input type="date" class="form-control" name="registration_open_to" value="<?= h($_POST['registration_open_to'] ?? '') ?>">
            </div>
          </div>
        </div>

        <div class="col-12 d-grid">
          <button class="btn btn-cp" type="submit">Create</button>
        </div>
      </form>
    </div>

    <div class="cp-card p-3 p-md-4">
      <div class="fw-bold mb-2">All Tournaments</div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Name</th>
              <th>Dates</th>
              <th>Registration</th>
              <th class="text-end">Requests</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$tournaments): ?>
              <tr><td colspan="4" class="cp-muted py-4 text-center">No tournaments yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($tournaments as $t): ?>
              <?php
                $regOpen = false;
                if (!empty($t['registration_open_from'])) {
                    $rf = (string)$t['registration_open_from'];
                    $rt = $t['registration_open_to'] !== null ? (string)$t['registration_open_to'] : null;
                    $regOpen = $rf <= $today && ($rt === null || $rt >= $today);
                }
              ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?= h($t['tournament_name']) ?></div>
                  <div class="small cp-muted"><?= !empty($t['venue']) ? h($t['venue']) : '—' ?></div>
                </td>
                <td class="cp-muted">
                  <?= h($t['start_date']) ?>
                  <?= !empty($t['end_date']) ? ' - ' . h((string)$t['end_date']) : '' ?>
                </td>
                <td>
                  <div class="small mb-1">
                    <?php if ($regOpen): ?>
                      <span class="badge cp-badge rounded-pill">Open</span>
                    <?php else: ?>
                      <span class="badge bg-secondary-subtle text-light rounded-pill border">Closed</span>
                    <?php endif; ?>
                  </div>

                  <form method="post" class="d-flex flex-wrap gap-2 align-items-end">
                    <input type="hidden" name="action" value="update_registration">
                    <input type="hidden" name="tournament_id" value="<?= (int)$t['id'] ?>">

                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="registration_enabled" value="1" id="reg<?= (int)$t['id'] ?>" <?= $regOpen ? 'checked' : '' ?>>
                      <label class="form-check-label small cp-muted" for="reg<?= (int)$t['id'] ?>">Open</label>
                    </div>

                    <div style="min-width: 150px;">
                      <input type="date" class="form-control form-control-sm" name="registration_open_from" value="<?= h((string)$t['registration_open_from']) ?>">
                    </div>
                    <div style="min-width: 150px;">
                      <input type="date" class="form-control form-control-sm" name="registration_open_to" value="<?= h((string)$t['registration_open_to']) ?>">
                    </div>

                    <button class="btn btn-sm btn-outline-success" type="submit">Update</button>
                  </form>

                  <form method="post" class="mt-2">
                    <input type="hidden" name="action" value="close_registration">
                    <input type="hidden" name="tournament_id" value="<?= (int)$t['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Close</button>
                  </form>
                </td>
                <td class="text-end cp-muted fw-semibold"><?= (int)$t['registration_rows'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

