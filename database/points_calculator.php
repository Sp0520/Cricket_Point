<?php
declare(strict_types=1);

/**
 * Dream11 Fantasy Cricket Points Calculation
 * Automatically calculates player points based on performance
 */

class FantasyPointsCalculator
{
    // BATSMAN POINTS
    const BATSMAN_PER_RUN = 1;
    const BATSMAN_4_BONUS = 1;      // +1 for hitting 4
    const BATSMAN_6_BONUS = 2;      // +2 for hitting 6
    const BATSMAN_50_BONUS = 8;     // +8 for 50 runs
    const BATSMAN_100_BONUS = 16;   // +16 for 100 runs
    const BATSMAN_DUCK = -2;        // -2 for duck

    // BOWLER POINTS
    const BOWLER_WICKET = 25;       // +25 for wicket
    const BOWLER_3_WICKET_BONUS = 8;    // +8 for 3 wickets
    const BOWLER_5_WICKET_BONUS = 16;   // +16 for 5 wickets
    const BOWLER_MAIDEN_OVER = 12;      // +12 for maiden over

    // FIELDING POINTS
    const FIELDING_CATCH = 8;       // +8 for catch
    const FIELDING_RUNOUT = 12;     // +12 for run-out
    const FIELDING_STUMPING = 12;   // +12 for stumping

    // STRIKE RATE BONUS (batsman)
    const STRIKE_RATE_ABOVE_170 = 6;
    const STRIKE_RATE_150_170 = 4;
    const STRIKE_RATE_130_150 = 2;

    // ECONOMY BONUS (bowler, if bowled at least 1 over)
    const ECONOMY_BELOW_5 = 6;
    const ECONOMY_5_6 = 4;
    const ECONOMY_6_7 = 2;

    /**
     * Calculate total fantasy points for a batsman
     */
    public static function calculate_batsman_points(
        int $runs,
        int $fours,
        int $sixes,
        int $balls_faced,
        bool $is_out,
        bool $is_duck = false
    ): int {
        $points = 0;

        // Base runs
        $points += $runs * self::BATSMAN_PER_RUN;

        // Bonus for 4s and 6s
        $points += $fours * self::BATSMAN_4_BONUS;
        $points += $sixes * self::BATSMAN_6_BONUS;

        // Half-century bonus (worth 50 runs total)
        if ($runs >= 50 && $runs < 100) {
            $points += self::BATSMAN_50_BONUS;
        }

        // Century bonus (worth 100+ runs)
        if ($runs >= 100) {
            $points += self::BATSMAN_100_BONUS;
        }

        // Duck penalty
        if ($is_duck && $is_out) {
            $points += self::BATSMAN_DUCK;
        }

        // Strike rate bonus
        if ($balls_faced > 0) {
            $strike_rate = ($runs / $balls_faced) * 100;
            if ($balls_faced >= 20) { // Only apply if batsman faced enough balls
                if ($strike_rate > 170) {
                    $points += self::STRIKE_RATE_ABOVE_170;
                } elseif ($strike_rate >= 150) {
                    $points += self::STRIKE_RATE_150_170;
                } elseif ($strike_rate >= 130) {
                    $points += self::STRIKE_RATE_130_150;
                }
            }
        }

        return max(0, $points); // Ensure non-negative points
    }

    /**
     * Calculate total fantasy points for a bowler
     */
    public static function calculate_bowler_points(
        int $wickets,
        int $runs_conceded,
        int $balls_bowled,
        int $maiden_overs = 0
    ): int {
        $points = 0;

        // Wicket points
        $points += $wickets * self::BOWLER_WICKET;

        // Wicket milestones
        if ($wickets >= 5) {
            $points += self::BOWLER_5_WICKET_BONUS;
        } elseif ($wickets >= 3) {
            $points += self::BOWLER_3_WICKET_BONUS;
        }

        // Maiden over bonus
        $points += $maiden_overs * self::BOWLER_MAIDEN_OVER;

        // Economy bonus (if bowled at least 1 over = 6 balls)
        if ($balls_bowled >= 6) {
            $overs = $balls_bowled / 6;
            $economy = $runs_conceded / $overs;

            if ($economy < 5) {
                $points += self::ECONOMY_BELOW_5;
            } elseif ($economy < 6) {
                $points += self::ECONOMY_5_6;
            } elseif ($economy < 7) {
                $points += self::ECONOMY_6_7;
            }
        }

        return max(0, $points);
    }

    /**
     * Calculate fielding points (catch, runout, stumping)
     */
    public static function calculate_fielding_points(
        int $catches = 0,
        int $runouts = 0,
        int $stumpings = 0
    ): int {
        $points = 0;
        $points += $catches * self::FIELDING_CATCH;
        $points += $runouts * self::FIELDING_RUNOUT;
        $points += $stumpings * self::FIELDING_STUMPING;
        return $points;
    }

    /**
     * Calculate and update points in database for a specific player in a match
     */
    public static function recalculate_and_update_player_points(
        int $match_id,
        int $player_id,
        int $innings = 1
    ): void {
        $pdo = db();

        // Get player stats
        $st = $pdo->prepare("
            SELECT 
                runs, balls_faced, fours, sixes, wickets,
                catches, runouts, stumpings, 
                maiden_overs, runs_conceded, balls_bowled,
                is_out
            FROM player_match_stats
            WHERE match_id = :match_id 
                AND player_id = :player_id
                AND innings_number = :innings
        ");
        $st->execute([
            ':match_id' => $match_id,
            ':player_id' => $player_id,
            ':innings' => $innings
        ]);
        $stats = $st->fetch();

        if (!$stats) {
            return; // No stats for this player
        }

        $runs = (int)$stats['runs'];
        $balls_faced = (int)$stats['balls_faced'];
        $fours = (int)$stats['fours'];
        $sixes = (int)$stats['sixes'];
        $wickets = (int)$stats['wickets'];
        $catches = (int)$stats['catches'];
        $runouts = (int)$stats['runouts'];
        $stumpings = (int)$stats['stumpings'];
        $maiden_overs = (int)$stats['maiden_overs'];
        $runs_conceded = (int)$stats['runs_conceded'];
        $balls_bowled = (int)$stats['balls_bowled'];
        $is_out = (int)$stats['is_out'];

        // Determine if duck (out on 0 runs)
        $is_duck = ($runs === 0 && $is_out === 1);

        // Calculate points for batting
        $batting_points = self::calculate_batsman_points(
            $runs,
            $fours,
            $sixes,
            $balls_faced,
            $is_out === 1,
            $is_duck
        );

        // Calculate points for bowling
        $bowling_points = self::calculate_bowler_points(
            $wickets,
            $runs_conceded,
            $balls_bowled,
            $maiden_overs
        );

        // Calculate points for fielding
        $fielding_points = self::calculate_fielding_points(
            $catches,
            $runouts,
            $stumpings
        );

        $total_points = $batting_points + $bowling_points + $fielding_points;

        // Insert or update player points
        $st = $pdo->prepare("
            INSERT INTO player_points (match_id, player_id, innings, batting_pts, bowling_pts, fielding_pts, bonus_pts, total_pts)
            VALUES (:match_id, :player_id, :innings, :batting_pts, :bowling_pts, :fielding_pts, 0, :total_pts)
            ON DUPLICATE KEY UPDATE
                batting_pts = VALUES(batting_pts),
                bowling_pts = VALUES(bowling_pts),
                fielding_pts = VALUES(fielding_pts),
                total_pts = VALUES(total_pts),
                updated_at = CURRENT_TIMESTAMP
        ");
        $st->execute([
            ':match_id' => $match_id,
            ':player_id' => $player_id,
            ':innings' => $innings,
            ':batting_pts' => $batting_points,
            ':bowling_pts' => $bowling_points,
            ':fielding_pts' => $fielding_points,
            ':total_pts' => $total_points
        ]);

        // Also update the fantasy_points column in player_match_stats
        $st = $pdo->prepare("
            UPDATE player_match_stats
            SET fantasy_points = :total_pts
            WHERE match_id = :match_id 
                AND player_id = :player_id
                AND innings_number = :innings
        ");
        $st->execute([
            ':total_pts' => $total_points,
            ':match_id' => $match_id,
            ':player_id' => $player_id,
            ':innings' => $innings
        ]);
    }

    /**
     * Get fantasy points for a specific player in a match
     */
    public static function get_player_fantasy_points(
        int $match_id,
        int $player_id,
        int $innings = 1
    ): int {
        $st = db()->prepare("
            SELECT total_pts FROM player_points
            WHERE match_id = :match_id 
                AND player_id = :player_id
                AND innings = :innings
            LIMIT 1
        ");
        $st->execute([
            ':match_id' => $match_id,
            ':player_id' => $player_id,
            ':innings' => $innings
        ]);
        $result = $st->fetch();
        return $result ? (int)$result['total_pts'] : 0;
    }
}
