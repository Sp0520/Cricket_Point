<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Ensure OTP table exists in the database. In some deployments the schema
// may not have been imported; create the table if missing so OTP features
// degrade more gracefully instead of raising a fatal PDO exception.
function ensure_otp_table_exists(): void
{
    $pdo = db();
    $sql = <<<'SQL'
    CREATE TABLE IF NOT EXISTS otp_verifications (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id INT UNSIGNED NULL,
      email VARCHAR(190) NULL,
      phone VARCHAR(32) NULL,
      purpose ENUM('registration','login') NOT NULL DEFAULT 'registration',
      otp_hash VARCHAR(255) NOT NULL,
      attempts INT UNSIGNED NOT NULL DEFAULT 0,
      max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      expires_at DATETIME NOT NULL,
      last_sent_at DATETIME NOT NULL,
      verified_at DATETIME DEFAULT NULL,
      PRIMARY KEY (id),
      KEY idx_otp_user (user_id),
      KEY idx_otp_email (email),
      KEY idx_otp_phone (phone)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    SQL;

    try {
        $pdo->exec($sql);
    } catch (Throwable $e) {
        // If creation fails (permissions, engine unsupported, etc.) we silently
        // ignore so the calling code can fall back to session-based OTP in dev.
    }
}

// Ensure the table exists immediately when this file is loaded.
ensure_otp_table_exists();

function generate_otp_code(): string
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function send_email_otp(string $to, string $otp, string $name = null): bool
{
    $subject = 'Your CricketPoints OTP code';
    $displayName = $name ? $name : 'Player';
    $message = "Hello $displayName,\n\nYour OTP code for CricketPoints is: $otp\n\n" .
               "It expires in 5 minutes. If you did not request this, please ignore.\n\n" .
               "Regards,\nCricketPoints Team";
    $headers = "From: noreply@cricketpoints.local\r\n" .
               "Reply-To: noreply@cricketpoints.local\r\n" .
               "Content-Type: text/plain; charset=UTF-8\r\n";

    if (function_exists('mail')) {
        return mail($to, $subject, $message, $headers);
    }

    // SMTP not configured; for development we accept false and return false.
    return false;
}

function send_sms_otp(string $phone, string $otp): bool
{
    // Example placeholder for Twilio or Fast2SMS API.
    // Replace with your own credentials and endpoint.
    
    // Twilio example:
    // $accountSid = 'your_account_sid';
    // $authToken = 'your_auth_token';
    // $fromNumber = 'your_twilio_number';
    // $message = "Your CricketPoints OTP is $otp (valid 5 minutes).";
    // $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $accountSid . '/Messages.json';
    // $postData = [
    //     'From' => $fromNumber,
    //     'To' => $phone,
    //     'Body' => $message,
    // ];
    // $ch = curl_init($url);
    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // curl_setopt($ch, CURLOPT_USERPWD, "$accountSid:$authToken");
    // curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    // $response = curl_exec($ch);
    // $info = curl_getinfo($ch);
    // curl_close($ch);
    // return $info['http_code'] >= 200 && $info['http_code'] < 300;

    // Fast2SMS example (commented):
    // $apiKey = 'YOUR_FAST2SMS_API_KEY';
    // $url = 'https://www.fast2sms.com/dev/bulkV2';
    // $postData = [
    //     'sender_id' => 'TXTIND',
    //     'message' => "Your CricketPoints OTP is $otp (valid 5 minutes).",
    //     'language' => 'english',
    //     'route' => 'v3',
    //     'numbers' => preg_replace('/\D+/', '', $phone),
    // ];
    // $headers = [
    //     'authorization: ' . $apiKey,
    //     'Content-Type: application/x-www-form-urlencoded'
    // ];
    // $ch = curl_init($url);
    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // curl_setopt($ch, CURLOPT_POST, true);
    // curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    // curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    // $response = curl_exec($ch);
    // $info = curl_getinfo($ch);
    // curl_close($ch);
    // return $info['http_code'] >= 200 && $info['http_code'] < 300;

    return false;
}

function create_otp_token(int $userId, ?string $email, ?string $phone, string $purpose = 'registration'): string
{
    $pdo = db();
    $value = generate_otp_code();
    $hash = password_hash($value, PASSWORD_DEFAULT);
    $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $expires = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+5 minutes')->format('Y-m-d H:i:s');

    // Invalidate previous OTP records for this user and purpose.
    $stmt = $pdo->prepare('DELETE FROM otp_verifications WHERE user_id = :uid AND purpose = :purpose');
    $stmt->execute([':uid' => $userId, ':purpose' => $purpose]);

    $stmt = $pdo->prepare('INSERT INTO otp_verifications (user_id, email, phone, purpose, otp_hash, expires_at, last_sent_at) VALUES (:uid, :email, :phone, :purpose, :hash, :expires, :last_sent)');
    $stmt->execute([
        ':uid' => $userId,
        ':email' => $email,
        ':phone' => $phone,
        ':purpose' => $purpose,
        ':hash' => $hash,
        ':expires' => $expires,
        ':last_sent' => $now,
    ]);

    return $value;
}

function get_otp_record(int $userId, string $purpose = 'registration'): ?array
{
    $stmt = db()->prepare('SELECT * FROM otp_verifications WHERE user_id = :uid AND purpose = :purpose ORDER BY id DESC LIMIT 1');
    $stmt->execute([':uid' => $userId, ':purpose' => $purpose]);
    $record = $stmt->fetch();
    return $record ?: null;
}

function can_resend_otp(int $userId, string $purpose = 'registration'): bool
{
    $record = get_otp_record($userId, $purpose);
    if (!$record) {
        return true;
    }
    $last = new DateTimeImmutable($record['last_sent_at'], new DateTimeZone('UTC'));
    $diff = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->getTimestamp() - $last->getTimestamp();
    return $diff >= 30;
}

function send_user_otp(array $userRow, string $purpose = 'registration'): bool
{
    $userId = (int)$userRow['id'];
    $email = $userRow['email'] ?? null;
    $phone = $userRow['phone'] ?? null;

    if (!$email && !$phone) {
        return false;
    }

    if (!can_resend_otp($userId, $purpose)) {
        return false;
    }

    $otp = create_otp_token($userId, $email, $phone, $purpose);

    $sent = false;
    if ($phone) {
        $sent = send_sms_otp($phone, $otp);
    }
    if (!$sent && $email) {
        $sent = send_email_otp($email, $otp, $userRow['name'] ?? null);
    }

    // Developer mode fallback (local testing when SMS/email is not configured)
    $isLocal = true;
    if (!$sent && $isLocal) {
        $_SESSION['otp_debug'] = ['user_id' => $userId, 'purpose' => $purpose, 'otp' => $otp];
        return true;
    }

    if ($sent) {
        // Clear debug code to avoid stale information
        unset($_SESSION['otp_debug']);
    }

    return $sent;
}

function verify_otp(string $userInput, int $userId, string $purpose = 'registration'): array
{
    $record = get_otp_record($userId, $purpose);
    if (!$record) {
        return ['ok' => false, 'message' => 'No OTP request found.'];
    }

    $expires = new DateTimeImmutable($record['expires_at'], new DateTimeZone('UTC'));
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    if ($now > $expires) {
        // expired: clear and return a message
        db()->prepare('DELETE FROM otp_verifications WHERE id = :id')->execute([':id' => $record['id']]);
        return ['ok' => false, 'message' => 'OTP expired. Please request a new code.'];
    }

    if ((int)$record['attempts'] >= (int)$record['max_attempts']) {
        // too many attempts
        db()->prepare('DELETE FROM otp_verifications WHERE id = :id')->execute([':id' => $record['id']]);
        return ['ok' => false, 'message' => 'Maximum verification attempts exceeded. Please resend OTP.'];
    }

    $success = password_verify($userInput, $record['otp_hash']);
    $stmt = db()->prepare('UPDATE otp_verifications SET attempts = attempts + 1 WHERE id = :id');
    $stmt->execute([':id' => $record['id']]);

    if (!$success) {
        return ['ok' => false, 'message' => 'Invalid OTP. Please try again.'];
    }

    db()->prepare('UPDATE otp_verifications SET verified_at = NOW() WHERE id = :id')->execute([':id' => $record['id']]);

    // Optionally clear OTP after verification
    db()->prepare('DELETE FROM otp_verifications WHERE id = :id')->execute([':id' => $record['id']]);

    return ['ok' => true, 'message' => 'OTP verified successfully.'];
}

function clear_otp_tokens(int $userId, string $purpose = 'registration'): void
{
    db()->prepare('DELETE FROM otp_verifications WHERE user_id = :uid AND purpose = :purpose')->execute([':uid' => $userId, ':purpose' => $purpose]);
}

function get_remaining_otp_time(int $userId, string $purpose = 'registration'): int
{
    $record = get_otp_record($userId, $purpose);
    if (!$record) {
        return 0;
    }
    $expires = new DateTimeImmutable($record['expires_at'], new DateTimeZone('UTC'));
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $remaining = $expires->getTimestamp() - $now->getTimestamp();
    return max(0, $remaining);
}
