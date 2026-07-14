<?php
/**
 * Migration Runner Script
 * Run pending database migrations
 */

require_once __DIR__ . '/config.php';

function run_migration(string $migrationFile): bool
{
    $filePath = __DIR__ . '/migrations/' . $migrationFile;
    
    if (!file_exists($filePath)) {
        echo "❌ Migration file not found: $migrationFile\n";
        return false;
    }

    $sql = file_get_contents($filePath);
    if (!$sql) {
        echo "❌ Failed to read migration file: $migrationFile\n";
        return false;
    }

    try {
        $pdo = db();
        
        // Split on semicolons to handle multiple statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($stmt) => !empty($stmt)
        );

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }

        echo "✅ Successfully executed migration: $migrationFile\n";
        return true;
    } catch (PDOException $e) {
        echo "❌ Migration failed: " . $e->getMessage() . "\n";
        return false;
    }
}

// List of pending migrations to run (in order)
$migrations = [
    'upgrade_20260402_otp_verification.sql',
    'upgrade_20260714_payments.sql',
];

echo "Running database migrations...\n";
echo str_repeat('-', 50) . "\n";

$allSuccessful = true;
foreach ($migrations as $migration) {
    if (!run_migration($migration)) {
        $allSuccessful = false;
    }
}

echo str_repeat('-', 50) . "\n";
if ($allSuccessful) {
    echo "✅ All migrations completed successfully!\n";
} else {
    echo "❌ Some migrations failed. Please check the errors above.\n";
}
?>
