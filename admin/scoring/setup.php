<?php declare(strict_types=1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../access.php';
require_once __DIR__ . '/../../database/cricket_db.php';

require_admin_or_organizer();

$pdo = db();

/**
 * Helpers:
 */
function column_exists(PDO $pdo, string $table, string $column): bool
{
    try {
        $st = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return (bool)$st && $st->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function ensure_match_columns(PDO $pdo): void
{
    $columns = [
        'batting_team_id' => 'INT UNSIGNED NULL',
        'bowling_team_id' => 'INT UNSIGNED NULL',
        'status' => "ENUM('scheduled','live','innings_break','completed') NOT NULL DEFAULT 'scheduled'",
        'current_innings' => 'TINYINT NOT NULL DEFAULT 1',
        'total_overs' => 'INT NOT NULL DEFAULT 20',
        'field_setup' => "VARCHAR(50) NOT NULL DEFAULT 'normal'"
    ];

    foreach ($columns as $column => $definition) {
        if (!column_exists($pdo, 'matches', $column)) {
            try {
                $pdo->exec("ALTER TABLE matches ADD COLUMN {$column} {$definition}");
            } catch (Exception $e) {
                // if migration cannot run, app should still continue with existing data
            }
        }
    }
}

// Ensure required match columns are present to support scoring pages.
ensure_match_columns($pdo);

/*
|--------------------------------------------------------------------------
| CHECK IF STATUS COLUMN EXISTS
|--------------------------------------------------------------------------
*/

$statusColumnExists = false;

try {
    $check = $pdo->query("SHOW COLUMNS FROM matches LIKE 'status'");
    $statusColumnExists = (bool)$check && (bool)$check->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

// Optional: try to create the column if it does not exist (for older schema versions)
if (!$statusColumnExists) {
    try {
        $pdo->exec("ALTER TABLE matches ADD COLUMN status ENUM('scheduled','live','innings_break','completed') NOT NULL DEFAULT 'scheduled'");
        $statusColumnExists = true;
    } catch (Exception $e) {
        // If ALTER fails, keep status support disabled and use fallback queries.
    }
}

/*
|--------------------------------------------------------------------------
| LOAD MATCHES
|--------------------------------------------------------------------------
*/

if ($statusColumnExists) {

    if (is_organizer_user()) {

        $oid = organizer_owner_user_id();

        $st = $pdo->prepare("
            SELECT m.id, m.match_name, m.match_date,
                   m.status,
                   m.batting_team_id,
                   m.bowling_team_id,
                   bt.team_name AS batting_team,
                   bwt.team_name AS bowling_team
            FROM matches m
            LEFT JOIN teams bt ON bt.id = m.batting_team_id
            LEFT JOIN teams bwt ON bwt.id = m.bowling_team_id
            WHERE m.owner_user_id = :oid OR m.owner_user_id IS NULL
            ORDER BY m.match_date DESC
        ");

        $st->execute([':oid' => $oid]);

    } else {

        $st = $pdo->query("
            SELECT m.id, m.match_name, m.match_date,
                   m.status,
                   m.batting_team_id,
                   m.bowling_team_id,
                   bt.team_name AS batting_team,
                   bwt.team_name AS bowling_team
            FROM matches m
            LEFT JOIN teams bt ON bt.id = m.batting_team_id
            LEFT JOIN teams bwt ON bwt.id = m.bowling_team_id
            ORDER BY m.match_date DESC
        ");
    }

} else {

    // fallback version WITHOUT status column

    if (is_organizer_user()) {

        $oid = organizer_owner_user_id();

        $st = $pdo->prepare("
            SELECT m.id, m.match_name, m.match_date,
                   m.batting_team_id,
                   m.bowling_team_id,
                   bt.team_name AS batting_team,
                   bwt.team_name AS bowling_team
            FROM matches m
            LEFT JOIN teams bt ON bt.id = m.batting_team_id
            LEFT JOIN teams bwt ON bwt.id = m.bowling_team_id
            WHERE m.owner_user_id = :oid
            ORDER BY m.match_date DESC
        ");

        $st->execute([':oid' => $oid]);

    } else {

        $st = $pdo->query("
            SELECT m.id, m.match_name, m.match_date,
                   m.batting_team_id,
                   m.bowling_team_id,
                   bt.team_name AS batting_team,
                   bwt.team_name AS bowling_team
            FROM matches m
            LEFT JOIN teams bt ON bt.id = m.batting_team_id
            LEFT JOIN teams bwt ON bwt.id = m.bowling_team_id
            ORDER BY m.match_date DESC
        ");
    }
}

$matches = $st->fetchAll();

if (!$matches) {
    echo "<div style='padding:20px;font-size:18px'>
            No matches found.<br><br>
            Please create a match first from Admin → Matches page.
          </div>";
    require_once __DIR__ . '/../../header.php';
    exit;
}

/*
|--------------------------------------------------------------------------
| TEAMS + PLAYERS
|--------------------------------------------------------------------------
*/

$teams = $pdo->query("
    SELECT id, team_name
    FROM teams
    ORDER BY team_name
")->fetchAll();

/*
|--------------------------------------------------------------------------
| MATCH SELECTION
|--------------------------------------------------------------------------
*/

$matchId = (int)($_GET['match_id'] ?? ($matches[0]['id'] ?? 0));

$currentMatch = null;
$battingTeamPlayers = [];
$bowlingTeamPlayers = [];

if ($matchId > 0) {

    $st = $pdo->prepare("
        SELECT *
        FROM matches
        WHERE id = :id
    ");

    $st->execute([':id' => $matchId]);

    $currentMatch = $st->fetch();

    if (!empty($currentMatch['batting_team_id'])) {

        $battingTeamPlayers =
            get_team_players((int)$currentMatch['batting_team_id']);
    }

    if (!empty($currentMatch['bowling_team_id'])) {

        $bowlingTeamPlayers =
            get_team_players((int)$currentMatch['bowling_team_id']);
    }
}

/*
|--------------------------------------------------------------------------
| MATCH SETUP SUBMIT
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'setup_match') {

    $matchId = (int)$_POST['match_id'];

    $battingTeamId = (int)$_POST['batting_team_id'];
    $bowlingTeamId = (int)$_POST['bowling_team_id'];

    $strikerId = (int)$_POST['striker_id'];
    $nonStrikerId = (int)$_POST['non_striker_id'];

    $bowlerId = (int)$_POST['bowler_id'];

    try {

        if ($battingTeamId === $bowlingTeamId) {

            throw new Exception(
                'Batting and bowling teams must be different'
            );
        }

        if ($statusColumnExists) {

            $updateSQL = "
                UPDATE matches
                SET batting_team_id = :batting,
                    bowling_team_id = :bowling,
                    status = 'live'
                WHERE id = :match_id
            ";

        } else {

            $updateSQL = "
                UPDATE matches
                SET batting_team_id = :batting,
                    bowling_team_id = :bowling
                WHERE id = :match_id
            ";
        }

        $st = $pdo->prepare($updateSQL);

        $st->execute([
            ':match_id' => $matchId,
            ':batting' => $battingTeamId,
            ':bowling' => $bowlingTeamId
        ]);

        record_ball(
            $matchId,
            1,
            0,
            1,
            $bowlerId,
            $strikerId,
            $nonStrikerId,
            0
        );

        header("Location: live_scoring.php?match_id={$matchId}");
        exit;

    } catch (Exception $e) {

        $error = $e->getMessage();
    }
}

require_once __DIR__ . '/../../header.php';
?>