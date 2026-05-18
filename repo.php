<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function fetch_leaderboard(?string $q = null): array
{
    $pdo = db();
    $q = trim((string)$q);
    $params = [];
    $where = '';
    if ($q !== '') {
        $where = 'WHERE p.full_name LIKE :q';
        $params[':q'] = '%' . $q . '%';
    }

    $sql = "
      SELECT
        p.id,
        p.full_name,
        p.photo_path,
        COALESCE(SUM(s.points), 0) AS total_points
      FROM players p
      LEFT JOIN player_match_stats s ON s.player_id = p.id
      $where
      GROUP BY p.id, p.full_name, p.photo_path
      ORDER BY total_points DESC, p.full_name ASC
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();

    $rank = 0;
    $prev = null;
    foreach ($rows as $i => $r) {
        $tp = (int)$r['total_points'];
        if ($prev === null || $tp !== $prev) {
            $rank++;
            $prev = $tp;
        }
        $rows[$i]['rank'] = $rank;
    }
    return $rows;
}

function fetch_player_totals(int $playerId): array
{
    $pdo = db();
    $st = $pdo->prepare("
      SELECT
        p.id,
        p.full_name,
        p.photo_path,
        COALESCE(SUM(s.points), 0) AS total_points
      FROM players p
      LEFT JOIN player_match_stats s ON s.player_id = p.id
      WHERE p.id = :id
      GROUP BY p.id, p.full_name, p.photo_path
      LIMIT 1
    ");
    $st->execute([':id' => $playerId]);
    $row = $st->fetch();
    return $row ?: [];
}

function fetch_player_rank(int $playerId): ?int
{
    $rows = fetch_leaderboard(null);
    foreach ($rows as $r) {
        if ((int)$r['id'] === $playerId) return (int)$r['rank'];
    }
    return null;
}

function fetch_player_match_rows(int $playerId): array
{
    $pdo = db();
    $st = $pdo->prepare("
      SELECT
        m.id AS match_id,
        m.match_name,
        m.match_date,
        m.venue,
        s.runs, s.fours, s.sixes, s.wickets, s.catches, s.runouts, s.stumpings, s.maiden_overs,
        s.points
      FROM player_match_stats s
      JOIN matches m ON m.id = s.match_id
      WHERE s.player_id = :pid
      ORDER BY m.match_date DESC, m.id DESC
    ");
    $st->execute([':pid' => $playerId]);
    return $st->fetchAll();
}

function fetch_matches(): array
{
    $pdo = db();
    $st = $pdo->query("
      SELECT
        m.*,
        p.full_name AS mom_name
      FROM matches m
      LEFT JOIN players p ON p.id = m.man_of_match_player_id
      ORDER BY m.match_date DESC, m.id DESC
    ");
    return $st->fetchAll();
}

function fetch_mom_rows(): array
{
    $pdo = db();
    $st = $pdo->query("
      SELECT
        m.id,
        m.match_name,
        m.match_date,
        m.venue,
        m.man_of_match_points,
        p.id AS player_id,
        p.full_name AS player_name,
        p.photo_path
      FROM matches m
      LEFT JOIN players p ON p.id = m.man_of_match_player_id
      ORDER BY m.match_date DESC, m.id DESC
    ");
    return $st->fetchAll();
}

function fetch_player_team(int $playerId): ?array
{
    $pdo = db();
    $st = $pdo->prepare("
      SELECT
        t.id,
        t.team_name,
        t.logo_path,
        t.created_by_player_id,
        t.contact_phone
      FROM teams t
      JOIN team_players tp ON tp.team_id = t.id
      WHERE tp.player_id = :pid
      LIMIT 1
    ");
    $st->execute([':pid' => $playerId]);
    $row = $st->fetch();
    return $row ?: null;
}

function fetch_live_matches(int $limit = 6): array
{
    $pdo = db();
    $st = $pdo->prepare("
      SELECT
        m.id,
        m.match_name,
        m.match_date,
        m.venue,
        m.man_of_match_points,
        tm.tournament_name,
        p.full_name AS mom_name,
        COUNT(s.player_id) AS stats_players
      FROM matches m
      LEFT JOIN tournaments tm ON tm.id = m.tournament_id
      LEFT JOIN players p ON p.id = m.man_of_match_player_id
      LEFT JOIN player_match_stats s ON s.match_id = m.id
      WHERE m.match_date = CURDATE()
      GROUP BY m.id
      HAVING COUNT(s.player_id) > 0
      ORDER BY m.id DESC
      LIMIT :lim
    ");
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

function fetch_upcoming_matches(int $limit = 10): array
{
    $pdo = db();
    $st = $pdo->prepare("
      SELECT
        m.id,
        m.match_name,
        m.match_date,
        m.venue,
        tm.tournament_name,
        COUNT(s.player_id) AS stats_players,
        m.man_of_match_points,
        p.full_name AS mom_name
      FROM matches m
      LEFT JOIN tournaments tm ON tm.id = m.tournament_id
      LEFT JOIN player_match_stats s ON s.match_id = m.id
      LEFT JOIN players p ON p.id = m.man_of_match_player_id
      WHERE m.match_date > CURDATE()
      GROUP BY m.id
      ORDER BY m.match_date ASC, m.id DESC
      LIMIT :lim
    ");
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

function fetch_upcoming_tournaments_for_player(int $playerId, ?int $teamId, int $limit = 12): array
{
    $pdo = db();
    $st = $pdo->prepare("
      SELECT
        t.*,
        CASE
          WHEN t.registration_open_from IS NOT NULL
           AND t.registration_open_from <= CURDATE()
           AND (t.registration_open_to IS NULL OR t.registration_open_to >= CURDATE())
          THEN 1 ELSE 0
        END AS is_registration_open,
        rp.id AS player_reg_id,
        rp.status AS player_reg_status,
        rt.id AS team_reg_id,
        rt.status AS team_reg_status
      FROM tournaments t
      LEFT JOIN tournament_registrations rp
        ON rp.tournament_id = t.id
       AND rp.registrant_type = 'player'
       AND rp.player_id = :pid
      LEFT JOIN tournament_registrations rt
        ON rt.tournament_id = t.id
       AND rt.registrant_type = 'team'
       AND (:teamId IS NOT NULL AND rt.team_id = :teamId)
      WHERE t.start_date >= CURDATE()
      ORDER BY t.start_date ASC, t.id ASC
      LIMIT :lim
    ");
    $st->bindValue(':pid', $playerId, PDO::PARAM_INT);
    $st->bindValue(':teamId', $teamId, $teamId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

function fetch_tournament(int $tournamentId): ?array
{
    $pdo = db();
    $st = $pdo->prepare("
      SELECT *
      FROM tournaments
      WHERE id = :id
      LIMIT 1
    ");
    $st->execute([':id' => $tournamentId]);
    $row = $st->fetch();
    return $row ?: null;
}

function fetch_match_scorecard(int $matchId): array
{
    $pdo = db();
    $st = $pdo->prepare("
      SELECT
        s.player_id,
        p.full_name,
        p.photo_path,
        s.runs,
        s.fours,
        s.sixes,
        s.wickets,
        s.catches,
        s.runouts,
        s.stumpings,
        s.maiden_overs,
        s.points
      FROM player_match_stats s
      JOIN players p ON p.id = s.player_id
      WHERE s.match_id = :mid
      ORDER BY s.points DESC, s.runs DESC, p.full_name ASC
    ");
    $st->execute([':mid' => $matchId]);
    return $st->fetchAll();
}

function fetch_match_with_tournament(int $matchId): ?array
{
    $pdo = db();
    $st = $pdo->prepare("
      SELECT
        m.*,
        tm.tournament_name,
        mom.full_name AS mom_name
      FROM matches m
      LEFT JOIN tournaments tm ON tm.id = m.tournament_id
      LEFT JOIN players mom ON mom.id = m.man_of_match_player_id
      WHERE m.id = :id
      LIMIT 1
    ");
    $st->execute([':id' => $matchId]);
    $row = $st->fetch();
    return $row ?: null;
}

