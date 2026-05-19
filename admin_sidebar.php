<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_admin();
?>

<aside class="admin-sidebar">
  <div class="mb-2 fw-bold">Admin Dashboard</div>
  <a href="admin.php">Overview</a>
  <a href="admin_tournaments.php">Tournaments</a>
  <a href="admin_tournament_registrations.php">Registrations</a>
  <a href="admin_teams.php">Teams</a>
  <a href="admin_players.php">Players</a>
  <a href="admin_matches.php">Matches</a>
  <a href="live_score_board.php">Live Scoreboards</a>
  <a href="admin_leaderboard.php">Leaderboard</a>

  
</aside>

