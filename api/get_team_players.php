<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database/cricket_db.php';

header('Content-Type: application/json');

try {
    $team_id = (int)($_GET['team_id'] ?? 0);

    if ($team_id <= 0) {
        throw new Exception('Invalid team ID');
    }

    $players = get_team_players($team_id);

    echo json_encode([
        'success' => true,
        'players' => $players
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
