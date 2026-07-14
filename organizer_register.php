<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    header('Location: ' . url_for('index.php'));
    exit;
}

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password2'] ?? '');

    if ($name === '' || $email === '' || $pass === '') {
        $err = 'Please fill all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Please enter a valid email.';
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
            $st = $pdo->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
            $st->execute([':e' => $email]);
            if ($st->fetch()) {
                throw new RuntimeException('Email is already registered.');
            }

            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $st = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, player_id) VALUES (:n, :e, :h, :r, NULL)');
            $st->execute([
                ':n' => $name,
                ':e' => $email,
                ':h' => $hash,
                ':r' => 'organizer',
            ]);

            $userId = (int)$pdo->lastInsertId();
            $pdo->commit();

            login_user([
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'role' => 'organizer',
                'player_id' => null,
            ]);
            header('Location: ' . url_for('organizer_dashboard.php'));
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $err = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-7 col-xl-6">
    <div class="cp-card p-4 p-md-5">
      <h2 class="fw-bold mb-1">Organizer registration</h2>
      <div class="cp-muted mb-4">Create your own tournaments and matches, and use the live score board for gully games.</div>

      <?php if ($err): ?>
        <div class="alert alert-danger"><?= h($err) ?></div>
      <?php endif; ?>

      <form method="post" class="row g-3">
        <div class="col-12">
          <label class="form-label">Full name *</label>
          <input data-autofocus class="form-control" name="name" value="<?= h($_POST['name'] ?? '') ?>" required>
        </div>
        <div class="col-12">
          <label class="form-label">Email *</label>
          <input class="form-control" type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Password *</label>
          <input class="form-control" type="password" name="password" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Confirm password *</label>
          <input class="form-control" type="password" name="password2" required>
        </div>
        <div class="col-12 d-grid">
          <button class="btn btn-cp btn-lg" type="submit">Create organizer account</button>
        </div>
        <div class="col-12 text-center">
          <span class="cp-muted">Player account?</span>
          <a href="<?= h(url_for('register.php')) ?>">Player register</a>
          &nbsp;·&nbsp;
          <a href="<?= h(url_for('login.php')) ?>">Login</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
