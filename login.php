<?php
require_once __DIR__ . '/config/db.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT id, name, password_hash FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['name'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid email or password.';
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center py-4">
  <div class="col-md-5 col-lg-4">
    <div class="pos-card p-4">
      <div class="text-center mb-4">
        <span class="badge text-bg-dark mb-2">POS ACCESS</span>
        <h4 class="fw-bold mb-1">Sign In</h4>
        <div class="text-muted small">Apne cashier/admin account se login karein</div>
      </div>

      <?php if ($error !== ''): ?>
        <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="mb-3">
          <label class="form-label fw-semibold">Email</label>
          <input type="email" name="email" class="form-control" placeholder="name@example.com" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Password</label>
          <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-dark w-100 py-2 fw-semibold">Login</button>
      </form>

      <hr class="my-4">
      <div class="text-center small">
        <span class="text-muted">Naya account chahiye?</span>
        <a href="register.php" class="fw-semibold text-decoration-none">Register here</a>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>