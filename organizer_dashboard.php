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

try {

    // Tournament Count
    $st = $pdo->query("
        SELECT COUNT(*) AS c
        FROM tournaments
    ");

    $row = $st->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $tc = (int)$row['c'];
    }

    // Match Count
    $st = $pdo->query("
        SELECT COUNT(*) AS c
        FROM matches
    ");

    $row = $st->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $mc = (int)$row['c'];
    }

} catch (PDOException $e) {

    die('Database Error: ' . $e->getMessage());
}
require_once __DIR__ . '/header.php';
$isPaid = is_organizer_paid();
?>

<div class="admin-shell">

    <?php require_once __DIR__ . '/organizer_sidebar.php'; ?>

    <div class="flex-grow-1">

        <?php if (!$isPaid): ?>
          <div class="cp-card p-4 border border-warning border-opacity-50 mb-3 bg-warning bg-opacity-10" style="animation: fadeIn 0.4s ease-out forwards;">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
              <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle p-3 bg-warning bg-opacity-20 text-warning d-flex align-items-center justify-content-center" style="width: 56px; height: 56px; flex-shrink: 0;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" class="bi bi-exclamation-triangle-fill" viewBox="0 0 16 16">
                    <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                  </svg>
                </div>
                <div>
                  <h4 class="fw-bold mb-1 text-warning">Organizer Account Inactive</h4>
                  <p class="cp-muted mb-0 small">Please activate your premium tournament organization membership in INR to unlock all options, including creating and scoring matches and tournaments.</p>
                </div>
              </div>
              <div>
                <a href="organizer_payment.php" class="btn btn-warning fw-bold text-dark px-4 py-2">Activate Now (₹999)</a>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <div class="cp-card p-4 p-md-5 mb-3">

            <h2 class="fw-bold mb-1">
                Organizer Dashboard
            </h2>

            <div class="cp-muted">
                Manage your tournaments, matches, and live scoring.
            </div>

            <hr class="border-secondary my-4">

            <div class="row g-3">

                <!-- Tournament Card -->
                <div class="col-md-6">

                    <div class="cp-card p-4 border-0">

                        <div class="cp-muted small">
                            Your Tournaments
                        </div>

                        <div class="fw-bold fs-3">
                            <?= $tc ?>
                        </div>

                        <a class="btn btn-sm btn-outline-success mt-2"
                           href="admin_tournaments.php">
                            Open
                        </a>

                    </div>

                </div>

                <!-- Match Card -->
                <div class="col-md-6">

                    <div class="cp-card p-4 border-0">

                        <div class="cp-muted small">
                            Your Matches
                        </div>

                        <div class="fw-bold fs-3">
                            <?= $mc ?>
                        </div>

                        <a class="btn btn-sm btn-outline-success mt-2"
                           href="admin_matches.php">
                            Open
                        </a>

                    </div>

                </div>

            </div>

        </div>

        <!-- Quick Start -->
        <div class="cp-card p-4">

            <div class="fw-bold mb-2">
                Quick Start
            </div>

            <ol class="cp-muted mb-0">

                <li>
                    Create a tournament and open registration dates.
                </li>

                <li>
                    Create a match linked to your tournament.
                </li>

                <li>
                    Open the
                    <a href="live_score_board.php">
                        Live Score Board
                    </a>
                    for ball-by-ball scoring.
                </li>

            </ol>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>