<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function is_admin_user(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function is_organizer_user(): bool
{
    return (current_user()['role'] ?? '') === 'organizer';
}

function require_admin_or_organizer(): void
{
    require_login();
    if (!is_admin_user() && !is_organizer_user()) {
        http_response_code(403);
        echo 'Access Denied';
        exit;
    }
}

/** @return int|null Organizer user id for ownership columns; null for platform admin. */
function organizer_owner_user_id(): ?int
{
    if (!is_organizer_user()) {
        return null;
    }
    $id = (int)(current_user()['id'] ?? 0);
    return $id > 0 ? $id : null;
}

function can_access_tournament_row(array $t): bool
{
    if (is_admin_user()) {
        return true;
    }
    $oid = organizer_owner_user_id();
    if ($oid === null) {
        return false;
    }
    return isset($t['owner_user_id']) && (int)$t['owner_user_id'] === $oid;
}

function can_access_match_row(array $m): bool
{
    if (is_admin_user()) {
        return true;
    }
    $oid = organizer_owner_user_id();
    if ($oid === null) {
        return false;
    }
    return isset($m['owner_user_id']) && (int)$m['owner_user_id'] === $oid;
}

function is_organizer_paid(): bool
{
    if (!is_organizer_user()) {
        return true;
    }
    $u = current_user();
    if ($u === null) {
        return false;
    }
    
    // Query DB to check fresh status
    try {
        $st = db()->prepare('SELECT is_paid_member FROM users WHERE id = :id LIMIT 1');
        $st->execute([':id' => (int)$u['id']]);
        $row = $st->fetch();
        if ($row) {
            $isPaid = (bool)$row['is_paid_member'];
            $_SESSION['user']['is_paid_member'] = $isPaid;
            return $isPaid;
        }
    } catch (Throwable $e) {
    }
    return !empty($u['is_paid_member']);
}

function require_organizer_paid(): void
{
    require_login();
    $u = current_user();
    if ($u === null) {
        header('Location: login.php');
        exit;
    }
    if (($u['role'] ?? '') === 'organizer' && !is_organizer_paid()) {
        header('Location: organizer_payment.php');
        exit;
    }
}
