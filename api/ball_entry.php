<?php
declare(strict_types=1);

/**
 * API: Record Ball Entry
 * Handles ball-by-ball scoring updates
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../database/cricket_db.php';
require_once __DIR__ . '/../database/points_calculator.php';

header('Content-Type: application/json');

try {
    require_admin_or_organizer();

    $action = $_POST['action'] ?? '';
    $match_id = (int)($_POST['match_id'] ?? 0);
    $innings = (int)($_POST['innings'] ?? 1);

    if ($match_id <= 0) {
        throw new Exception('Invalid match ID');
    }

    $pdo = db();

    // Get current match details
    $match = get_match_details($match_id);
    if (!$match) {
        throw new Exception('Match not found');
    }

    switch ($action) {
        case 'record_ball':
            record_ball_entry($match_id, $innings);
            break;

        case 'undo_last_ball':
            undo_last_ball($match_id, $innings);
            break;

        case 'end_over':
            end_over($match_id, $innings);
            break;

        case 'end_innings':
            end_innings($match_id, $innings);
            break;

        case 'update_strike':
            update_striker_info($match_id, $innings);
            break;

        case 'wicket_details':
            record_wicket_ball($match_id, $innings);
            break;

        default:
            throw new Exception('Unknown action: ' . $action);
    }

    echo json_encode(['success' => true, 'message' => 'Ball recorded successfully']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Record a regular ball entry
 */
function record_ball_entry(int $match_id, int $innings): void
{
    $pdo = db();
    $bowler_id = (int)($_POST['bowler_id'] ?? 0);
    $striker_id = (int)($_POST['striker_id'] ?? 0);
    $non_striker_id = (int)($_POST['non_striker_id'] ?? 0);
    $runs = (int)($_POST['runs'] ?? 0);
    $extra_type = $_POST['extra_type'] ?? 'none';
    $extra_runs = (int)($_POST['extra_runs'] ?? 0);

    if (!$bowler_id || !$striker_id || !$non_striker_id) {
        throw new Exception('Missing player IDs');
    }

    // Get next ball position
    $position = get_next_ball_position($match_id, $innings);
    $over = $position['over_number'];
    $ball = $position['ball_number'];

    // Record the ball
    $ball_id = record_ball(
        $match_id,
        $innings,
        $over,
        $ball,
        $bowler_id,
        $striker_id,
        $non_striker_id,
        $runs,
        $extra_type,
        $extra_runs
    );

    if (!$ball_id) {
        throw new Exception('Failed to record ball');
    }

    // Update striker stats
    update_batsman_stats($match_id, $innings, $striker_id, $runs);

    // Update bowler stats
    update_bowler_ball_stats($match_id, $innings, $bowler_id, $runs, $extra_runs, $extra_type);

    // Auto change striker if odd runs
    if (($runs % 2) === 1) {
        $pdo->prepare("
            UPDATE matches 
            SET bowling_team_id = bowling_team_id
            WHERE id = :match_id
        ")->execute([':match_id' => $match_id]);
    }

    // Check if over is complete (6 balls)
    if ($ball >= 6) {
        end_over($match_id, $innings);
    }
}

/**
 * Update batsman stats after runs
 */
function update_batsman_stats(int $match_id, int $innings, int $striker_id, int $runs): void
{
    $pdo = db();

    // Check if stats row exists
    $st = $pdo->prepare("
        SELECT id FROM player_match_stats
        WHERE match_id = :match_id 
            AND player_id = :player_id
            AND innings_number = :innings
    ");
    $st->execute([
        ':match_id' => $match_id,
        ':player_id' => $striker_id,
        ':innings' => $innings
    ]);

    if ($st->fetch()) {
        // Update existing stats
        $pdo->prepare("
            UPDATE player_match_stats
            SET runs = runs + :runs,
                balls_faced = balls_faced + 1,
                fours = CASE WHEN :runs = 4 THEN fours + 1 ELSE fours END,
                sixes = CASE WHEN :runs = 6 THEN sixes + 1 ELSE sixes END,
                strike_rate = (runs + :runs) / (balls_faced + 1) * 100
            WHERE match_id = :match_id 
                AND player_id = :player_id
                AND innings_number = :innings
        ")->execute([
            ':match_id' => $match_id,
            ':player_id' => $striker_id,
            ':innings' => $innings,
            ':runs' => $runs
        ]);
    } else {
        // Insert new stats
        $pdo->prepare("
            INSERT INTO player_match_stats (
                match_id, player_id, innings_number, runs, balls_faced, fours, sixes, strike_rate
            ) VALUES (
                :match_id, :player_id, :innings, :runs, 1, 
                CASE WHEN :runs = 4 THEN 1 ELSE 0 END,
                CASE WHEN :runs = 6 THEN 1 ELSE 0 END,
                :runs * 100
            )
        ")->execute([
            ':match_id' => $match_id,
            ':player_id' => $striker_id,
            ':innings' => $innings,
            ':runs' => $runs
        ]);
    }

    // Recalculate fantasy points for batsman
    FantasyPointsCalculator::recalculate_and_update_player_points($match_id, $striker_id, $innings);
}

/**
 * Update bowler stats after delivering ball
 */
function update_bowler_ball_stats(
    int $match_id,
    int $innings,
    int $bowler_id,
    int $runs,
    int $extra_runs,
    string $extra_type
): void {
    $pdo = db();
    $total_runs = $runs + $extra_runs;

    $st = $pdo->prepare("
        SELECT id FROM player_match_stats
        WHERE match_id = :match_id 
            AND player_id = :player_id
            AND innings_number = :innings
    ");
    $st->execute([
        ':match_id' => $match_id,
        ':player_id' => $bowler_id,
        ':innings' => $innings
    ]);

    if ($st->fetch()) {
        $pdo->prepare("
            UPDATE player_match_stats
            SET balls_bowled = balls_bowled + 1,
                runs_conceded = runs_conceded + :total_runs,
                economy = runs_conceded / (balls_bowled + 1) * 6
            WHERE match_id = :match_id 
                AND player_id = :player_id
                AND innings_number = :innings
        ")->execute([
            ':match_id' => $match_id,
            ':player_id' => $bowler_id,
            ':innings' => $innings,
            ':total_runs' => $total_runs
        ]);
    } else {
        $pdo->prepare("
            INSERT INTO player_match_stats (
                match_id, player_id, innings_number, balls_bowled, runs_conceded, economy
            ) VALUES (
                :match_id, :player_id, :innings, 1, :total_runs, :economy
            )
        ")->execute([
            ':match_id' => $match_id,
            ':player_id' => $bowler_id,
            ':innings' => $innings,
            ':total_runs' => $total_runs,
            ':economy' => $total_runs * 6
        ]);
    }

    // Recalculate fantasy points for bowler
    FantasyPointsCalculator::recalculate_and_update_player_points($match_id, $bowler_id, $innings);
}

/**
 * Record a wicket ball
 */
function record_wicket_ball(int $match_id, int $innings): void
{
    $pdo = db();
    $bowler_id = (int)($_POST['bowler_id'] ?? 0);
    $striker_id = (int)($_POST['striker_id'] ?? 0);
    $non_striker_id = (int)($_POST['non_striker_id'] ?? 0);
    $wicket_type = $_POST['wicket_type'] ?? 'bowled';
    $fielder_id = (int)($_POST['fielder_id'] ?? 0);
    $extra_type = $_POST['extra_type'] ?? 'none';
    $runs = (int)($_POST['runs'] ?? 0);
    $extra_runs = (int)($_POST['extra_runs'] ?? 0);

    if (!$bowler_id || !$striker_id) {
        throw new Exception('Missing player IDs');
    }

    // Get next ball position
    $position = get_next_ball_position($match_id, $innings);
    $over = $position['over_number'];
    $ball = $position['ball_number'];

    // Record the ball with wicket
    $ball_id = record_ball(
        $match_id,
        $innings,
        $over,
        $ball,
        $bowler_id,
        $striker_id,
        $non_striker_id,
        $runs,
        $extra_type,
        $extra_runs,
        true,
        $wicket_type,
        $fielder_id > 0 ? $fielder_id : null
    );

    if (!$ball_id) {
        throw new Exception('Failed to record wicket');
    }

    // Mark batsman as out
    $pdo->prepare("
        UPDATE player_match_stats
        SET is_out = 1
        WHERE match_id = :match_id 
            AND player_id = :player_id
            AND innings_number = :innings
    ")->execute([
        ':match_id' => $match_id,
        ':player_id' => $striker_id,
        ':innings' => $innings
    ]);

    // Update bowler wicket count
    $st = $pdo->prepare("
        SELECT id FROM player_match_stats
        WHERE match_id = :match_id 
            AND player_id = :player_id
            AND innings_number = :innings
    ");
    $st->execute([
        ':match_id' => $match_id,
        ':player_id' => $bowler_id,
        ':innings' => $innings
    ]);

    if ($st->fetch()) {
        $pdo->prepare("
            UPDATE player_match_stats
            SET wickets = wickets + 1
            WHERE match_id = :match_id 
                AND player_id = :player_id
                AND innings_number = :innings
        ")->execute([
            ':match_id' => $match_id,
            ':player_id' => $bowler_id,
            ':innings' => $innings
        ]);
    } else {
        $pdo->prepare("
            INSERT INTO player_match_stats (match_id, player_id, innings_number, wickets)
            VALUES (:match_id, :player_id, :innings, 1)
        ")->execute([
            ':match_id' => $match_id,
            ':player_id' => $bowler_id,
            ':innings' => $innings
        ]);
    }

    // Update fielder stats if applicable
    if ($fielder_id > 0 && in_array($wicket_type, ['caught', 'stumped', 'run_out'])) {
        if ($wicket_type === 'caught') {
            $field = 'catches';
        } elseif ($wicket_type === 'stumped') {
            $field = 'stumpings';
        } else {
            $field = 'runouts';
        }

        $st = $pdo->prepare("
            SELECT id FROM player_match_stats
            WHERE match_id = :match_id 
                AND player_id = :player_id
                AND innings_number = :innings
        ");
        $st->execute([
            ':match_id' => $match_id,
            ':player_id' => $fielder_id,
            ':innings' => $innings
        ]);

        if ($st->fetch()) {
            $pdo->prepare("
                UPDATE player_match_stats
                SET {$field} = {$field} + 1
                WHERE match_id = :match_id 
                    AND player_id = :player_id
                    AND innings_number = :innings
            ")->execute([
                ':match_id' => $match_id,
                ':player_id' => $fielder_id,
                ':innings' => $innings
            ]);
        } else {
            $cols = "{$field} = 1";
            $pdo->prepare("
                INSERT INTO player_match_stats (match_id, player_id, innings_number, {$field})
                VALUES (:match_id, :player_id, :innings, 1)
            ")->execute([
                ':match_id' => $match_id,
                ':player_id' => $fielder_id,
                ':innings' => $innings
            ]);
        }

        FantasyPointsCalculator::recalculate_and_update_player_points($match_id, $fielder_id, $innings);
    }

    // Recalculate points
    FantasyPointsCalculator::recalculate_and_update_player_points($match_id, $bowler_id, $innings);
    FantasyPointsCalculator::recalculate_and_update_player_points($match_id, $striker_id, $innings);
}

/**
 * Undo last ball
 */
function undo_last_ball(int $match_id, int $innings): void

{
    $pdo = db();

    // Get last ball
    $st = $pdo->prepare("
        SELECT * FROM ball_by_ball
        WHERE match_id = :match_id AND innings = :innings
        ORDER BY id DESC LIMIT 1
    ");
    $st->execute([':match_id' => $match_id, ':innings' => $innings]);
    $lastBall = $st->fetch();

    if (!$lastBall) {
        throw new Exception('No balls to undo');
    }

    // Revert batsman stats
    if ($lastBall['striker_id']) {
        $pdo->prepare("
            UPDATE player_match_stats
            SET runs = GREATEST(0, runs - :runs),
                balls_faced = GREATEST(0, balls_faced - 1),
                fours = CASE WHEN :runs = 4 THEN GREATEST(0, fours - 1) ELSE fours END,
                sixes = CASE WHEN :runs = 6 THEN GREATEST(0, sixes - 1) ELSE sixes END
            WHERE match_id = :match_id 
                AND player_id = :player_id
                AND innings_number = :innings
        ")->execute([
            ':match_id' => $match_id,
            ':player_id' => $lastBall['striker_id'],
            ':innings' => $innings,
            ':runs' => $lastBall['total_runs']
        ]);

        FantasyPointsCalculator::recalculate_and_update_player_points($match_id, $lastBall['striker_id'], $innings);
    }

    // Revert bowler stats
    if ($lastBall['bowler_id']) {
        $pdo->prepare("
            UPDATE player_match_stats
            SET balls_bowled = GREATEST(0, balls_bowled - 1),
                runs_conceded = GREATEST(0, runs_conceded - :runs),
                wickets = CASE WHEN :is_wicket = 1 THEN GREATEST(0, wickets - 1) ELSE wickets END,
                is_out = 0
            WHERE match_id = :match_id 
                AND player_id = :player_id
                AND innings_number = :innings
        ")->execute([
            ':match_id' => $match_id,
            ':player_id' => $lastBall['bowler_id'],
            ':innings' => $innings,
            ':runs' => $lastBall['total_runs'],
            ':is_wicket' => $lastBall['is_wicket']
        ]);

        FantasyPointsCalculator::recalculate_and_update_player_points($match_id, $lastBall['bowler_id'], $innings);
    }

    // Delete the ball
    $pdo->prepare("DELETE FROM ball_by_ball WHERE id = :id")->execute([':id' => $lastBall['id']]);
}

/**
 * Mark end of over
 */
function end_over(int $match_id, int $innings): void
{
    $pdo = db();

    // Get the over number of the last ball
    $st = $pdo->prepare("
        SELECT MAX(over_number) as over_number FROM ball_by_ball
        WHERE match_id = :match_id AND innings = :innings
    ");
    $st->execute([':match_id' => $match_id, ':innings' => $innings]);
    $result = $st->fetch();
    $currentOver = (int)($result['over_number'] ?? 0);

    // Check for maiden over (no runs in this over)
    $st = $pdo->prepare("
        SELECT SUM(total_runs) as total_runs FROM ball_by_ball
        WHERE match_id = :match_id AND innings = :innings AND over_number = :over
    ");
    $st->execute([
        ':match_id' => $match_id,
        ':innings' => $innings,
        ':over' => $currentOver
    ]);
    $overResult = $st->fetch();
    $totalRuns = (int)($overResult['total_runs'] ?? 0);

    if ($totalRuns === 0) {
        // Get bowler of this over and mark maiden
        $st = $pdo->prepare("
            SELECT DISTINCT bowler_id FROM ball_by_ball
            WHERE match_id = :match_id AND innings = :innings AND over_number = :over
        ");
        $st->execute([
            ':match_id' => $match_id,
            ':innings' => $innings,
            ':over' => $currentOver
        ]);
        $bowler = $st->fetch();

        if ($bowler && $bowler['bowler_id']) {
            $pdo->prepare("
                UPDATE player_match_stats
                SET maiden_overs = maiden_overs + 1
                WHERE match_id = :match_id 
                    AND player_id = :player_id
                    AND innings_number = :innings
            ")->execute([
                ':match_id' => $match_id,
                ':player_id' => $bowler['bowler_id'],
                ':innings' => $innings
            ]);

            FantasyPointsCalculator::recalculate_and_update_player_points($match_id, $bowler['bowler_id'], $innings);
        }
    }
}

/**
 * Mark end of innings
 */
function end_innings(int $match_id, int $innings): void
{
    $pdo = db();

    $statusColumnExists = false;
    try {
        $check = $pdo->query("SHOW COLUMNS FROM matches LIKE 'status'");
        $statusColumnExists = (bool)$check && (bool)$check->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
    }

    if ($statusColumnExists) {
        $pdo->prepare("UPDATE matches SET status = 'innings_break' WHERE id = :match_id")
            ->execute([':match_id' => $match_id]);
    }
}

/**
 * Update striker information
 */
function update_striker_info(int $match_id, int $innings): void
{
    $pdo = db();
    $striker_id = (int)($_POST['striker_id'] ?? 0);
    $non_striker_id = (int)($_POST['non_striker_id'] ?? 0);

    if (!$striker_id || !$non_striker_id) {
        throw new Exception('Invalid player IDs');
    }

    // Get last ball and swap strike
    $st = $pdo->prepare("
        SELECT MAX(id) as last_id FROM ball_by_ball
        WHERE match_id = :match_id AND innings = :innings
    ");
    $st->execute([':match_id' => $match_id, ':innings' => $innings]);
    $result = $st->fetch();

    if ($result['last_id']) {
        $pdo->prepare("
            UPDATE ball_by_ball
            SET striker_id = :new_striker, non_striker_id = :new_non_striker
            WHERE id > :last_id AND match_id = :match_id AND innings = :innings
        ")->execute([
            ':new_striker' => $striker_id,
            ':new_non_striker' => $non_striker_id,
            ':last_id' => $result['last_id'],
            ':match_id' => $match_id,
            ':innings' => $innings
        ]);
    }
}
