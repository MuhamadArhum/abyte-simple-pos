<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/csrf.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid name and email.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = 'This email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
            $insert->execute([$name, $email, $hash]);
            $success = 'Account created successfully. You can login now.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="pos-card p-4">
      <div class="text-center mb-4">
        <span class="brand-mark mb-3"><i class="bi bi-shop"></i></span>
        <span class="badge text-bg-primary auth-badge mb-2 d-block w-100"><i class="bi bi-person-plus me-1"></i>NEW ACCOUNT</span>
        <h4 class="fw-bold mb-1">Create Account</h4>
        <div class="text-muted small">POS system use karne ke liye account banayein</div>
      </div>

      <?php if ($error !== ''): ?>
        <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($success !== ''): ?>
        <div class="alert alert-success py-2 small">
          <?= htmlspecialchars($success) ?> <a href="login.php" class="fw-bold">Login here</a>
        </div>
      <?php endif; ?>

      <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label fw-semibold">Full Name</label>
          <input type="text" name="name" class="form-control" placeholder="Ali Raza" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Email</label>
          <input type="email" name="email" class="form-control" placeholder="ali@example.com" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Password</label>
          <input type="password" name="password" class="form-control" minlength="6" placeholder="Min. 6 characters" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold"><i class="bi bi-person-plus"></i> Create Account</button>
      </form>

      <hr class="my-4">
      <div class="text-center small">
        <span class="text-muted">Already registered?</span>
        <a href="login.php" class="fw-semibold text-decoration-none"><i class="bi bi-box-arrow-in-right"></i> Sign in</a>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>