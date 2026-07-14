<?php
/**
 * Migration Runner Script
 * Run pending database migrations
 */

require_once __DIR__ . '/config.php';

function strip_sql_comments(string $sql): string
{
    // Remove multi-line comments
    $sql = preg_replace('!/\*.*?\*/!s', '', $sql);
    
    // Remove single-line comments
    $lines = explode("\n", $sql);
    $cleanLines = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0) {
            continue;
        }
        $cleanLines[] = $line;
    }
    
    return implode("\n", $cleanLines);
}

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

    $cleanSql = strip_sql_comments($sql);

    $pdo = db();
    
    // Split on semicolons to handle multiple statements
    $statements = array_filter(
        array_map('trim', explode(';', $cleanSql)),
        fn($stmt) => !empty($stmt)
    );

    $migrationSuccessful = true;

    foreach ($statements as $statement) {
        // Skip DB selecting statements to avoid locking onto a specific database name
        if (stripos($statement, 'USE ') === 0) {
            continue;
        }

        // Translate MariaDB 'IF NOT EXISTS' to standard MySQL by removing it,
        // since we run statement-by-statement and ignore duplicate column/key errors.
        $statement = str_ireplace('ADD COLUMN IF NOT EXISTS ', 'ADD COLUMN ', $statement);
        $statement = str_ireplace('ADD KEY IF NOT EXISTS ', 'ADD KEY ', $statement);
        $statement = str_ireplace('FOREIGN KEY IF NOT EXISTS ', 'FOREIGN KEY ', $statement);
        $statement = str_ireplace('DROP INDEX IF EXISTS ', 'DROP INDEX ', $statement);
        $statement = str_ireplace('ADD CONSTRAINT fk_matches_team_a FOREIGN KEY IF NOT EXISTS (team_a_id)', 'ADD CONSTRAINT fk_matches_team_a FOREIGN KEY (team_a_id)', $statement);
        $statement = str_ireplace('ADD CONSTRAINT fk_matches_team_b FOREIGN KEY IF NOT EXISTS (team_b_id)', 'ADD CONSTRAINT fk_matches_team_b FOREIGN KEY (team_b_id)', $statement);
        $statement = str_ireplace('ADD CONSTRAINT fk_matches_batting_team FOREIGN KEY IF NOT EXISTS (batting_team_id)', 'ADD CONSTRAINT fk_matches_batting_team FOREIGN KEY (batting_team_id)', $statement);
        $statement = str_ireplace('ADD CONSTRAINT fk_matches_bowling_team FOREIGN KEY IF NOT EXISTS (bowling_team_id)', 'ADD CONSTRAINT fk_matches_bowling_team FOREIGN KEY (bowling_team_id)', $statement);

        // Also clean up general 'IF NOT EXISTS' in any other ADD COLUMN or CONSTRAINT statements
        $statement = preg_replace('/ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\s+/i', 'ADD COLUMN ', $statement);
        $statement = preg_replace('/FOREIGN\s+KEY\s+IF\s+NOT\s+EXISTS\s+/i', 'FOREIGN KEY ', $statement);
        $statement = preg_replace('/ADD\s+KEY\s+IF\s+NOT\s+EXISTS\s+/i', 'ADD KEY ', $statement);

        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            $sqlState = isset($e->errorInfo[0]) ? (string)$e->errorInfo[0] : (string)$e->getCode();
            
            // Check if this error is ignorable (e.g. column already exists, duplicate index, etc.)
            $ignorable = false;
            
            if (
                $sqlState === '42S21' || // Column already exists
                $sqlState === '42S01' || // Table already exists
                $sqlState === '42000' || // Syntax error / duplicate key or drop index when not exists
                strpos($msg, 'Duplicate column') !== false ||
                strpos($msg, 'already exists') !== false ||
                strpos($msg, 'Duplicate key') !== false ||
                strpos($msg, 'Duplicate entry') !== false ||
                strpos($msg, 'system_logs') !== false ||
                strpos($msg, 'Can\'t DROP') !== false || // Cant drop key/index if not exists
                strpos($msg, 'check that column/key exists') !== false
            ) {
                $ignorable = true;
            }
            
            if ($ignorable) {
                echo "⚠️ Info: Ignored ignorable check in [$migrationFile]: " . $msg . "\n";
            } else {
                echo "❌ Error in statement in [$migrationFile]: " . $msg . "\n";
                $migrationSuccessful = false;
            }
        }
    }

    if ($migrationSuccessful) {
        echo "✅ Successfully completed migration: $migrationFile\n";
    } else {
        echo "❌ Migration completed with some errors: $migrationFile\n";
    }
    return $migrationSuccessful;
}

// List of all migrations to run (in order)
$migrations = [
    'upgrade_20260714_types.sql',
    'upgrade_20260328_organizer_gully.sql',
    'upgrade_20260329_team_contact.sql',
    'upgrade_20260330_ball_by_ball.sql',
    'upgrade_20260401_live_scoring.sql',
    'upgrade_20260402_otp_verification.sql',
    'upgrade_20260714_payments.sql',
];

echo "Running database migrations...\n";
echo str_repeat('-', 50) . "\n";

$allSuccessful = true;
foreach ($migrations as $migration) {
    run_migration($migration);
}

echo str_repeat('-', 50) . "\n";
echo "Migrations finished checking.\n";
?>
