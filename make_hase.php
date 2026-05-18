<?php
declare(strict_types=1);
// Dev-only: run from command line — `php make_hase.php`
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
echo password_hash('Sneh@0520', PASSWORD_DEFAULT), "\n";
