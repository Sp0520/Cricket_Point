<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/access.php';

require_organizer();
$uid = organizer_owner_user_id();
$pdo = db();

$tc = 0;
$mc = 0;
if ($uid !== null) {
    $st = $pdo->prepare('SELECT COUNT(*) c FROM tournaments WHERE owner_user_id = :u');
    $st->execute([':u' => $uid]);
    $tc = (int)$st->fetch()['c'];
    $st = $pdo->prepare('SELECT COUNT(*) c FROM matches WHERE owner_user_id = :u');
    $st->execute([':u' => $uid]);
    $mc = (int)$st->fetch()['c'];
}

require_once __DIR__ . '/header.php';
?>

<div class="admin-shell">
  <?php require_once __DIR__ . '/organizer_sidebar.php'; ?>
  <div class="flex-grow-1">
    <div class="cp-card p-4 p-md-5 mb-3">
      <h2 class="fw-bold mb-1">Organizer dashboard</h2>
      <div class="cp-muted">Manage your private tournaments, matches, and live scoring.</div>
      <hr class="border-secondary my-4">
      <div class="row g-3">
        <div class="col-md-6">
          <div class="cp-card p-4 border-0">
            <div class="cp-muted small">Your tournaments</div>
            <div class="fw-bold fs-3"><?= (int)$tc ?></div>
            <a class="btn btn-sm btn-outline-success mt-2" href="admin_tournaments.php">Open</a>
          </div>
        </div>
        <div class="col-md-6">
          <div class="cp-card p-4 border-0">
            <div class="cp-muted small">Your matches</div>
            <div class="fw-bold fs-3"><?= (int)$mc ?></div>
            <a class="btn btn-sm btn-outline-success mt-2" href="admin_matches.php">Open</a>
          </div>
        </div>
      </div>
    </div>

    <div class="cp-card p-4">
      <div class="fw-bold mb-2">Quick start</div>
      <ol class="cp-muted mb-0">
        <li>Create a tournament and open registration dates.</li>
        <li>Create a match (optionally linked to that tournament).</li>
        <li>Open the <a href="live_score_board.php">Live score board</a> for ball-by-ball entry.</li>
      </ol>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
