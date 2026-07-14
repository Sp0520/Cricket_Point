<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/repo.php';
require_once __DIR__ . '/access.php';

require_admin_or_organizer();
require_organizer_paid();
$pdo = db();

if (is_organizer_user()) {
    $oid = organizer_owner_user_id();
    $st = $pdo->prepare('SELECT id, tournament_name FROM tournaments WHERE owner_user_id = :o ORDER BY start_date DESC, id DESC');
    $st->execute([':o' => $oid]);
    $tournaments = $st->fetchAll();
} else {
    $tournaments = $pdo->query('SELECT id, tournament_name FROM tournaments ORDER BY start_date DESC, id DESC')->fetchAll();
}

$tournamentId = (int)($_GET['tournament_id'] ?? 0);
if ($tournamentId <= 0 && $tournaments) {
    $tournamentId = (int)$tournaments[0]['id'];
}

$err = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $registrationId = (int)($_POST['registration_id'] ?? 0);
    $status = (string)($_POST['status'] ?? '');
    $allowed = ['pending', 'approved', 'rejected', 'cancelled'];
    if ($registrationId <= 0 || !in_array($status, $allowed, true)) {
        $err = 'Invalid request.';
    } else {
        $st = $pdo->prepare('
          SELECT r.*, t.owner_user_id
          FROM tournament_registrations r
          JOIN tournaments t ON t.id = r.tournament_id
          WHERE r.id = :id
          LIMIT 1
        ');
        $st->execute([':id' => $registrationId]);
        $regRow = $st->fetch();
        if (!$regRow) {
            $err = 'Registration not found.';
        } elseif (is_organizer_user()) {
            $oid = organizer_owner_user_id();
            if ((int)($regRow['owner_user_id'] ?? 0) !== (int)$oid) {
                $err = 'Access denied.';
            }
        }
        if ($err === '') {
            $st = $pdo->prepare('UPDATE tournament_registrations SET status=:s WHERE id=:id');
            $st->execute([':s' => $status, ':id' => $registrationId]);
            $ok = 'Status updated.';
        }
    }
}

$regs = [];
if ($tournamentId > 0) {
    $st = $pdo->prepare("
      SELECT
        r.id,
        r.tournament_id,
        r.registrant_type,
        r.player_id,
        r.team_id,
        r.contact_phone,
        r.status,
        r.created_at,
        p.full_name AS player_name,
        tm.team_name AS team_name
      FROM tournament_registrations r
      LEFT JOIN players p ON p.id = r.player_id
      LEFT JOIN teams tm ON tm.id = r.team_id
      WHERE r.tournament_id = :tid
      ORDER BY r.created_at DESC, r.id DESC
    ");
    $st->execute([':tid' => $tournamentId]);
    $regs = $st->fetchAll();
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
        <h2 class="fw-bold mb-1">Tournament Registrations</h2>
        <div class="cp-muted">View registrations submitted by players or their teams.</div>
      </div>
    </div>

    <?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?= h($ok) ?></div><?php endif; ?>

    <div class="cp-card p-3 p-md-4 mb-3">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-12 col-md-7">
          <label class="form-label">Tournament</label>
          <select class="form-select" name="tournament_id" required>
            <?php foreach ($tournaments as $t): ?>
              <option value="<?= (int)$t['id'] ?>" <?= $tournamentId === (int)$t['id'] ? 'selected' : '' ?>>
                <?= h($t['tournament_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-5 d-grid">
          <button class="btn btn-cp" type="submit">Load</button>
        </div>
      </form>
    </div>

    <div class="cp-card p-3 p-md-4">
      <div class="fw-bold mb-2">Registrations</div>
      <?php if ($tournamentId <= 0): ?>
        <div class="cp-muted py-4 text-center">Create a tournament first.</div>
      <?php else: ?>
        <?php if (!$regs): ?>
          <div class="cp-muted py-4 text-center">No registrations yet.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Type</th>
                  <th>Player/Team</th>
                  <th>Contact</th>
                  <th>Status</th>
                  <th>Submitted</th>
                  <th class="text-end">Update</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($regs as $r): ?>
                  <tr>
                    <td class="cp-muted">#<?= (int)$r['id'] ?></td>
                    <td><?= h((string)$r['registrant_type']) ?></td>
                    <td>
                      <?php if ($r['registrant_type'] === 'player'): ?>
                        <div class="fw-semibold"><?= h((string)$r['player_name']) ?></div>
                      <?php else: ?>
                        <div class="fw-semibold"><?= h((string)$r['team_name']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td class="cp-muted small">
                      <?php if ($r['registrant_type'] === 'team' && !empty($r['contact_phone'])): ?>
                        <?= h((string)$r['contact_phone']) ?>
                      <?php else: ?>
                        —
                      <?php endif; ?>
                    </td>
                    <td>
                      <form method="post" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="registration_id" value="<?= (int)$r['id'] ?>">
                        <select name="status" class="form-select form-select-sm" style="min-width: 140px;" onchange="this.form.submit()">
                          <?php
                            $statuses = ['pending','approved','rejected','cancelled'];
                          ?>
                          <?php foreach ($statuses as $s): ?>
                            <option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>>
                              <?= h($s) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </form>
                    </td>
                    <td class="cp-muted"><?= h((string)$r['created_at']) ?></td>
                    <td class="text-end cp-muted"> </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

