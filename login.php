<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/otp.php';

if (is_logged_in()) {

    $u = current_user();

    if ($u['role'] === 'admin') {

        header('Location: admin.php');
        exit;

    }

    if ($u['role'] === 'organizer') {
        header('Location: organizer_dashboard.php');
        exit;
    }

    if (!empty($u['player_id'])) {

        header('Location: player.php?id=' . (int)$u['player_id']);
        exit;

    }

    header('Location: index.php');
    exit;
}

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $login = trim((string)($_POST['login'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');

    if ($login === '' || $pass === '') {
        $err = 'Email/mobile and password are required.';
    } else {
        $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL) !== false;
        $query = $isEmail
            ? 'SELECT * FROM users WHERE email = :value LIMIT 1'
            : 'SELECT * FROM users WHERE phone = :value LIMIT 1';

        $st = db()->prepare($query);
        $st->execute([':value' => $login]);

        $u = $st->fetch();

        if (!$u || !password_verify($pass, (string)$u['password_hash'])) {
            $err = 'Invalid email/mobile or password.';
        } else {
            if (!((bool)($u['verified'] ?? false))) {
                $_SESSION['otp_pending'] = ['user_id' => (int)$u['id'], 'purpose' => 'login'];
                $sent = send_user_otp($u, 'login');
                if (!$sent) {
                    $err = 'Unable to send OTP. Please try again in a few moments.';
                } else {
                    header('Location: ' . url_for('otp_verify.php'));
                    exit;
                }
            } else {
                login_user($u);

                if ($u['role'] === 'admin') {
                    header('Location: admin.php');
                    exit;
                }

                if ($u['role'] === 'organizer') {
                    header('Location: organizer_dashboard.php');
                    exit;
                }

                if (!empty($u['player_id'])) {
                    header('Location: player.php?id=' . (int)$u['player_id']);
                    exit;
                }

                header('Location: index.php');
                exit;
            }
        }
    }
}

require_once __DIR__ . '/header.php';
?>
<div class="row justify-content-center">
  <div class="col-lg-6 col-xl-5">
    <div class="cp-card p-4 p-md-5">
      <h2 class="fw-bold mb-1">Login</h2>
      <div class="cp-muted mb-4">Admin or Player login.</div>

      <?php if ($err): ?>
        <div class="alert alert-danger"><?= h($err) ?></div>
      <?php endif; ?>

      <form method="post" class="row g-3">
        <div class="col-12">
          <label class="form-label">Email or Mobile</label>
          <input class="form-control"
                 type="text"
                 name="login"
                 value="<?= h($_POST['login'] ?? '') ?>"
                 placeholder="Enter your email or mobile"
                 required>
        </div>

        <div class="col-12">
          <label class="form-label">Password</label>
          <input class="form-control"
                 type="password"
                 name="password"
                 required>
        </div>

        <div class="col-12 d-grid">
          <button class="btn btn-cp btn-lg" type="submit">
            Login
          </button>
        </div>

        <div class="col-12 text-center">
          <span class="cp-muted">New player?</span>
          <a href="register.php">Register</a>
        </div>
      </form>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>