<?php
require __DIR__ . '/config.php';

function mask(string $s): string {
    if ($s === '') return '(empty)';
    $len = strlen($s);
    if ($len <= 4) return str_repeat('*', $len);
    return substr($s, 0, 2) . str_repeat('*', max(0, $len - 4)) . substr($s, -2);
}

$keys = ['DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASS','DB_CHARSET','DATABASE_URL'];
header('Content-Type: text/plain; charset=utf-8');
echo "Config debug\n============\n";
foreach ($keys as $k) {
    $v = env($k, null);
    if ($v === null) {
        // also check $_ENV/$_SERVER
        if (isset($_ENV[$k])) $v = $_ENV[$k];
        elseif (isset($_SERVER[$k])) $v = $_SERVER[$k];
        else $v = null;
    }

    if ($v === null) {
        echo $k . ": (not set)\n";
        continue;
    }

    if ($k === 'DB_PASS') {
        echo $k . ": " . mask($v) . "\n";
    } else if ($k === 'DATABASE_URL') {
        // don't show full URL; mask password if present
        $p = parse_url($v);
        if ($p === false) {
            echo $k . ": (invalid) " . $v . "\n";
        } else {
            $user = $p['user'] ?? '';
            $pass = $p['pass'] ?? '';
            $host = $p['host'] ?? '';
            $port = $p['port'] ?? '';
            $path = $p['path'] ?? '';
            echo $k . ": " . ($user ? $user . '@' : '') . $host . ($port ? ':' . $port : '') . ($path ? $path : '') . " (password=" . mask($pass) . ")\n";
        }
    } else {
        echo $k . ": " . $v . "\n";
    }
}

echo "\nNote: This file is for debugging. Remove it after use.\n";
