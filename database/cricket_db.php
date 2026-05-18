<?php
declare(strict_types=1);

/**
 * Cricket Database Utility Functions
 * Handles all cricket-specific database operations
 */

function get_team_players(int $team_id): array
{
    $st = db()->prepare("
        SELECT p.id, p.full_name
        FROM players p
        JOIN team_players tp ON tp.player_id = p.id
        WHERE tp.team_id = :team_id
        ORDER BY p.full_name
    ");
    $st->execute([':team_id' => $team_id]);
    return $st->fetchAll();
}

function get_match_details(int $match_id): ?array
{
    $st = db()->prepare("
        SELECT m.*, 
               bt.team_name as batting_team_name,
               bwt.team_name as bowling_team_name,
               striker.full_name as striker_name,
               non_striker.full_name as non_striker_name,
               bowler.full_name as bowler_name
        FROM matches m
        LEFT JOIN teams bt ON bt.id = m.batting_team_id
        LEFT JOIN teams bwt ON bwt.id = m.bowling_team_id
        LEFT JOIN players striker ON striker.id = (
            SELECT striker_id FROM ball_by_ball 
            WHERE match_id = m.id 
            ORDER BY over_number DESC, ball_number DESC LIMIT 1
        )
        LEFT JOIN players non_striker ON non_striker.id = (
            SELECT non_striker_id FROM ball_by_ball 
            WHERE match_id = m.id 
            ORDER BY over_number DESC, ball_number DESC LIMIT 1
        )
        LEFT JOIN players bowler ON bowler.id = (
            SELECT bowler_id FROM ball_by_ball 
            WHERE match_id = m.id 
            ORDER BY id DESC LIMIT 1
        )
        WHERE m.id = :match_id
    ");
    $st->execute([':match_id' => $match_id]);
    return $st->fetch() ?: null;
}

function get_match_score(int $match_id, int $innings = 1): array
{
    $st = db()->prepare("
        SELECT 
            SUM(total_runs) as total_runs,
            COUNT(CASE WHEN is_wicket = 1 THEN 1 END) as wickets,
            MAX(over_number) as overs_completed,
            MAX(ball_number) as balls_in_over
        FROM ball_by_ball
        WHERE match_id = :match_id AND innings = :innings
    ");
    $st->execute([':match_id' => $match_id, ':innings' => $innings]);
    $result = $st->fetch() ?: [
        'total_runs' => 0,
        'wickets' => 0,
        'overs_completed' => 0,
        'balls_in_over' => 0
    ];
    return $result;
}

function get_batsman_stats(int $match_id, int $player_id, int $innings = 1): array
{
    $st = db()->prepare("
        SELECT pms.*
        FROM player_match_stats pms
        WHERE pms.match_id = :match_id 
            AND pms.player_id = :player_id
            AND pms.innings_number = :innings
    ");
    $st->execute([
        ':match_id' => $match_id,
        ':player_id' => $player_id,
        ':innings' => $innings
    ]);
    return $st->fetch() ?: [
        'runs' => 0,
        'balls_faced' => 0,
        'fours' => 0,
        'sixes' => 0,
        'strike_rate' => 0,
        'is_out' => 0
    ];
}

function get_bowler_stats(int $match_id, int $player_id, int $innings = 1): array
{
    $st = db()->prepare("
        SELECT pms.*
        FROM player_match_stats pms
        WHERE pms.match_id = :match_id 
            AND pms.player_id = :player_id
            AND pms.innings_number = :innings
    ");
    $st->execute([
        ':match_id' => $match_id,
        ':player_id' => $player_id,
        ':innings' => $innings
    ]);
    return $st->fetch() ?: [
        'balls_bowled' => 0,
        'runs_conceded' => 0,
        'wickets' => 0,
        'economy' => 0,
        'maiden_overs' => 0
    ];
}

function get_last_6_balls(int $match_id, int $innings = 1): array
{
    $st = db()->prepare("
        SELECT bbb.*, 
               striker.full_name as striker_name,
               bowler.full_name as bowler_name
        FROM ball_by_ball bbb
        LEFT JOIN players striker ON striker.id = bbb.striker_id
        LEFT JOIN players bowler ON bowler.id = bbb.bowler_id
        WHERE bbb.match_id = :match_id AND bbb.innings = :innings
        ORDER BY bbb.id DESC
        LIMIT 6
    ");
    $st->execute([':match_id' => $match_id, ':innings' => $innings]);
    return array_reverse($st->fetchAll() ?: []);
}

function get_batting_lineup(int $team_id, int $match_id, int $innings = 1): array
{
    $st = db()->prepare("
        SELECT 
            p.id, p.full_name,
            pms.runs, pms.balls_faced, pms.fours, pms.sixes,
            pms.is_out,
            COALESCE(pms.strike_rate, 0) as strike_rate
        FROM players p
        JOIN team_players tp ON tp.player_id = p.id
        LEFT JOIN player_match_stats pms ON (
            pms.match_id = :match_id 
            AND pms.player_id = p.id
            AND pms.innings_number = :innings
        )
        WHERE tp.team_id = :team_id
        ORDER BY CASE WHEN pms.runs IS NOT NULL THEN 0 ELSE 1 END,
                 pms.runs DESC
    ");
    $st->execute([
        ':team_id' => $team_id,
        ':match_id' => $match_id,
        ':innings' => $innings
    ]);
    return $st->fetchAll() ?: [];
}

function get_bowling_lineup(int $team_id, int $match_id, int $innings = 1): array
{
    $st = db()->prepare("
        SELECT 
            p.id, p.full_name,
            COALESCE(pms.balls_bowled, 0) as balls_bowled,
            COALESCE(pms.runs_conceded, 0) as runs_conceded,
            COALESCE(pms.wickets, 0) as wickets,
            COALESCE(pms.economy, 0) as economy,
            COALESCE(pms.maiden_overs, 0) as maiden_overs
        FROM players p
        JOIN team_players tp ON tp.player_id = p.id
        LEFT JOIN player_match_stats pms ON (
            pms.match_id = :match_id 
            AND pms.player_id = p.id
            AND pms.innings_number = :innings
        )
        WHERE tp.team_id = :team_id
        ORDER BY COALESCE(pms.balls_bowled, 0) DESC
    ");
    $st->execute([
        ':team_id' => $team_id,
        ':match_id' => $match_id,
        ':innings' => $innings
    ]);
    return $st->fetchAll() ?: [];
}

function record_ball(
    int $match_id,
    int $innings,
    int $over_number,
    int $ball_number,
    int $bowler_id,
    int $striker_id,
    int $non_striker_id,
    int $runs_off_bat,
    string $extra_type = 'none',
    int $extra_runs = 0,
    bool $is_wicket = false,
    string $wicket_type = 'none',
    ?int $fielder_id = null
): ?int
{
    $is_legal = 1;
    $total_runs = $runs_off_bat + $extra_runs;

    if ($extra_type !== 'none') {
        $is_legal = ($extra_type === 'bye' || $extra_type === 'leg_bye') ? 1 : 0;
    }

    $st = db()->prepare("
        INSERT INTO ball_by_ball (
            match_id, innings, over_number, ball_number,
            bowler_id, striker_id, non_striker_id,
            runs_off_bat, extras, extra_type,
            is_wicket, wicket_type, fielder_id,
            total_runs, is_legal
        ) VALUES (
            :match_id, :innings, :over_number, :ball_number,
            :bowler_id, :striker_id, :non_striker_id,
            :runs_off_bat, :extra_runs, :extra_type,
            :is_wicket, :wicket_type, :fielder_id,
            :total_runs, :is_legal
        )
    ");

    $st->execute([
        ':match_id' => $match_id,
        ':innings' => $innings,
        ':over_number' => $over_number,
        ':ball_number' => $ball_number,
        ':bowler_id' => $bowler_id,
        ':striker_id' => $striker_id,
        ':non_striker_id' => $non_striker_id,
        ':runs_off_bat' => $runs_off_bat,
        ':extra_runs' => $extra_runs,
        ':extra_type' => $extra_type,
        ':is_wicket' => $is_wicket ? 1 : 0,
        ':wicket_type' => $wicket_type,
        ':fielder_id' => $fielder_id,
        ':total_runs' => $total_runs,
        ':is_legal' => $is_legal
    ]);

    return db()->lastInsertId() ? (int)db()->lastInsertId() : null;
}

function get_next_ball_position(int $match_id, int $innings): array
{
    $st = db()->prepare("
        SELECT 
            COALESCE(MAX(over_number), 0) as over_number,
            COALESCE(MAX(CASE WHEN over_number = MAX(over_number) THEN ball_number ELSE 0 END), 0) as ball_number
        FROM ball_by_ball
        WHERE match_id = :match_id AND innings = :innings
    ");
    $st->execute([':match_id' => $match_id, ':innings' => $innings]);
    $result = $st->fetch() ?: ['over_number' => 0, 'ball_number' => 0];

    $over = (int)$result['over_number'];
    $ball = (int)$result['ball_number'];

    if ($ball >= 6) {
        $over++;
        $ball = 0;
    }

    return ['over_number' => $over, 'ball_number' => $ball + 1];
}
