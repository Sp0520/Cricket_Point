<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function ensure_upload_dirs(): void
{
    if (!is_dir(UPLOAD_DIR_PLAYERS)) {
        mkdir(UPLOAD_DIR_PLAYERS, 0777, true);
    }
}

function save_player_photo(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    $orig = (string)($file['name'] ?? '');
    $size = (int)($file['size'] ?? 0);

    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return null;
    }

    if ($size <= 0 || $size > 5000000) {
        return null;
    }

    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return null;
    }

    ensure_upload_dirs();

    $name = bin2hex(random_bytes(12)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);

    $dest = UPLOAD_DIR_PLAYERS . '/' . $name;

    if (!move_uploaded_file($tmp, $dest)) {
        return null;
    }

    return UPLOAD_URL_PLAYERS . '/' . $name;
}