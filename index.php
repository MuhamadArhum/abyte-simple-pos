<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$salesStmt = $pdo->prepare(
    'SELECT COUNT(*) AS bill_count, COALESCE(SUM(total_amount), 0) AS total_sales
     FROM orders
     WHERE DATE(created_at) = CURDATE()'
);
$salesStmt->execute();
$today = $salesStmt->fetch();

$menuCount = (int) $pdo->query('SELECT COUNT(*) FROM menu_items WHERE is_active = 1')->fetchColumn();

$shiftStmt = $pdo->query("SELECT id, cashier_name, opening_cash, opened_at FROM shifts WHERE status = 'OPEN' ORDER BY id DESC LIMIT 1");
$openShift = $shiftStmt->fetch();

require_once __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Dashboard</h3>
  <a href="pos.php" class="btn btn-success">Open POS Screen</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card card-body">
      <div class="text-muted">Today's Total Sales</div>
      <h2 class="mt-1"><?= number_format((float) $today['total_sales'], 2) ?></h2>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card card-body">
      <div class="text-muted">Orders Today</div>
      <h2 class="mt-1"><?= (int) $today['bill_count'] ?></h2>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card card-body">
      <div class="text-muted">Active Menu Items</div>
      <h2 class="mt-1"><?= $menuCount ?></h2>
    </div>
  </div>
</div>

<div class="card card-body">
  <h5 class="card-title">Shift Status</h5>
  <?php if ($openShift): ?>
    <p class="mb-2 text-success">
      <strong>Open Shift #<?= (int) $openShift['id'] ?></strong> — Cashier: <?= htmlspecialchars($openShift['cashier_name']) ?>
      (Opening Cash: <?= number_format((float) $openShift['opening_cash'], 2) ?>)
    </p>
    <a href="shifts.php" class="btn btn-outline-dark btn-sm">Manage / Close Shift</a>
  <?php else: ?>
    <p class="mb-2 text-warning">Koi shift open nahi hai.</p>
    <a href="shifts.php" class="btn btn-primary btn-sm">Start Shift</a>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>