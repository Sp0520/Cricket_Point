<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/repo.php';
require_once __DIR__ . '/header.php';

$rows = fetch_mom_rows();
?>

<div class="d-flex justify-content-between align-items-end mb-3">
  <div>
    <h2 class="fw-bold mb-1">Man of the Match</h2>
    <div class="cp-muted">Automatically selected by highest points in each match.</div>
  </div>
</div>

<div class="row g-3">
  <?php if (!$rows): ?>
    <div class="col-12">
      <div class="cp-card p-4 text-center cp-muted">No matches yet.</div>
    </div>
  <?php endif; ?>

  <?php foreach ($rows as $r): ?>
    <div class="col-md-6">
      <div class="cp-card p-4 h-100">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div>
            <div class="fw-bold fs-5"><?= h($r['match_name']) ?></div>
            <div class="cp-muted small"><?= h($r['match_date']) ?><?= $r['venue'] ? ' • ' . h($r['venue']) : '' ?></div>
          </div>
          <span class="badge cp-badge rounded-pill">Match #<?= (int)$r['id'] ?></span>
        </div>
        <hr class="border-secondary my-3">
        <?php if (!empty($r['player_id'])): ?>
          <div class="d-flex align-items-center gap-3">
            <?php if (!empty($r['photo_path'])): ?>
              <img class="player-photo" src="<?= h(url_for((string)$r['photo_path'])) ?>" alt="photo">
            <?php else: ?>
              <div class="player-photo d-flex align-items-center justify-content-center cp-muted">N/A</div>
            <?php endif; ?>
            <div class="flex-grow-1">
              <div class="cp-muted small">Man of the Match</div>
              <div class="fw-bold"><?= h($r['player_name']) ?></div>
              <div class="small cp-muted">Points: <span class="text-white fw-semibold"><?= (int)$r['man_of_match_points'] ?></span></div>
            </div>
            <a class="btn btn-sm btn-outline-success" href="<?= h(url_for('player.php?id=' . (int)$r['player_id'])) ?>">View profile</a>
          </div>
        <?php else: ?>
          <div class="cp-muted">Not decided yet. Admin needs to enter match stats.</div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

