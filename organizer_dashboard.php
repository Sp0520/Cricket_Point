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
?>

<div class="admin-shell">

    <?php require_once __DIR__ . '/organizer_sidebar.php'; ?>

    <div class="flex-grow-1">

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