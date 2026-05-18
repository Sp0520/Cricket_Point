<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/upload.php';

require_admin();

$pdo = db();
$err = '';
$ok = '';

// Add new player
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $name = trim((string)($_POST['full_name'] ?? ''));
    if ($name === '') {
        $err = 'Player name is required.';
    } else {
        $photo = save_player_photo($_FILES['photo'] ?? []);
        $st = $pdo->prepare('INSERT INTO players (full_name, photo_path) VALUES (:n, :p)');
        $st->execute([':n' => $name, ':p' => $photo]);
        $ok = 'Player added.';
    }
}

// Update player
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['full_name'] ?? ''));
    if ($id <= 0 || $name === '') {
        $err = 'Invalid player update.';
    } else {
        $photo = save_player_photo($_FILES['photo'] ?? []);
        if ($photo) {
            $st = $pdo->prepare('UPDATE players SET full_name=:n, photo_path=:p WHERE id=:id');
            $st->execute([':n' => $name, ':p' => $photo, ':id' => $id]);
        } else {
            $st = $pdo->prepare('UPDATE players SET full_name=:n WHERE id=:id');
            $st->execute([':n' => $name, ':id' => $id]);
        }
        $ok = 'Player updated.';
    }
}

$players = $pdo->query('SELECT * FROM players ORDER BY full_name ASC')->fetchAll();

require_once __DIR__ . '/header.php';
?>

<div class="admin-shell">
  <?php require_once __DIR__ . '/admin_sidebar.php'; ?>

  <div class="flex-grow-1">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
      <div>
        <h2 class="fw-bold mb-1">Players</h2>
        <div class="cp-muted">Add players and upload photos.</div>
      </div>
    </div>

    <?php if ($err): ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>
    <?php if ($ok): ?><div class="alert alert-success"><?= h($ok) ?></div><?php endif; ?>

    <div class="cp-card p-4 mb-3">
      <div class="fw-bold mb-2">Add Player</div>
      <form method="post" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="action" value="add">
        <div class="col-md-7">
          <label class="form-label">Full Name *</label>
          <input data-autofocus class="form-control" name="full_name" required>
        </div>
        <div class="col-md-5">
          <label class="form-label">Photo (optional)</label>
          <input class="form-control" type="file" name="photo" accept=".jpg,.jpeg,.png,.webp">
        </div>
        <div class="col-12">
          <button class="btn btn-cp" type="submit">Add Player</button>
        </div>
      </form>
    </div>

    <div class="cp-card p-3 p-md-4">
      <div class="fw-bold mb-2">All Players</div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th style="width:80px;">Photo</th>
              <th>Player</th>
              <th style="width:120px;">ID</th>
              <th style="width:320px;">Edit</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$players): ?>
              <tr><td colspan="4" class="cp-muted py-4 text-center">No players yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($players as $p): ?>
              <tr>
                <td>
                  <?php if (!empty($p['photo_path'])): ?>
                    <img class="player-photo" src="<?= h(url_for((string)$p['photo_path'])) ?>" alt="photo">
                  <?php else: ?>
                    <div class="player-photo d-flex align-items-center justify-content-center cp-muted">N/A</div>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="fw-bold"><?= h($p['full_name']) ?></div>
                  <a class="small" href="<?= h(url_for('player.php?id=' . (int)$p['id'])) ?>">View profile</a>
                </td>
                <td class="cp-muted">#<?= (int)$p['id'] ?></td>
                <td>
                  <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <div class="col-12 col-lg-6">
                      <label class="form-label small cp-muted mb-1">Name</label>
                      <input class="form-control form-control-sm" name="full_name" value="<?= h($p['full_name']) ?>" required>
                    </div>
                    <div class="col-12 col-lg-4">
                      <label class="form-label small cp-muted mb-1">New photo</label>
                      <input class="form-control form-control-sm" type="file" name="photo" accept=".jpg,.jpeg,.png,.webp">
                    </div>
                    <div class="col-12 col-lg-2 d-grid">
                      <button class="btn btn-sm btn-outline-success" type="submit">Save</button>
                    </div>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

