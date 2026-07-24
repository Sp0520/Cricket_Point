<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/repo.php';
require_once __DIR__ . '/header.php';

$matches = fetch_matches();

?>

<div class="d-flex justify-content-between align-items-end mb-3">
  <div>
    <h2 class="fw-bold mb-1">Match List</h2>
    <div class="cp-muted">Matches and their Man of the Match (auto-selected by points).</div>
  </div>
</div>

<div class="row g-3">
  <?php if (!$matches): ?>
    <div class="col-12">
      <div class="cp-card p-4 text-center cp-muted">No matches yet.</div>
    </div>
  <?php endif; ?>
  <?php foreach ($matches as $m): ?>
    <div class="col-md-6">
      <div class="cp-card p-4 h-100">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div>
            <div class="fw-bold fs-5"><?= h($m['match_name']) ?></div>
            <div class="cp-muted small"><?= h($m['match_date']) ?><?= $m['venue'] ? ' • ' . h($m['venue']) : '' ?></div>
          </div>
          <span class="badge cp-badge rounded-pill">Match #<?= (int)$m['id'] ?></span>
        </div>
        <hr class="border-secondary my-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <div class="cp-muted small">Man of the Match</div>
            <div class="fw-semibold"><?= h($m['mom_name'] ?? 'Not decided') ?></div>
          </div>
          <div class="text-end">
            <div class="cp-muted small">Points</div>
            <div class="fw-bold"><?= (int)($m['man_of_match_points'] ?? 0) ?></div>
          </div>
        </div>
        <div class="d-flex justify-content-between align-items-center gap-2">
          <a class="btn btn-sm btn-outline-light px-3" href="match.php?id=<?= (int)$m['id'] ?>"><i class="fas fa-chart-bar me-1"></i> Stats</a>
          <a class="btn btn-sm btn-cp px-3" href="match_scoreboard.php?match_id=<?= (int)$m['id'] ?>"><i class="fas fa-satellite-dish me-1"></i> Live Scoreboard</a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

