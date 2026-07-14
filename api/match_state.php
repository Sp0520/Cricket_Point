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

    // Get all balls history for graphs and last ball details
    $stHistory = $pdo->prepare("
        SELECT id, over_number, ball_number, runs_off_bat, extras, extra_type, total_runs, is_wicket, wicket_type
        FROM ball_by_ball
        WHERE match_id = :match_id AND innings = :innings
        ORDER BY id ASC
    ");
    $stHistory->execute([':match_id' => $match_id, ':innings' => $innings]);
    $historyBalls = $stHistory->fetchAll() ?: [];

    $lastBall = count($historyBalls) > 0 ? $historyBalls[count($historyBalls) - 1] : null;
    $lastBallDetails = null;
    if ($lastBall) {
        $lastBallDetails = [
            'id' => (int)$lastBall['id'],
            'over' => (int)$lastBall['over_number'],
            'ball' => (int)$lastBall['ball_number'],
            'runs' => (int)$lastBall['runs_off_bat'],
            'extras' => (int)$lastBall['extras'],
            'extra_type' => $lastBall['extra_type'],
            'total_runs' => (int)$lastBall['total_runs'],
            'is_wicket' => (bool)$lastBall['is_wicket'],
            'wicket_type' => $lastBall['wicket_type']
        ];
    }

    $cumulativeRuns = 0;
    $cumulativeWickets = 0;
    $graphData = [];
    foreach ($historyBalls as $b) {
        $cumulativeRuns += (int)$b['total_runs'];
        if ($b['is_wicket']) {
            $cumulativeWickets++;
        }
        $graphData[] = [
            'ball_display' => "{$b['over_number']}.{$b['ball_number']}",
            'cumulative_runs' => $cumulativeRuns,
            'wickets' => $cumulativeWickets,
            'runs_off_this_ball' => (int)$b['total_runs']
        ];
    }

    $runsPerOver = [];
    $overLabels = [];
    $overRunsTemp = 0;
    $currentOverNum = 0;
    foreach ($historyBalls as $b) {
        if ($b['over_number'] !== $currentOverNum) {
            $runsPerOver[] = $overRunsTemp;
            $overLabels[] = "Over " . ($currentOverNum + 1);
            $overRunsTemp = 0;
            $currentOverNum = $b['over_number'];
        }
        $overRunsTemp += (int)$b['total_runs'];
    }
    if (count($historyBalls) > 0) {
        $runsPerOver[] = $overRunsTemp;
        $overLabels[] = "Over " . ($currentOverNum + 1);
    }

    // Target & Required Run Rate calculations for second innings
    $target = null;
    $runsNeeded = null;
    $rrr = 0.00;
    if ($innings === 2) {
        $scoreInn1 = get_match_score($match_id, 1);
        $target = (int)$scoreInn1['total_runs'] + 1;
        $runsNeeded = $target - (int)$score['total_runs'];
        
        $stLimit = $pdo->prepare("SELECT total_overs FROM matches WHERE id = :id");
        $stLimit->execute([':id' => $match_id]);
        $matchLimit = $stLimit->fetch();
        $totalOversLimit = (int)($matchLimit['total_overs'] ?? 20);
        
        $ballsCompleted = ($overs * 6) + $balls;
        $totalBallsLimit = $totalOversLimit * 6;
        $ballsRemaining = max(0, $totalBallsLimit - $ballsCompleted);
        
        if ($ballsRemaining > 0) {
            $rrr = round(($runsNeeded / $ballsRemaining) * 6, 2);
        } else {
            $rrr = $runsNeeded > 0 ? 99.99 : 0.00;
        }
    }

    $response = [
        'match_id' => $match_id,
        'match_name' => $match['match_name'],
        'status' => $match['status'] ?? 'scheduled',
        'innings' => $innings,
        'field_setup' => $match['field_setup'] ?? 'normal',
        'last_ball_details' => $lastBallDetails,
        'graph_worm' => $graphData,
        'graph_run_rate' => [
            'labels' => $overLabels,
            'runs' => $runsPerOver
        ],
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
            'run_rate' => $runRate,
            'target' => $target,
            'runs_needed' => $runsNeeded,
            'required_run_rate' => $rrr
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
