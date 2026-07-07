<?php
require __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');
try {
    $pdo = db();
    echo "DB connection successful.\n";
    // show a very small query to confirm access
    $stmt = $pdo->query("SELECT 1");
    $row = $stmt->fetch();
    echo "SELECT 1 => " . json_encode($row) . "\n";
} catch (Throwable $e) {
    echo "DB connection failed: " . $e->getMessage() . "\n";
}

echo "\nRemove this file when done.\n";
