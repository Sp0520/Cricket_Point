<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/access.php';

require_admin_or_organizer();

$pdo = db();

$err = '';
$ok = '';

/* =========================
   FETCH TEAMS
========================= */

$teams = $pdo->query("
    SELECT id, team_name
    FROM teams
    ORDER BY team_name
")->fetchAll();

/* =========================
   CREATE MATCH
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim((string)($_POST['match_name'] ?? ''));
    $date = (string)($_POST['match_date'] ?? '');
    $time = trim((string)($_POST['match_time'] ?? ''));
    $venue = trim((string)($_POST['venue'] ?? ''));

    $teamAId = (int)($_POST['team_a_id'] ?? 0);
    $teamBId = (int)($_POST['team_b_id'] ?? 0);

    $oversLimit = max(1, (int)($_POST['overs_limit'] ?? 20));
    $wicketsLimit = max(1, (int)($_POST['wickets_limit'] ?? 10));

    $tournamentId = (int)($_POST['tournament_id'] ?? 0);
    $tournamentId = $tournamentId > 0 ? $tournamentId : null;

    if (
        $name === '' ||
        $date === '' ||
        $teamAId <= 0 ||
        $teamBId <= 0 ||
        $teamAId === $teamBId
    ) {

        $err = 'Match name, date and two different teams are required.';

    } else {

        $ownerId = organizer_owner_user_id();

        if ($tournamentId !== null && is_organizer_user()) {

            $chk = $pdo->prepare("
                SELECT *
                FROM tournaments
                WHERE id = :id
                LIMIT 1
            ");

            $chk->execute([
                ':id' => $tournamentId
            ]);

            $trow = $chk->fetch();

            if (!$trow || !can_access_tournament_row($trow)) {
                $err = 'You cannot attach this match to that tournament.';
            }
        }

        if ($err === '') {

            $st = $pdo->prepare("
                INSERT INTO matches
                (
                    match_name,
                    match_date,
                    match_time,
                    venue,
                    tournament_id,
                    team_a_id,
                    team_b_id,
                    overs_limit,
                    wickets_limit,
                    owner_user_id,
                    status
                )
                VALUES
                (
                    :n,
                    :d,
                    :tm,
                    :v,
                    :tid,
                    :ta,
                    :tb,
                    :ov,
                    :wk,
                    :oid,
                    :st
                )
            ");

            $st->execute([
                ':n'   => $name,
                ':d'   => $date,
                ':tm'  => ($time === '' ? null : $time),
                ':v'   => ($venue === '' ? null : $venue),
                ':tid' => $tournamentId,
                ':ta'  => $teamAId,
                ':tb'  => $teamBId,
                ':ov'  => $oversLimit,
                ':wk'  => $wicketsLimit,
                ':oid' => $ownerId,
                ':st'  => 'setup',
            ]);

            $ok = 'Match created successfully.';
        }
    }
}

/* =========================
   FETCH TOURNAMENTS
========================= */

if (is_organizer_user()) {

    $oid = organizer_owner_user_id();

    $st = $pdo->prepare("
        SELECT id, tournament_name
        FROM tournaments
        WHERE owner_user_id = :o
        ORDER BY start_date DESC, id DESC
    ");

    $st->execute([
        ':o' => $oid
    ]);

    $tournaments = $st->fetchAll();

} else {

    $tournaments = $pdo->query("
        SELECT id, tournament_name
        FROM tournaments
        ORDER BY start_date DESC, id DESC
    ")->fetchAll();
}

/* =========================
   FETCH MATCHES
========================= */

if (is_organizer_user()) {

    $oid = organizer_owner_user_id();

    $st = $pdo->prepare("
        SELECT
            m.*,
            p.full_name AS mom_name,
            tm.tournament_name,
            ta.team_name AS team_a_name,
            tb.team_name AS team_b_name
        FROM matches m

        LEFT JOIN players p
            ON p.id = m.man_of_match_player_id

        LEFT JOIN tournaments tm
            ON tm.id = m.tournament_id

        LEFT JOIN teams ta
            ON ta.id = m.team_a_id

        LEFT JOIN teams tb
            ON tb.id = m.team_b_id

        WHERE m.owner_user_id = :oid

        ORDER BY m.match_date DESC, m.id DESC
    ");

    $st->execute([
        ':oid' => $oid
    ]);

    $matches = $st->fetchAll();

} else {

    $matches = $pdo->query("
        SELECT
            m.*,
            p.full_name AS mom_name,
            tm.tournament_name,
            ta.team_name AS team_a_name,
            tb.team_name AS team_b_name
        FROM matches m

        LEFT JOIN players p
            ON p.id = m.man_of_match_player_id

        LEFT JOIN tournaments tm
            ON tm.id = m.tournament_id

        LEFT JOIN teams ta
            ON ta.id = m.team_a_id

        LEFT JOIN teams tb
            ON tb.id = m.team_b_id

        ORDER BY m.match_date DESC, m.id DESC
    ")->fetchAll();
}

require_once __DIR__ . '/header.php';

$orgShell = is_organizer_user();
?>

<!-- YOUR HTML CODE CONTINUES HERE -->