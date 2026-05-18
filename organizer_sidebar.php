<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/access.php';
require_organizer();
?>

<aside class="admin-sidebar">
  <div class="mb-2 fw-bold">Organizer</div>
  <a href="organizer_dashboard.php">Overview</a>
  <a href="admin_tournaments.php">My tournaments</a>
  <a href="admin_tournament_registrations.php">Registrations</a>
  <a href="admin_matches.php">My matches</a>
  <a href="live_score_board.php">Live score board</a>
</aside>
