<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/repo.php';
require_once __DIR__ . '/header.php';

$q = (string)($_GET['q'] ?? '');
$rows = fetch_leaderboard($q);
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
  <div>
    <h2 class="fw-bold mb-1">Leaderboard</h2>
    <div class="cp-muted">Ranking is based on total points across all matches.</div>
  </div>
  <form class="d-flex gap-2" method="get" action="leaderboard.php">
    <input class="form-control" name="q" placeholder="Search player..." value="<?= h($q) ?>" style="min-width:240px;">
    <button class="btn btn-outline-light" type="submit">Search</button>
  </form>
</div>

<div class="cp-card p-3 p-md-4">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th style="width:90px;">Rank</th>
          <th>Player</th>
          <th class="text-end" style="width:160px;">Total Points</th>
          <th class="text-end" style="width:160px;">Profile</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="4" class="cp-muted py-4 text-center">No players found.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="fw-semibold">#<?= (int)$r['rank'] ?></td>
            <td>
              <div class="d-flex align-items-center gap-3">
                <?php if (!empty($r['photo_path'])): ?>
                  <img class="player-photo" src="<?= h(url_for((string)$r['photo_path'])) ?>" alt="photo">
                <?php else: ?>
                  <div class="player-photo d-flex align-items-center justify-content-center cp-muted">N/A</div>
                <?php endif; ?>
                <div>
                  <div class="fw-bold"><?= h($r['full_name']) ?></div>
                  <div class="small cp-muted">Player ID: <?= (int)$r['id'] ?></div>
                </div>
              </div>
            </td>
            <td class="text-end fw-bold"><?= (int)$r['total_points'] ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-success" href="player.php?id=<?= (int)$r['id'] ?>">View</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

