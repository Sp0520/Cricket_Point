<?php
declare(strict_types=1);

/**
 * API: Get Player Stats
 * Returns player performance statistics
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database/cricket_db.php';
require_once __DIR__ . '/../database/points_calculator.php';

header('Content-Type: application/json');

try {
    $match_id = (int)($_GET['match_id'] ?? 0);
    $player_id = (int)($_GET['player_id'] ?? 0);
    $innings = (int)($_GET['innings'] ?? 1);

    if ($match_id <= 0 || $player_id <= 0) {
        throw new Exception('Invalid match or player ID');
    }

    $pdo = db();

    // Get player info
    $st = $pdo->prepare("
        SELECT id, full_name FROM players WHERE id = :player_id
    ");
    $st->execute([':player_id' => $player_id]);
    $player = $st->fetch();

    if (!$player) {
        throw new Exception('Player not found');
    }

    // Get match stats
    $stats = get_batsman_stats($match_id, $player_id, $innings);

    // Get fantasy points
    $fantasyPoints = FantasyPointsCalculator::get_player_fantasy_points($match_id, $player_id, $innings);

    $response = [
        'player_id' => $player_id,
        'player_name' => $player['full_name'],
        'match_id' => $match_id,
        'innings' => $innings,
        'batting' => [
            'runs' => (int)$stats['runs'],
            'balls_faced' => (int)$stats['balls_faced'],
            'fours' => (int)$stats['fours'],
            'sixes' => (int)$stats['sixes'],
            'strike_rate' => (float)$stats['strike_rate'],
            'is_out' => (bool)$stats['is_out']
        ],
        'bowling' => [
            'overs' => get_bowler_overs_display($match_id, $player_id, $innings),
            'runs_conceded' => (int)$stats['runs_conceded'],
            'wickets' => (int)$stats['wickets'],
            'economy' => (float)$stats['economy'],
            'maiden_overs' => (int)$stats['maiden_overs']
        ],
        'fielding' => [
            'catches' => (int)$stats['catches'],
            'runouts' => (int)$stats['runouts'],
            'stumpings' => (int)$stats['stumpings']
        ],
        'fantasy_points' => $fantasyPoints
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Get bowler's overs in display format
 */
function get_bowler_overs_display(int $match_id, int $bowler_id, int $innings): string
{
    if ($bowler_id <= 0) return '0.0';

    $st = db()->prepare("
        SELECT COALESCE(balls_bowled, 0) as balls_bowled
        FROM player_match_stats
        WHERE match_id = :match_id 
            AND player_id = :bowler_id
            AND innings_number = :innings
    ");
    $st->execute([
        ':match_id' => $match_id,
        ':bowler_id' => $bowler_id,
        ':innings' => $innings
    ]);
    $result = $st->fetch();
    $ballsBowled = (int)($result['balls_bowled'] ?? 0);
    $overs = intdiv($ballsBowled, 6);
    $balls = $ballsBowled % 6;
    return $balls > 0 ? "{$overs}.{$balls}" : "{$overs}.0";
}
