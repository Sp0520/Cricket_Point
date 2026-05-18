<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/*
|--------------------------------------------------------------------------
| Get Current Logged User
|--------------------------------------------------------------------------
*/

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/*
|--------------------------------------------------------------------------
| Check Login Status
|--------------------------------------------------------------------------
*/

function is_logged_in(): bool
{
    return current_user() !== null;
}

/*
|--------------------------------------------------------------------------
| Require Login
|--------------------------------------------------------------------------
*/

function require_login(): void
{
    if (!is_logged_in()) {

        // redirect dynamically to login page
        header('Location: login.php');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Require Admin Access
|--------------------------------------------------------------------------
*/

function require_admin(): void
{
    require_login();

    $u = current_user();

    if (($u['role'] ?? '') !== 'admin') {

        http_response_code(403);
        echo "Access Denied: Admin Only";
        exit;
    }
}

function require_organizer(): void
{
    require_login();
    if ((current_user()['role'] ?? '') !== 'organizer') {
        http_response_code(403);
        echo 'Access Denied: Organizer Only';
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Login User Session Setup
|--------------------------------------------------------------------------
*/

function login_user(array $userRow): void
{
    $_SESSION['user'] = [

        'id' => (int)$userRow['id'],
        'name' => (string)$userRow['name'],
        'email' => (string)($userRow['email'] ?? ''),
        'phone' => isset($userRow['phone']) ? (string)$userRow['phone'] : null,
        'role' => (string)$userRow['role'],
        'player_id' => $userRow['player_id'] !== null
            ? (int)$userRow['player_id']
            : null,
        'verified' => (bool)($userRow['verified'] ?? false),
    ];
}

function is_verified(): bool
{
    $u = current_user();
    return $u !== null && !empty($u['verified']);
}

function require_verified(): void
{
    require_login();

    if (!is_verified()) {
        header('Location: otp_verify.php?message=verify_first');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Logout User
|--------------------------------------------------------------------------
*/

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool)$params['secure'],
            (bool)$params['httponly']
        );
    }

    session_destroy();
}