<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/access.php';

require_admin_or_organizer();

$pdo = db();

$matchList = $pdo->query("
SELECT m.id, m.match_name, m.match_date, m.match_time, m.tournament_id, m.team_a_id, m.team_b_id, m.overs_limit, m.wickets_limit, m.status, m.gully_state,
       ta.team_name AS team_a_name, tb.team_name AS team_b_name
FROM matches m
LEFT JOIN teams ta ON ta.id = m.team_a_id
LEFT JOIN teams tb ON tb.id = m.team_b_id
ORDER BY m.match_date DESC
")->fetchAll();

$matchId = (int)($_GET['match_id'] ?? 0);

$currentMatch = null;

foreach ($matchList as $row) {
if ($row['id'] == $matchId)
$currentMatch = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'save_score');
    $formMatchId = (int)($_POST['match_id'] ?? 0);

    if ($action === 'match_control' && $formMatchId > 0) {
        $status = (string)($_POST['status'] ?? 'setup');
        $allowed = ['setup', 'live', 'paused', 'innings_break', 'completed'];
        if (in_array($status, $allowed, true)) {
            if ($status === 'innings_break') {
                $pdo->prepare("UPDATE matches SET status = 'innings_break' WHERE id = ?")->execute([$formMatchId]);
            } elseif ($status === 'completed') {
                $pdo->prepare("UPDATE matches SET status = 'completed' WHERE id = ?")->execute([$formMatchId]);
            } else {
                $pdo->prepare("UPDATE matches SET status = ? WHERE id = ?")->execute([$status, $formMatchId]);
            }
        }
        header("Location: live_score_board.php?match_id=$formMatchId&saved=1");
        exit;
    }

    if ($action === 'save_score' && $formMatchId > 0) {
        $pdo->prepare("UPDATE matches SET gully_state = ? WHERE id = ?")->execute([
            (string)($_POST['gully_json'] ?? ''),
            $formMatchId
        ]);
        header("Location: live_score_board.php?match_id=$formMatchId&saved=1");
        exit;
    }
}

$gullyJson = $currentMatch['gully_state'] ?? '';

$matchTeamIds = [];
if ($currentMatch) {
    if (!empty($currentMatch['team_a_id'])) $matchTeamIds[] = (int)$currentMatch['team_a_id'];
    if (!empty($currentMatch['team_b_id'])) $matchTeamIds[] = (int)$currentMatch['team_b_id'];
    $matchTeamIds = array_unique($matchTeamIds);
}

if ($matchTeamIds) {
    $placeholders = implode(',', array_fill(0, count($matchTeamIds), '?'));
    $boardTeams = $pdo->prepare("SELECT id, team_name FROM teams WHERE id IN ($placeholders) ORDER BY team_name");
    $boardTeams->execute($matchTeamIds);

    $boardPlayers = $pdo->prepare("SELECT p.id, p.full_name, tp.team_id FROM players p JOIN team_players tp ON tp.player_id = p.id WHERE tp.team_id IN ($placeholders) ORDER BY p.full_name");
    $boardPlayers->execute($matchTeamIds);
    $boardPlayers = $boardPlayers->fetchAll();
} else {
    $boardPlayers = $pdo->query("
SELECT p.id, p.full_name, tp.team_id
FROM players p
LEFT JOIN team_players tp ON tp.player_id = p.id
ORDER BY p.full_name
")->fetchAll();

    $boardTeams = $pdo->query("
SELECT id, team_name
FROM teams
ORDER BY team_name
")->fetchAll();
}

// If teams are available from match but no selected match use all as fallback
if (empty($boardTeams)) {
    $boardTeams = $pdo->query("SELECT id, team_name FROM teams ORDER BY team_name")->fetchAll();
}


require_once __DIR__ . '/header.php';
?>

<div class="admin-shell">

<?php require_once __DIR__ . '/admin_sidebar.php'; ?>

<div class="flex-grow-1">

<div class="d-flex justify-content-between align-items-end mb-3">

<div>

<h2 class="fw-bold mb-1">Live score board</h2>

<div class="cp-muted">
Select teams → players → then tap runs
</div>

</div>

</div>


<div class="cp-card p-3 mb-3">

<form method="get" class="row g-2 align-items-end">

<div class="col-md-8">

<label class="form-label small cp-muted">
Match
</label>

<select class="form-select"
name="match_id"
onchange="this.form.submit()">

<?php foreach ($matchList as $m): ?>

<option value="<?= $m['id'] ?>"
<?= $matchId==$m['id']?'selected':''?>>

<?= $m['match_name']?> —
<?= $m['match_date']?>

</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-4 d-grid">

<button class="btn btn-outline-success">
Load
</button>

</div>

</form>

</div>


<!-- TEAM PANEL -->

<div class="cp-card p-3 mb-3">

<h5>Select Teams & Players</h5>

<div class="row g-2">

<div class="col-md-6">

<label>Batting Team</label>

<select class="form-select" id="battingTeam">

<?php foreach($boardTeams as $t): ?>

<option value="<?= $t['id']?>">

<?= $t['team_name']?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="col-md-6">

<label>Bowling Team</label>

<select class="form-select" id="bowlingTeam">

<?php foreach($boardTeams as $t): ?>

<option value="<?= $t['id']?>">

<?= $t['team_name']?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="col-md-4">

<label>Striker</label>

<select class="form-select" id="strikerPlayer">

<?php foreach($boardPlayers as $p): ?>

<option value="<?= $p['id']?>"
data-team="<?= $p['team_id']?>">

<?= $p['full_name']?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="col-md-4">

<label>Non-Striker</label>

<select class="form-select" id="nonStrikerPlayer">

<?php foreach($boardPlayers as $p): ?>

<option value="<?= $p['id']?>"
data-team="<?= $p['team_id']?>">

<?= $p['full_name']?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="col-md-4">

<label>Bowler</label>

<select class="form-select" id="bowlerPlayer">

<?php foreach($boardPlayers as $p): ?>

<option value="<?= $p['id']?>"
data-team="<?= $p['team_id']?>">

<?= $p['full_name']?>

</option>

<?php endforeach; ?>

</select>

</div>

</div>

</div>



<div class="cp-card p-3 mb-3">
  <h5>Match Control</h5>
  <div class="mb-2">Current status: <strong><?= h($currentMatch['status'] ?? 'scheduled') ?></strong></div>
  <div class="row g-2">
    <div class="col-auto">
      <form method="post" class="d-inline">
        <input type="hidden" name="action" value="match_control">
        <input type="hidden" name="match_id" value="<?= (int)$matchId ?>">
        <input type="hidden" name="status" value="live">
        <button class="btn btn-success btn-sm" type="submit">Start Match</button>
      </form>
    </div>
    <div class="col-auto">
      <form method="post" class="d-inline">
        <input type="hidden" name="action" value="match_control">
        <input type="hidden" name="match_id" value="<?= (int)$matchId ?>">
        <input type="hidden" name="status" value="paused">
        <button class="btn btn-warning btn-sm" type="submit">Pause</button>
      </form>
    </div>
    <div class="col-auto">
      <form method="post" class="d-inline">
        <input type="hidden" name="action" value="match_control">
        <input type="hidden" name="match_id" value="<?= (int)$matchId ?>">
        <input type="hidden" name="status" value="live">
        <button class="btn btn-info btn-sm" type="submit">Resume</button>
      </form>
    </div>
    <div class="col-auto">
      <form method="post" class="d-inline">
        <input type="hidden" name="action" value="match_control">
        <input type="hidden" name="match_id" value="<?= (int)$matchId ?>">
        <input type="hidden" name="status" value="innings_break">
        <button class="btn btn-secondary btn-sm" type="submit">End Innings</button>
      </form>
    </div>
    <div class="col-auto">
      <form method="post" class="d-inline">
        <input type="hidden" name="action" value="match_control">
        <input type="hidden" name="match_id" value="<?= (int)$matchId ?>">
        <input type="hidden" name="status" value="completed">
        <button class="btn btn-dark btn-sm" type="submit">Finish Match</button>
      </form>
    </div>
  </div>
</div>



<!-- SCOREBOARD PANEL -->

<div id="gully-root"
class="gully-board"
data-initial='<?= $gullyJson ?>'>


<div class="cp-card p-4 text-center">

<h2 class="gully-big">0/0 (0.0 Ov)</h2>

<div class="gully-crr">CRR 0.00</div>
<div class="gully-meta small cp-muted mb-2">
  Limit: <?= (int)($currentMatch['overs_limit'] ?? 20) ?> overs / <?= (int)($currentMatch['wickets_limit'] ?? 10) ?> wickets
</div>

<hr>


<div class="gully-pad-main d-flex flex-wrap gap-2 justify-content-center">

<button class="btn btn-success" data-run="0">0</button>
<button class="btn btn-success" data-run="1">1</button>
<button class="btn btn-success" data-run="2">2</button>
<button class="btn btn-success" data-run="3">3</button>
<button class="btn btn-success" data-run="4">4</button>
<button class="btn btn-success" data-run="6">6</button>

<button class="btn btn-warning" data-extra="wd">Wide</button>
<button class="btn btn-warning" data-extra="nb">No Ball</button>

<button class="btn btn-info" data-extra="bye">Bye</button>
<button class="btn btn-info" data-extra="lb">Leg Bye</button>

<button class="btn btn-danger" data-wicket="1">Wicket</button>

<button class="btn btn-dark" data-undo="1">Undo</button>

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
name="match_id"
value="<?= $matchId ?>">

<input type="hidden"
name="gully_json"
id="gully-json-field">

</form>


</div>

</div>



<script>

const root=document.getElementById("gully-root");

let state;

try{
    state = root.dataset.initial
    ? JSON.parse(root.dataset.initial)
    : null;
}catch(e){
    state = null;
}

const oversLimit = <?= (int)($currentMatch['overs_limit'] ?? 20) ?>;
const wicketsLimit = <?= (int)($currentMatch['wickets_limit'] ?? 10) ?>;

if(!state){
    state = {runs:0,wickets:0,balls:0,current_innings:1};
}

state.overs_limit = (state.overs_limit ?? oversLimit);
state.wickets_limit = (state.wickets_limit ?? wicketsLimit);
state.current_innings = (state.current_innings ?? 1);

let history=[];

function overs(){
    return Math.floor(state.balls/6)
    +"."+(state.balls%6);
}

function rr(){
    if(state.balls==0) return "0.00";
    return (state.runs/(state.balls/6)).toFixed(2);
}

function inningsFinished(){
    return state.wickets >= state.wickets_limit || Math.floor(state.balls/6) >= state.overs_limit;
}

function maybeEndInnings(){
    if (inningsFinished()) {
        state.innings_finished = true;
    }
}

const matchStatus = '<?= h($currentMatch['status'] ?? 'scheduled') ?>';

function render(){
    document.querySelector(".gully-big").innerHTML=
        state.runs+"/"+state.wickets+
        " ("+overs()+" Ov)";

    document.querySelector(".gully-crr").innerHTML=
        "CRR "+rr();

    const inningFinished = state.wickets >= state.wickets_limit || Math.floor(state.balls/6) >= state.overs_limit;
    const scoringEnabled = matchStatus === 'live' && !inningFinished;

    document.querySelectorAll('.gully-pad-main button').forEach(btn => {
        if (btn.dataset.undo) {
            btn.disabled = false;
        } else {
            btn.disabled = !scoringEnabled;
        }
    });

    let statusMessage = 'Match status: ' + matchStatus;
    if (inningFinished) {
        statusMessage = 'Innings ended (limit reached).';
    }

    let infoEl = document.querySelector('.gully-state-info');
    if (!infoEl) {
        infoEl = document.createElement('div');
        infoEl.className = 'gully-state-info small text-danger mt-2';
        document.querySelector('.cp-card.p-4.text-center').appendChild(infoEl);
    }
    infoEl.innerText = statusMessage;
}

function saveHist(){
history.push(JSON.stringify(state));
}

function undo(){
if(!history.length)return;
state=JSON.parse(history.pop());
render();
}

function addRun(x){

    saveHist();

    state.runs += x;
    state.balls++;

    maybeEndInnings();
    render();

}

function wide(){
    saveHist();
    state.runs++;
    maybeEndInnings();
    render();
}

function nb(){
    saveHist();
    state.runs++;
    maybeEndInnings();
    render();
}

function bye(){
    saveHist();
    state.runs++;
    state.balls++;
    maybeEndInnings();
    render();
}

function lb(){
    saveHist();
    state.runs++;
    state.balls++;
    maybeEndInnings();
    render();
}

function wicket(){
    saveHist();
    state.wickets++;
    state.balls++;
    maybeEndInnings();
    render();
}


root.addEventListener("click",e=>{

if(e.target.dataset.run)
addRun(parseInt(e.target.dataset.run));

if(e.target.dataset.extra==="wd")
wide();

if(e.target.dataset.extra==="nb")
nb();

if(e.target.dataset.extra==="bye")
bye();

if(e.target.dataset.extra==="lb")
lb();

if(e.target.dataset.wicket)
wicket();

if(e.target.dataset.undo)
undo();

});


document.getElementById("gully-save")
.onclick=function(){

document.getElementById("gully-json-field")
.value=JSON.stringify(state);

document.getElementById("gully-save-form")
.submit();

};


render();

</script>
<script>

const battingTeam = document.getElementById("battingTeam");
const bowlingTeam = document.getElementById("bowlingTeam");

const striker = document.getElementById("strikerPlayer");
const nonStriker = document.getElementById("nonStrikerPlayer");
const bowler = document.getElementById("bowlerPlayer");


/*
STORE ALL PLAYERS FIRST
*/
const allPlayers = Array.from(striker.options).map(opt => ({
    value: opt.value,
    name: opt.text,
    team: opt.dataset.team
}));


/*
FUNCTION TO FILL PLAYERS
*/
function fillPlayers(selectBox, teamId) {

    selectBox.innerHTML = "";

    const filtered = allPlayers.filter(player => player.team == teamId);

    filtered.forEach(player => {

        const option = document.createElement("option");

        option.value = player.value;
        option.text = player.name;

        selectBox.appendChild(option);

    });

}


/*
BATSMEN FILTER
*/
battingTeam.addEventListener("change", function () {

    fillPlayers(striker, this.value);
    fillPlayers(nonStriker, this.value);

});


/*
BOWLER FILTER
*/
bowlingTeam.addEventListener("change", function () {

    fillPlayers(bowler, this.value);

});


/*
RUN ON PAGE LOAD
*/
window.addEventListener("load", function () {

    if (battingTeam.value) {

        fillPlayers(striker, battingTeam.value);
        fillPlayers(nonStriker, battingTeam.value);

    }

    if (bowlingTeam.value) {

        fillPlayers(bowler, bowlingTeam.value);

    }

});

</script>

<?php require_once __DIR__ . '/footer.php'; ?>