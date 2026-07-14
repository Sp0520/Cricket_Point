<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/repo.php';
require_once __DIR__ . '/contact_util.php';

require_login();
require_verified();
$u = current_user();
if (($u['role'] ?? '') !== 'player' || empty($u['player_id'])) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}
$playerId = (int)$u['player_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: player_dashboard.php');
    exit;
}

$tournamentId = (int)($_POST['tournament_id'] ?? 0);
$registrantType = (string)($_POST['registrant_type'] ?? '');
if ($tournamentId <= 0 || !in_array($registrantType, ['player', 'team'], true)) {
    header('Location: player_dashboard.php?msg=invalid');
    exit;
}

$t = fetch_tournament($tournamentId);
if (!$t) {
    header('Location: player_dashboard.php?msg=notfound');
    exit;
}

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$from = $t['registration_open_from'] ?? null;
$to = $t['registration_open_to'] ?? null;
$open = $from !== null
    && $from <= $today
    && ($to === null || $to >= $today);

if (!$open) {
    header('Location: player_dashboard.php?msg=closed');
    exit;
}

$pdo = db();
$err = '';

try {
    $pdo->beginTransaction();
    $hasEntryFee = ((float)($t['entry_fees'] ?? 0) > 0);
    $status = $hasEntryFee ? 'pending' : 'approved';

    if ($registrantType === 'player') {
        // If already registered, do nothing.
        $st = $pdo->prepare("
          SELECT id
          FROM tournament_registrations
          WHERE tournament_id = :tid
            AND registrant_type = 'player'
            AND player_id = :pid
          LIMIT 1
        ");
        $st->execute([':tid' => $tournamentId, ':pid' => $playerId]);
        $existing = $st->fetch();
        if ($existing) {
            $pdo->rollBack();
            header('Location: player_dashboard.php?msg=already');
            exit;
        }

        $st = $pdo->prepare("
          INSERT INTO tournament_registrations (tournament_id, registrant_type, player_id, status)
          VALUES (:tid, 'player', :pid, :status)
        ");
        $st->execute([':tid' => $tournamentId, ':pid' => $playerId, ':status' => $status]);
    } else {
        // team registration
        $myTeam = fetch_player_team($playerId);
        $teamId = $myTeam ? (int)$myTeam['id'] : null;
        if (!$teamId) {
            $pdo->rollBack();
            header('Location: player_teams.php?msg=needteam');
            exit;
        }

        $st = $pdo->prepare("
          SELECT id
          FROM tournament_registrations
          WHERE tournament_id = :tid
            AND registrant_type = 'team'
            AND team_id = :teamId
          LIMIT 1
        ");
        $st->execute([':tid' => $tournamentId, ':teamId' => $teamId]);
        $existing = $st->fetch();
        if ($existing) {
            $pdo->rollBack();
            header('Location: player_dashboard.php?msg=already');
            exit;
        }

        $phoneRaw = trim((string)($_POST['contact_phone'] ?? ''));
        $phoneOk = normalized_contact_phone($phoneRaw);
        if ($phoneOk === null) {
            $pdo->rollBack();
            header('Location: team_tournament_register.php?tournament_id=' . $tournamentId . '&msg=badphone');
            exit;
        }

        $st = $pdo->prepare('UPDATE teams SET contact_phone = :ph WHERE id = :id');
        $st->execute([':ph' => $phoneOk, ':id' => $teamId]);

        $st = $pdo->prepare("
          INSERT INTO tournament_registrations (tournament_id, registrant_type, team_id, contact_phone, status)
          VALUES (:tid, 'team', :teamId, :ph, :status)
        ");
        $st->execute([':tid' => $tournamentId, ':teamId' => $teamId, ':ph' => $phoneOk, ':status' => $status]);
    }

    $registrationId = (int)$pdo->lastInsertId();
    $pdo->commit();
    
    if ($hasEntryFee) {
        header('Location: tournament_payment.php?registration_id=' . $registrationId);
        exit;
    } else {
        header('Location: player_dashboard.php?msg=registered');
        exit;
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $err = $e->getMessage();
}

header('Location: player_dashboard.php?msg=error&detail=' . urlencode($err));
exit;

