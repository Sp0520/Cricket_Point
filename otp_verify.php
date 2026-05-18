<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/otp.php';

$pending = $_SESSION['otp_pending'] ?? null;
if (!is_array($pending) || empty($pending['user_id']) || empty($pending['purpose'])) {
    $message = 'No pending OTP verification request found. Please login or register first.';
    require_once __DIR__ . '/header.php';
    echo '<div class="alert alert-warning">' . h($message) . '</div>';
    echo '<a href="' . h(url_for('login.php')) . '">Go to login</a>';
    require_once __DIR__ . '/footer.php';
    exit;
}

$userId = (int)$pending['user_id'];
$purpose = $pending['purpose'];
$st = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
$st->execute([':id' => $userId]);
$user = $st->fetch();

if (!$user) {
    unset($_SESSION['otp_pending']);
    header('Location: ' . url_for('login.php') . '?msg=user_not_found');
    exit;
}

if ((bool)$user['verified']) {
    unset($_SESSION['otp_pending']);
    login_user($user);
    header('Location: ' . url_for('player_dashboard.php'));
    exit;
}

$err = '';
$success = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'not_sent') {
    $err = 'OTP could not be sent. Please check your contact and try resending.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resend'])) {
        if (!can_resend_otp($userId, $purpose)) {
            $err = 'Please wait 30 seconds before requesting another OTP.';
        } else {
            if (send_user_otp($user, $purpose)) {
                $success = 'OTP resent successfully. Please check your email or SMS.';
            } else {
                $err = 'Failed to resend OTP. Please try again later.';
            }
        }
    } else {
        $otpInput = trim((string)($_POST['otp'] ?? ''));
        if ($otpInput === '') {
            $err = 'Please enter OTP.';
        } else {
            $result = verify_otp($otpInput, $userId, $purpose);
            if (!$result['ok']) {
                $err = $result['message'];
            } else {
                db()->prepare('UPDATE users SET verified = 1 WHERE id = :id')->execute([':id' => $userId]);
                $user['verified'] = 1;
                clear_otp_tokens($userId, $purpose);
                unset($_SESSION['otp_pending']);
                login_user($user);

                if ($user['role'] === 'admin') {
                    header('Location: ' . url_for('admin.php'));
                    exit;
                }
                if ($user['role'] === 'organizer') {
                    header('Location: ' . url_for('organizer_dashboard.php'));
                    exit;
                }
                if (!empty($user['player_id'])) {
                    header('Location: ' . url_for('player.php?id=' . (int)$user['player_id']));
                    exit;
                }

                header('Location: ' . url_for('index.php'));
                exit;
            }
        }
    }
}

$remaining = get_remaining_otp_time($userId, $purpose);
$canResend = can_resend_otp($userId, $purpose);
$nextResendIn = 0;
if (!$canResend) {
    $record = get_otp_record($userId, $purpose);
    if ($record) {
        $sentAt = new DateTimeImmutable($record['last_sent_at'], new DateTimeZone('UTC'));
        $diff = 30 - ((new DateTimeImmutable('now', new DateTimeZone('UTC')))->getTimestamp() - $sentAt->getTimestamp());
        $nextResendIn = max(0, $diff);
    }
}

require_once __DIR__ . '/header.php';
?>
<div class="row justify-content-center">
  <div class="col-lg-6 col-xl-5">
    <div class="cp-card p-4 p-md-5">
      <h2 class="fw-bold mb-1">OTP Verification</h2>
      <div class="cp-muted mb-4">Enter the 6-digit code sent to your startup contact.</div>

      <?php if ($err): ?>
        <div class="alert alert-danger"><?= h($err) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
      <?php endif; ?>

      <div class="mb-3">
        <div class="small cp-muted">Sending OTP to: </div>
        <div><strong><?= h($user['email'] ?: '') ?><?= $user['phone'] ? ' / ' . h($user['phone']) : '' ?></strong></div>
        <div class="cp-muted small mt-2">OTP expires in <?= h((string)($remaining ?: 0)) ?> seconds.</div>
      </div>

      <form method="post" class="row g-3">
        <div class="col-12">
          <label class="form-label">OTP Code</label>
          <input class="form-control" type="text" name="otp" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="123456" required>
        </div>

        <div class="col-12 d-grid">
          <button class="btn btn-cp btn-lg" type="submit">Verify OTP</button>
        </div>
      </form>

      <form method="post" class="mt-3">
        <button class="btn btn-secondary" type="submit" name="resend" <?= $canResend ? '' : 'disabled' ?>>Resend OTP <?= $canResend ? '' : '(' . $nextResendIn . 's)' ?></button>
      </form>

      <div class="mt-4 small cp-muted">
        <div>Failed to receive code? Double-check your spam folder for email or SMS provider delivery delays.</div>
        <div>OTP is valid for 5 minutes and 3 attempts only.</div>
      </div>

      <div class="mt-4 text-center">
        <a href="<?= h(url_for('login.php')) ?>">Back to login</a>
      </div>
    </div>
  </div>
</div>
<?php
if (!empty($_SESSION['otp_debug']['otp'])) {
    echo "<div style='color:green;font-weight:bold'>
    TEST OTP: " . $_SESSION['otp_debug']['otp'] . "
    </div>";
}
?>

<?php require_once __DIR__ . '/footer.php'; ?>
