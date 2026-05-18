<?php
declare(strict_types=1);

/** Returns trimmed contact string or null if too short (fewer than 8 digits). */
function normalized_contact_phone(string $raw): ?string
{
    $t = trim($raw);
    if ($t === '') {
        return null;
    }
    $digits = preg_replace('/\D+/', '', $t);
    if (strlen($digits) < 8) {
        return null;
    }
    return $t;
}
