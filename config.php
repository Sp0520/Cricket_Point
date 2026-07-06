<?php
declare(strict_types=1);

const APP_NAME = 'Cricket Points';
const DEFAULT_APP_WEB_BASE = '';

const DEFAULT_DB_HOST = '';
const DEFAULT_DB_NAME = '';
const DEFAULT_DB_USER = '';
const DEFAULT_DB_PASS = '';
const DEFAULT_DB_CHARSET = 'utf8mb4';

// File uploads
const UPLOAD_DIR_PLAYERS = __DIR__ . '/uploads/players';
const UPLOAD_URL_PLAYERS = 'uploads/players';

// ---- Bootstrap session ----
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env('DB_HOST', DEFAULT_DB_HOST);
    $name = env('DB_NAME', DEFAULT_DB_NAME);
    $user = env('DB_USER', DEFAULT_DB_USER);
    $pass = env('DB_PASS', DEFAULT_DB_PASS);
    $charset = env('DB_CHARSET', DEFAULT_DB_CHARSET);
    $port = env('DB_PORT');

    if ($host === '' || $name === '' || $user === '' || $pass === '') {
        throw new RuntimeException(
            'Database configuration is missing. Please set DB_HOST, DB_NAME, DB_USER, and DB_PASS in your Render environment variables.'
        );
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $name, $charset);
    if ($port !== null && $port !== '') {
        $dsn .= ';port=' . $port;
    }

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function h(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function url_for(string $path): string
{
    $path = ltrim(str_replace('\\', '/', $path), '/');
    $base = rtrim(env('APP_WEB_BASE', DEFAULT_APP_WEB_BASE), '/');

    if ($base === '') {
        return $path;
    }

    return $base . '/' . $path;
}