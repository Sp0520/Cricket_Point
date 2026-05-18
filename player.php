<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/repo.php';
require_once __DIR__ . '/header.php';

$playerId = (int)($_GET['id'] ?? 0);
if ($playerId <= 0) {
    http_response_code(404);
    echo 'Player not found';
    exit;
}

$player = fetch_player_totals($playerId);
if (!$player) {
    http_response_code(404);
    echo 'Player not found';
    exit;
}

$rank = fetch_player_rank($playerId);
$matches = fetch_player_match_rows($playerId);
?>

<div class="cp-card p-4 p-md-5 mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div class="d-flex align-items-center gap-3">
      <?php if (!empty($player['photo_path'])): ?>
        <img class="player-photo" style="width:78px;height:78px;border-radius:18px;" src="<?= h(url_for((string)$player['photo_path'])) ?>" alt="photo">
      <?php else: ?>
        <div class="player-photo d-flex align-items-center justify-content-center cp-muted" style="width:78px;height:78px;border-radius:18px;">N/A</div>
      <?php endif; ?>
      <div>
        <h2 class="fw-bold mb-1"><?= h($player['full_name']) ?></h2>
        <div class="cp-muted">Player ID: <?= (int)$player['id'] ?></div>
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <div class="cp-card p-3 border-0">
        <div class="cp-muted small">Total Points</div>
        <div class="fw-bold fs-4"><?= (int)$player['total_points'] ?></div>
      </div>
      <div class="cp-card p-3 border-0">
        <div class="cp-muted small">Rank</div>
        <div class="fw-bold fs-4"><?= $rank ? ('#' . (int)$rank) : '—' ?></div>
      </div>
    </div>
  </div>
</div>

<div class="cp-card p-3 p-md-4">
  <div class="d-flex justify-content-between align-items-end mb-2">
    <div>
      <div class="fw-bold">Match Performance</div>
      <div class="cp-muted small">Points are calculated automatically from the scoring rules.</div>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Match</th>
          <th class="text-end">Runs</th>
          <th class="text-end">4s</th>
          <th class="text-end">6s</th>
          <th class="text-end">Wkts</th>
          <th class="text-end">Catches</th>
          <th class="text-end">Runouts</th>
          <th class="text-end">Stump</th>
          <th class="text-end">Maidens</th>
          <th class="text-end">Points</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$matches): ?>
          <tr><td colspan="10" class="cp-muted py-4 text-center">No match stats yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($matches as $m): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= h($m['match_name']) ?></div>
              <div class="small cp-muted"><?= h($m['match_date']) ?><?= $m['venue'] ? ' • ' . h($m['venue']) : '' ?></div>
            </td>
            <td class="text-end"><?= (int)$m['runs'] ?></td>
            <td class="text-end"><?= (int)$m['fours'] ?></td>
            <td class="text-end"><?= (int)$m['sixes'] ?></td>
            <td class="text-end"><?= (int)$m['wickets'] ?></td>
            <td class="text-end"><?= (int)$m['catches'] ?></td>
            <td class="text-end"><?= (int)$m['runouts'] ?></td>
            <td class="text-end"><?= (int)$m['stumpings'] ?></td>
            <td class="text-end"><?= (int)$m['maiden_overs'] ?></td>
            <td class="text-end fw-bold"><?= (int)$m['points'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

