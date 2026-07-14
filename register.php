<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/otp.php';
require_once __DIR__ . '/contact_util.php';
require_once __DIR__ . '/upload.php';

if (is_logged_in()) {
    header('Location: ' . url_for('index.php'));
    exit;
}

$err = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phoneRaw = trim((string)($_POST['phone'] ?? ''));
    $phone = normalized_contact_phone($phoneRaw);
    $pass = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password2'] ?? '');

    if ($name === '' || $pass === '' || $pass2 === '') {
        $err = 'Please fill all required fields.';
    } elseif ($email === '' && $phone === null) {
        $err = 'Provide email or mobile number.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Please enter a valid email.';
    } elseif ($phoneRaw !== '' && $phone === null) {
        $err = 'Please enter a valid mobile number (at least 8 digits).';
    } elseif ($pass !== $pass2) {
        $err = 'Passwords do not match.';
    } elseif (strlen($pass) < 8) {
        $err = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $pass)) {
        $err = 'Password must contain at least one uppercase letter.';
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($email !== '') {
                $st = $pdo->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
                $st->execute([':e' => $email]);
                if ($st->fetch()) {
                    throw new RuntimeException('Email is already registered.');
                }
            }

            if ($phone !== null) {
                $st = $pdo->prepare('SELECT id FROM users WHERE phone = :p LIMIT 1');
                $st->execute([':p' => $phone]);
                if ($st->fetch()) {
                    throw new RuntimeException('Mobile number is already registered.');
                }
            }

            $photoPath = save_player_photo($_FILES['photo'] ?? []);

            $st = $pdo->prepare('SELECT id FROM players WHERE full_name = :n LIMIT 1');
            $st->execute([':n' => $name]);
            $playerRow = $st->fetch();
            if ($playerRow) {
                $playerId = (int)$playerRow['id'];
            } else {
                $st = $pdo->prepare('INSERT INTO players (full_name, photo_path) VALUES (:n, :p)');
                $st->execute([':n' => $name, ':p' => $photoPath]);
                $playerId = (int)$pdo->lastInsertId();
            }

            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $st = $pdo->prepare('INSERT INTO users (name, email, phone, password_hash, role, player_id, verified) VALUES (:n, :e, :p, :h, :r, :pid, 0)');
            $st->execute([
                ':n' => $name,
                ':e' => $email !== '' ? $email : null,
                ':p' => $phone,
                ':h' => $hash,
                ':r' => 'player',
                ':pid' => $playerId,
            ]);

            $userId = (int)$pdo->lastInsertId();
            $pdo->commit();

            $userRow = ['id' => $userId, 'name' => $name, 'email' => $email, 'phone' => $phone, 'role' => 'player'];
            if (!send_user_otp($userRow, 'registration')) {
                // Keep pending OTP state and show message if email/SMS failed
                $_SESSION['otp_pending'] = ['user_id' => $userId, 'purpose' => 'registration'];
                header('Location: ' . url_for('otp_verify.php') . '?msg=not_sent');
                exit;
            }

            $_SESSION['otp_pending'] = ['user_id' => $userId, 'purpose' => 'registration'];
            header('Location: ' . url_for('otp_verify.php'));
            exit;
        } catch (Throwable $e) {
          if ($pdo->inTransaction()) {
            $pdo->rollBack();
          }
          $err = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-7 col-xl-6">
    <div class="cp-card p-4 p-md-5">
      <h2 class="fw-bold mb-1">Player Registration</h2>
      <div class="cp-muted mb-4">Create your account to track points & ranking.</div>

      <?php if ($err): ?>
        <div class="alert alert-danger"><?= h($err) ?></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="row g-3">
        <div class="col-12">
          <label class="form-label">Full Name *</label>
          <input data-autofocus class="form-control" name="name" value="<?= h($_POST['name'] ?? '') ?>" required>
        </div>
        <div class="col-12">
          <label class="form-label">Email</label>
          <input class="form-control" type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" placeholder="you@example.com">
        </div>
        <div class="col-12">
          <label class="form-label">Mobile Number</label>
          <input class="form-control" type="tel" name="phone" value="<?= h($_POST['phone'] ?? '') ?>" placeholder="e.g. +91 98765 43210">
          <div class="form-text cp-muted">Enter at least 8 digits. Either email or mobile is required.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Password *</label>
          <input class="form-control" type="password" name="password" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Confirm Password *</label>
          <input class="form-control" type="password" name="password2" required>
        </div>
        <div class="col-12">
          <label class="form-label">Player Photo (optional)</label>
          <input class="form-control" type="file" name="photo" accept=".jpg,.jpeg,.png,.webp">
          <div class="form-text cp-muted">Max 5MB. JPG/PNG/WebP.</div>
        </div>
        <div class="col-12 d-grid">
          <button class="btn btn-cp btn-lg" type="submit">Create Account</button>
        </div>
        <div class="col-12 text-center">
          <span class="cp-muted">Already have an account?</span>
          <a href="<?= h(url_for('login.php')) ?>">Login</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

