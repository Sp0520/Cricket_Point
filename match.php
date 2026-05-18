<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/repo.php';

$matchId = (int)($_GET['id'] ?? 0);
if ($matchId <= 0) {
    http_response_code(404);
    echo 'Match not found';
    exit;
}

$match = fetch_match_with_tournament($matchId);
if (!$match) {
    http_response_code(404);
    echo 'Match not found';
    exit;
}

$scorecard = fetch_match_scorecard($matchId);

$activePlayerId = null;
if (is_logged_in()) {
    $u = current_user();
    if (($u['role'] ?? '') === 'player' && !empty($u['player_id'])) {
        $activePlayerId = (int)$u['player_id'];
    }
}

require_once __DIR__ . '/header.php';
?>

<div class="cp-card p-4 p-md-5 mb-4">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
    <div>
      <h2 class="fw-bold mb-1"><?= h($match['match_name']) ?></h2>
      <div class="cp-muted small">
        <?= h($match['tournament_name'] ?? '—') ?><?= !empty($match['venue']) ? ' • ' . h($match['venue']) : '' ?>
      </div>
      <div class="cp-muted small">Date: <?= h($match['match_date']) ?></div>
    </div>
    <div class="text-end">
      <div class="cp-muted small mb-1">Man of the Match</div>
      <div class="fw-semibold"><?= h($match['mom_name'] ?? '') ?: 'Not decided' ?></div>
      <div class="cp-muted small">MoM Points: <?= (int)($match['man_of_match_points'] ?? 0) ?></div>
    </div>
  </div>
  <hr class="border-secondary my-4">

  <div class="cp-muted small">
    <?= count($scorecard) ? ('Scorecard available for ' . count($scorecard) . ' players.') : 'No score data entered yet.' ?>
  </div>
</div>

<div class="cp-card p-3 p-md-4">
  <?php if (!$scorecard): ?>
    <div class="cp-muted py-4 text-center">No player stats on file for this match yet.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Player</th>
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
          <?php foreach ($scorecard as $s): ?>
            <?php $isMe = $activePlayerId !== null && (int)$s['player_id'] === $activePlayerId; ?>
            <tr<?= $isMe ? ' style="background: rgba(21,209,122,.10);"' : '' ?>>
              <td>
                <div class="d-flex align-items-center gap-3">
                  <?php if (!empty($s['photo_path'])): ?>
                    <img class="player-photo" style="width:44px;height:44px;border-radius:12px;" src="<?= h(url_for((string)$s['photo_path'])) ?>" alt="photo">
                  <?php else: ?>
                    <div class="player-photo d-flex align-items-center justify-content-center cp-muted" style="width:44px;height:44px;border-radius:12px;">N/A</div>
                  <?php endif; ?>
                  <div>
                    <div class="fw-semibold"><?= h($s['full_name']) ?></div>
                    <?php if ($isMe): ?><div class="small cp-muted">You</div><?php endif; ?>
                  </div>
                </div>
              </td>
              <td class="text-end"><?= (int)$s['runs'] ?></td>
              <td class="text-end"><?= (int)$s['fours'] ?></td>
              <td class="text-end"><?= (int)$s['sixes'] ?></td>
              <td class="text-end"><?= (int)$s['wickets'] ?></td>
              <td class="text-end"><?= (int)$s['catches'] ?></td>
              <td class="text-end"><?= (int)$s['runouts'] ?></td>
              <td class="text-end"><?= (int)$s['stumpings'] ?></td>
              <td class="text-end"><?= (int)$s['maiden_overs'] ?></td>
              <td class="text-end fw-bold"><?= (int)$s['points'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

