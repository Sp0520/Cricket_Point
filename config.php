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
    if ($value !== false && $value !== '') {
        return $value;
    }

    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }

    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }

    return $default;
}

function env_any(array $keys, ?string $default = null): ?string
{
    foreach ($keys as $key) {
        $value = env($key);
        if ($value !== null && $value !== '') {
            return $value;
        }
    }

    return $default;
}

function parse_database_url(string $databaseUrl): array
{
    $parts = parse_url($databaseUrl);
    if ($parts === false || !isset($parts['scheme'], $parts['host'], $parts['user'], $parts['pass'], $parts['path'])) {
        return [];
    }

    return [
        'host' => $parts['host'],
        'name' => ltrim($parts['path'], '/'),
        'user' => $parts['user'],
        'pass' => $parts['pass'],
        'port' => isset($parts['port']) ? (string)$parts['port'] : '',
        'charset' => env('DB_CHARSET', DEFAULT_DB_CHARSET),
    ];
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbUrl = env_any([
        'DATABASE_URL',
        'MYSQL_URL',
        'CLEARDB_DATABASE_URL',
        'DB_URL',
        'RENDER_DATABASE_URL',
    ], '');

    if ($dbUrl !== '') {
        $parsed = parse_database_url($dbUrl);
        if ($parsed !== []) {
            $host = $parsed['host'];
            $name = $parsed['name'];
            $user = $parsed['user'];
            $pass = $parsed['pass'];
            $charset = $parsed['charset'];
            $port = $parsed['port'];
        }
    }

    if (!isset($host)) {
        $host = env_any(['DB_HOST'], DEFAULT_DB_HOST);
        $name = env_any(['DB_NAME'], DEFAULT_DB_NAME);
        $user = env_any(['DB_USER'], DEFAULT_DB_USER);
        $pass = env_any(['DB_PASS'], DEFAULT_DB_PASS);
        $charset = env_any(['DB_CHARSET'], DEFAULT_DB_CHARSET);
        $port = env_any(['DB_PORT'], '');
    }

    if ($host === '' || $name === '' || $user === '' || $pass === '') {
        throw new RuntimeException(
            'Database configuration is missing. Please set DB_HOST, DB_NAME, DB_USER, and DB_PASS or provide DATABASE_URL in Render environment variables.'
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