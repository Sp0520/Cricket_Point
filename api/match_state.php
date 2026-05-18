<?php
declare(strict_types=1);

/**
 * API: Get Match State
 * Returns current match scoreboard data for live updates
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database/cricket_db.php';

header('Content-Type: application/json');

try {
    $match_id = (int)($_GET['match_id'] ?? 0);
    $innings = (int)($_GET['innings'] ?? 1);

    if ($match_id <= 0) {
        throw new Exception('Invalid match ID');
    }

    $match = get_match_details($match_id);
    if (!$match) {
        throw new Exception('Match not found');
    }

    $score = get_match_score($match_id, $innings);
    $lastSixBalls = get_last_6_balls($match_id, $innings);
    $battingTeamId = (int)$match['batting_team_id'];
    $bowlingTeamId = (int)$match['bowling_team_id'];

    // Get batting lineup
    $battingLineup = get_batting_lineup($battingTeamId, $match_id, $innings);
    $bowlingLineup = get_bowling_lineup($bowlingTeamId, $match_id, $innings);

    // Format score
    $overs = (int)$score['overs_completed'];
    $balls = (int)$score['balls_in_over'];
    $oversDisplay = $balls > 0 ? "{$overs}.{$balls}" : "{$overs}.0";
    $runRate = 0;
    if ($score['overs_completed'] > 0 || ($score['overs_completed'] === 0 && $balls > 0)) {
        $totalOvers = $score['overs_completed'] + ($balls / 6);
        $runRate = $totalOvers > 0 ? round((int)$score['total_runs'] / $totalOvers, 2) : 0;
    }

    $response = [
        'match_id' => $match_id,
        'match_name' => $match['match_name'],
        'status' => $match['status'] ?? 'scheduled',
        'innings' => $innings,
        'batting_team' => [
            'id' => $battingTeamId,
            'name' => $match['batting_team_name']
        ],
        'bowling_team' => [
            'id' => $bowlingTeamId,
            'name' => $match['bowling_team_name']
        ],
        'score' => [
            'total_runs' => (int)$score['total_runs'],
            'wickets' => (int)$score['wickets'],
            'overs' => $oversDisplay,
            'run_rate' => $runRate
        ],
        'batting_lineup' => $battingLineup,
        'bowling_lineup' => $bowlingLineup,
        'last_six_balls' => array_map(function ($ball) {
            return format_ball_display($ball);
        }, $lastSixBalls),
        'next_striker' => [
            'id' => $match['striker_id'] ?? null,
            'name' => $match['striker_name'] ?? null,
            'runs' => (int)get_batsman_stats($match['id'], $match['striker_id'] ?? 0, $innings)['runs'] ?? 0,
            'balls' => (int)get_batsman_stats($match['id'], $match['striker_id'] ?? 0, $innings)['balls_faced'] ?? 0
        ],
        'non_striker' => [
            'id' => $match['non_striker_id'] ?? null,
            'name' => $match['non_striker_name'] ?? null,
            'runs' => (int)get_batsman_stats($match['id'], $match['non_striker_id'] ?? 0, $innings)['runs'] ?? 0,
            'balls' => (int)get_batsman_stats($match['id'], $match['non_striker_id'] ?? 0, $innings)['balls_faced'] ?? 0
        ],
        'bowler' => [
            'id' => $match['bowler_id'] ?? null,
            'name' => $match['bowler_name'] ?? null,
            'overs' => get_bowler_overs_display($match['id'], $match['bowler_id'] ?? 0, $innings),
            'wickets' => (int)get_bowler_stats($match['id'], $match['bowler_id'] ?? 0, $innings)['wickets'] ?? 0
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Format ball display for timeline
 */
function format_ball_display(array $ball): array
{
    $display = '';
    $runs = (int)$ball['total_runs'];

    if ($ball['is_wicket']) {
        $display = 'W';
    } elseif ($ball['extra_type'] === 'wide') {
        $display = 'Wd';
    } elseif ($ball['extra_type'] === 'no_ball') {
        $display = 'Nb';
    } elseif ($ball['extra_type'] === 'bye') {
        $display = 'B' . ($runs > 0 ? $runs : '');
    } elseif ($ball['extra_type'] === 'leg_bye') {
        $display = 'Lb' . ($runs > 0 ? $runs : '');
    } else {
        $display = (string)$runs;
    }

    return [
        'display' => $display,
        'runs' => $runs,
        'is_wicket' => (bool)$ball['is_wicket'],
        'strike' => $ball['striker_name'] ?? '',
        'bowler' => $ball['bowler_name'] ?? ''
    ];
}

/**
 * Get bowler's overs in display format
 */
function get_bowler_overs_display(int $match_id, int $bowler_id, int $innings): string
{
    if ($bowler_id <= 0) return '';

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
