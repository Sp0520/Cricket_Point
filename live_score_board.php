<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/access.php';

require_admin_or_organizer();

$pdo = db();

$isOrganizer = is_organizer_user();
$ownerId = organizer_owner_user_id();


/*
|--------------------------------------------------------------------------
| LOAD MATCHES
|--------------------------------------------------------------------------
*/

if ($isOrganizer && $ownerId !== null) {

    $st = $pdo->prepare("
        SELECT
            m.id,
            m.match_name,
            m.match_date,
            m.match_time,
            m.tournament_id,
            m.team_a_id,
            m.team_b_id,
            m.overs_limit,
            m.wickets_limit,
            m.status,
            m.gully_state,
            ta.team_name AS team_a_name,
            tb.team_name AS team_b_name
        FROM matches m
        LEFT JOIN teams ta ON ta.id = m.team_a_id
        LEFT JOIN teams tb ON tb.id = m.team_b_id
        LEFT JOIN tournaments t ON t.id = m.tournament_id
        WHERE t.owner_user_id = :oid
        ORDER BY m.match_date DESC
    ");

    $st->execute([
        ':oid' => $ownerId
    ]);

    $matchList = $st->fetchAll();

} else {

    $matchList = $pdo->query("
        SELECT
            m.id,
            m.match_name,
            m.match_date,
            m.match_time,
            m.tournament_id,
            m.team_a_id,
            m.team_b_id,
            m.overs_limit,
            m.wickets_limit,
            m.status,
            m.gully_state,
            ta.team_name AS team_a_name,
            tb.team_name AS team_b_name
        FROM matches m
        LEFT JOIN teams ta ON ta.id = m.team_a_id
        LEFT JOIN teams tb ON tb.id = m.team_b_id
        ORDER BY m.match_date DESC
    ")->fetchAll();
}


/*
|--------------------------------------------------------------------------
| MATCH SELECT
|--------------------------------------------------------------------------
*/

$matchId = (int)($_GET['match_id'] ?? 0);

$currentMatch = null;

foreach ($matchList as $row) {

    if ((int)$row['id'] === $matchId) {
        $currentMatch = $row;
        break;
    }
}


/*
|--------------------------------------------------------------------------
| SAVE SCORE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = (string)($_POST['action'] ?? '');
    $formMatchId = (int)($_POST['match_id'] ?? 0);

    if ($action === 'save_score' && $formMatchId > 0) {

        $gullyJson = (string)($_POST['gully_json'] ?? '');

        $st = $pdo->prepare("
            UPDATE matches
            SET gully_state = :g
            WHERE id = :id
        ");

        $st->execute([
            ':g' => $gullyJson,
            ':id' => $formMatchId
        ]);

        header("Location: live_score_board.php?match_id={$formMatchId}&saved=1");
        exit;
    }

    if ($action === 'match_control' && $formMatchId > 0) {

        $status = (string)($_POST['status'] ?? 'setup');

        $allowed = [
            'setup',
            'live',
            'paused',
            'innings_break',
            'completed'
        ];

        if (in_array($status, $allowed, true)) {

            $st = $pdo->prepare("
                UPDATE matches
                SET status = :s
                WHERE id = :id
            ");

            $st->execute([
                ':s' => $status,
                ':id' => $formMatchId
            ]);
        }

        header("Location: live_score_board.php?match_id={$formMatchId}&saved=1");
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| TEAM + PLAYER LOAD
|--------------------------------------------------------------------------
*/

$gullyJson = $currentMatch['gully_state'] ?? '';

$matchTeamIds = [];

if ($currentMatch) {

    if (!empty($currentMatch['team_a_id'])) {
        $matchTeamIds[] = (int)$currentMatch['team_a_id'];
    }

    if (!empty($currentMatch['team_b_id'])) {
        $matchTeamIds[] = (int)$currentMatch['team_b_id'];
    }

    $matchTeamIds = array_unique($matchTeamIds);
}


if ($matchTeamIds) {

    $placeholders = implode(',', array_fill(0, count($matchTeamIds), '?'));

    $boardTeams = $pdo->prepare("
        SELECT id, team_name
        FROM teams
        WHERE id IN ($placeholders)
        ORDER BY team_name
    ");

    $boardTeams->execute($matchTeamIds);

    $boardTeams = $boardTeams->fetchAll();


    $boardPlayers = $pdo->prepare("
        SELECT
            p.id,
            p.full_name,
            tp.team_id
        FROM players p
        JOIN team_players tp
            ON tp.player_id = p.id
        WHERE tp.team_id IN ($placeholders)
        ORDER BY p.full_name
    ");

    $boardPlayers->execute($matchTeamIds);

    $boardPlayers = $boardPlayers->fetchAll();

} else {

    $boardTeams = [];

    $boardPlayers = [];
}


require_once __DIR__ . '/header.php';
?>

<div class="admin-shell">

<?php

if ($isOrganizer) {
    require_once __DIR__ . '/organizer_sidebar.php';
} else {
    require_once __DIR__ . '/admin_sidebar.php';
}

?>

<div class="flex-grow-1">

<div class="cp-card p-4">

<h2 class="fw-bold mb-3">
Live Score Board
</h2>

<form method="get" class="mb-4">

<select name="match_id" class="form-select mb-3">

<?php foreach ($matchList as $m): ?>

<option
value="<?= (int)$m['id'] ?>"
<?= $matchId == $m['id'] ? 'selected' : '' ?>>

<?= h($m['match_name']) ?>
-
<?= h($m['match_date']) ?>

</option>

<?php endforeach; ?>

</select>

<button class="btn btn-success">
Load Match
</button>

</form>

<?php if ($currentMatch): ?>

<div class="alert alert-info">

<strong>
<?= h($currentMatch['team_a_name']) ?>
</strong>

VS

<strong>
<?= h($currentMatch['team_b_name']) ?>
</strong>

</div>

<?php endif; ?>


<div id="gully-root"
data-initial='<?= h($gullyJson) ?>'>

<div class="text-center">

<h1 class="gully-big">
0/0 (0.0 Ov)
</h1>

<div class="gully-crr mb-3">
CRR 0.00
</div>

<div class="d-flex flex-wrap gap-2 justify-content-center">

<button class="btn btn-success" data-run="0">0</button>
<button class="btn btn-success" data-run="1">1</button>
<button class="btn btn-success" data-run="2">2</button>
<button class="btn btn-success" data-run="3">3</button>
<button class="btn btn-success" data-run="4">4</button>
<button class="btn btn-success" data-run="6">6</button>

<button class="btn btn-warning" data-extra="wd">Wide</button>
<button class="btn btn-warning" data-extra="nb">No Ball</button>

<button class="btn btn-danger" data-wicket="1">
Wicket
</button>

<button class="btn btn-dark" data-undo="1">
Undo
</button>

</div>

<hr>

<button id="gully-save"
class="btn btn-outline-success">

Save Scoreboard

</button>

</div>

</div>

<form id="gully-save-form"
method="post"
class="d-none">

<input type="hidden"
name="action"
value="save_score">

<input type="hidden"
name="match_id"
value="<?= (int)$matchId ?>">

<input type="hidden"
name="gully_json"
id="gully-json-field">

</form>

</div>

</div>

</div>


<script>

const root = document.getElementById("gully-root");

let state;

try {

    state = root.dataset.initial
        ? JSON.parse(root.dataset.initial)
        : null;

} catch(e) {

    state = null;
}

if(!state){

    state = {
        runs:0,
        wickets:0,
        balls:0
    };
}

let history=[];

function overs(){

    return Math.floor(state.balls/6)
        +"."+(state.balls%6);
}

function rr(){

    if(state.balls===0) return "0.00";

    return (state.runs/(state.balls/6)).toFixed(2);
}

function render(){

    document.querySelector(".gully-big").innerHTML =
        state.runs + "/" + state.wickets +
        " (" + overs() + " Ov)";

    document.querySelector(".gully-crr").innerHTML =
        "CRR " + rr();
}

function saveHist(){

    history.push(JSON.stringify(state));
}

function undo(){

    if(!history.length) return;

    state = JSON.parse(history.pop());

    render();
}

function addRun(x){

    saveHist();

    state.runs += x;
    state.balls++;

    render();
}

function wide(){

    saveHist();

    state.runs++;

    render();
}

function nb(){

    saveHist();

    state.runs++;

    render();
}

function wicket(){

    saveHist();

    state.wickets++;
    state.balls++;

    render();
}


root.addEventListener("click", e => {

    if(e.target.dataset.run){
        addRun(parseInt(e.target.dataset.run));
    }

    if(e.target.dataset.extra === "wd"){
        wide();
    }

    if(e.target.dataset.extra === "nb"){
        nb();
    }

    if(e.target.dataset.wicket){
        wicket();
    }

    if(e.target.dataset.undo){
        undo();
    }
});


document.getElementById("gully-save").onclick = function(){

    document.getElementById("gully-json-field").value =
        JSON.stringify(state);

    document.getElementById("gully-save-form").submit();
};

render();

</script>

<?php require_once __DIR__ . '/footer.php'; ?>