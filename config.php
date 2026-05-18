<?php
declare(strict_types=1);

// ---- App configuration ----
// Update these for your MySQL setup (XAMPP defaults are usually root with empty password).

const APP_NAME = 'Cricket Points';

/**
 * Web path to this app (no trailing slash), e.g. '/CricketPoints'.
 * Leave empty for relative URLs — works when the project folder is renamed.
 */
const APP_WEB_BASE = '';

const DB_HOST = 'sql12.freesqldatabase.com';
const DB_NAME = 'sql12827255';
const DB_USER = 'sql12827255';
const DB_PASS = '1Jnbj9VwnI';

// File uploads
const UPLOAD_DIR_PLAYERS = __DIR__ . '/uploads/players';
const UPLOAD_URL_PLAYERS = 'uploads/players';

// ---- Bootstrap session ----
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
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
    $base = rtrim(APP_WEB_BASE, '/');
    if ($base === '') {
        return $path;
    }
    return $base . '/' . $path;
}

