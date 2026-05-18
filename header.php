<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

$u = current_user();
$isAdmin = $u && ($u['role'] ?? '') === 'admin';
$isOrganizer = $u && ($u['role'] ?? '') === 'organizer';
?>
<!doctype html>
<html lang="en">
  <head>
    <title>Cricket Points System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Your Custom CSS -->
    <link rel="stylesheet" href="style.css">
  </head>
  <body class="<?= ($isAdmin || $isOrganizer) ? 'cp-admin-body' : 'cp-user-body' ?>">
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
      <div class="container cp-container">
        <a class="navbar-brand fw-bold" href="index.php"><?= h(APP_NAME) ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#cpNav" aria-controls="cpNav" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="cpNav">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item"><a class="nav-link" href="leaderboard.php">Leaderboard</a></li>
            <li class="nav-item"><a class="nav-link" href="matches.php">Matches</a></li>
            <li class="nav-item"><a class="nav-link" href="mom.php">Man of the Match</a></li>
            <?php if ($isAdmin): ?>
              <li class="nav-item"><a class="nav-link" href="admin.php">Admin</a></li>
            <?php endif; ?>
            <?php if ($isOrganizer): ?>
              <li class="nav-item"><a class="nav-link" href="organizer_dashboard.php">Organizer</a></li>
            <?php endif; ?>
          </ul>
          <ul class="navbar-nav ms-auto">
            <?php if ($u): ?>
              <?php if (($u['role'] ?? '') === 'player' && ($u['player_id'] ?? null)): ?>
                <li class="nav-item"><a class="nav-link" href="player_dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="player_teams.php">My Team</a></li>
                <li class="nav-item"><a class="nav-link" href="player.php?id=<?= (int)$u['player_id'] ?>">My Profile</a></li>
              <?php endif; ?>
              <li class="nav-item"><span class="nav-link cp-muted">Hi, <?= h($u['name'] ?? '') ?></span></li>
              <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
            <?php else: ?>
              <li class="nav-item"><a class="nav-link" href="register.php">Register</a></li>
              <li class="nav-item"><a class="nav-link" href="organizer_register.php">Organizer signup</a></li>
              <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </nav>
    <main class="container cp-container py-4">

