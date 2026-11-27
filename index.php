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

$recentOrders = $pdo->query(
    'SELECT order_no, payment_method, total_amount, created_at
     FROM orders
     ORDER BY id DESC
     LIMIT 6'
)->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h3 class="mb-0 page-heading"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h3>
    <small class="text-muted">Today's overview and quick POS actions</small>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="shifts.php" class="btn btn-outline-dark"><i class="bi bi-clock-history"></i> Shifts</a>
    <a href="menu_import.php" class="btn btn-outline-primary"><i class="bi bi-card-list"></i> Menu</a>
    <a href="pos.php" class="btn btn-success fw-semibold px-4"><i class="bi bi-cart3"></i> Open POS</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="pos-card stat-card p-4 h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-label">Today's Sales</div>
          <div class="stat-value num-col"><?= number_format((float) $today['total_sales'], 2) ?></div>
          <div class="stat-foot">Completed bills today</div>
        </div>
        <span class="stat-icon"><i class="bi bi-cash-stack"></i></span>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="pos-card stat-card p-4 h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-label">Orders Today</div>
          <div class="stat-value num-col"><?= (int) $today['bill_count'] ?></div>
          <div class="stat-foot">Total receipts generated</div>
        </div>
        <span class="stat-icon"><i class="bi bi-receipt"></i></span>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="pos-card stat-card p-4 h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-label">Active Menu Items</div>
          <div class="stat-value num-col"><?= $menuCount ?></div>
          <div class="stat-foot">Ready for billing</div>
        </div>
        <span class="stat-icon"><i class="bi bi-card-list"></i></span>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="pos-card p-4 h-100">
      <h5 class="fw-bold mb-3"><i class="bi bi-clock-history me-2"></i>Shift Status</h5>
      <?php if ($openShift): ?>
        <div class="alert alert-success mb-3">
          <div class="fw-bold">Shift #<?= (int) $openShift['id'] ?> is OPEN</div>
          <div class="small mt-1">Cashier: <strong><?= htmlspecialchars($openShift['cashier_name']) ?></strong></div>
          <div class="small">Opened: <?= htmlspecialchars($openShift['opened_at']) ?></div>
          <div class="small num-col">Opening Cash: <strong><?= number_format((float) $openShift['opening_cash'], 2) ?></strong></div>
        </div>
        <a href="shifts.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-stop-circle"></i> Manage / Close Shift</a>
      <?php else: ?>
        <div class="alert alert-warning mb-3">
          <div class="fw-bold"><i class="bi bi-exclamation-triangle me-1"></i>No Active Shift</div>
          <div class="small mt-1">Behtar tracking ke liye billing se pehle shift open karein.</div>
        </div>
        <a href="shifts.php" class="btn btn-primary btn-sm"><i class="bi bi-play-circle"></i> Open Shift Now</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="pos-card p-4 h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0"><i class="bi bi-receipt me-2"></i>Recent Orders</h5>
        <a href="reports.php" class="small text-decoration-none">View all in Reports <i class="bi bi-arrow-right"></i></a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Order #</th>
              <th>Payment</th>
              <th>Time</th>
              <th class="text-end">Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$recentOrders): ?>
              <tr><td colspan="4" class="text-center text-muted py-4"><i class="bi bi-inbox d-block fs-4 mb-1"></i>Abhi koi order save nahi hua.</td></tr>
            <?php else: ?>
              <?php foreach ($recentOrders as $ord): ?>
                <tr>
                  <td class="fw-semibold num-col"><?= htmlspecialchars($ord['order_no']) ?></td>
                  <td><span class="badge text-bg-light border"><?= htmlspecialchars($ord['payment_method']) ?></span></td>
                  <td class="text-muted small"><?= htmlspecialchars($ord['created_at']) ?></td>
                  <td class="text-end fw-semibold num-col"><?= number_format((float) $ord['total_amount'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>