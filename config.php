<?php
declare(strict_types=1);

const APP_NAME = 'Cricket Points';
const APP_WEB_BASE = '';

const DB_HOST = 'sql12.freesqldatabase.com';
const DB_NAME = 'sql12827255';
const DB_USER = 'sql12827255';
const DB_PASS = '1Jnbj9VwnI';
const DB_CHARSET = 'utf8mb4';

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

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

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