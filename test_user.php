<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

require_admin();

$st = db()->query('SELECT email, role FROM users');
print_r($st->fetchAll());
