<?php
// Order history + reprint (Simple POS).
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$orderId = (int) ($_GET['id'] ?? 0);

// ---------- Single order / reprint ----------
if ($orderId > 0) {
    $oStmt = $pdo->prepare(
        'SELECT o.*, s.cashier_name FROM orders o LEFT JOIN shifts s ON s.id = o.shift_id WHERE o.id = ? LIMIT 1'
    );
    $oStmt->execute([$orderId]);
    $order = $oStmt->fetch();

    if (!$order) {
        header('Location: orders.php');
        exit;
    }

    $iStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
    $iStmt->execute([$orderId]);
    $items = $iStmt->fetchAll();

    require_once __DIR__ . '/includes/header.php';
    ?>
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
      <h3 class="mb-0 page-heading"><i class="bi bi-receipt me-2"></i>Order <span class="num-col"><?= htmlspecialchars($order['order_no']) ?></span></h3>
      <a href="orders.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Orders</a>
    </div>

    <div class="receipt-box p-4 mb-4">
      <div class="text-center border-bottom pb-3 mb-3">
        <h5 class="fw-bold mb-1"><i class="bi bi-receipt me-1"></i>POS RECEIPT</h5>
        <div class="small text-muted num-col">Order #: <?= htmlspecialchars($order['order_no']) ?></div>
        <div class="small text-muted num-col">Date: <?= htmlspecialchars($order['created_at']) ?></div>
        <?php if (!empty($order['cashier_name'])): ?>
          <div class="small text-muted">Cashier: <?= htmlspecialchars($order['cashier_name']) ?></div>
        <?php endif; ?>
      </div>

      <table class="table table-sm table-borderless mb-3">
        <thead class="border-bottom small text-muted">
          <tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Total</th></tr>
        </thead>
        <tbody>
          <?php foreach ($items as $pItem): ?>
            <tr>
              <td><?= htmlspecialchars($pItem['item_name']) ?></td>
              <td class="text-center num-col"><?= (int) $pItem['qty'] ?></td>
              <td class="text-end num-col"><?= number_format((float) $pItem['line_total'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="border-top pt-2 mb-3">
        <div class="d-flex justify-content-between small text-muted mb-1">
          <span>Payment Method</span>
          <span class="fw-semibold"><?= htmlspecialchars($order['payment_method']) ?></span>
        </div>
        <div class="d-flex justify-content-between fs-5 fw-bold">
          <span>Total</span>
          <span class="num-col"><?= number_format((float) $order['total_amount'], 2) ?></span>
        </div>
      </div>

      <div class="d-flex gap-2 no-print">
        <button type="button" onclick="window.print()" class="btn btn-dark w-100 fw-semibold"><i class="bi bi-printer"></i> Print Bill</button>
      </div>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// ---------- Order list ----------
$orders = $pdo->query(
    'SELECT o.id, o.order_no, o.created_at, o.shift_id, o.payment_method, o.total_amount, s.cashier_name
     FROM orders o
     LEFT JOIN shifts s ON s.id = o.shift_id
     ORDER BY o.id DESC
     LIMIT 100'
)->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h3 class="mb-0 page-heading"><i class="bi bi-receipt me-2"></i>Order History</h3>
    <small class="text-muted">Recent orders &middot; reprint any bill</small>
  </div>
  <a href="reports.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-graph-up-arrow"></i> Reports</a>
</div>

<div class="card card-body table-responsive">
  <table class="table table-hover align-middle mb-0">
    <thead class="table-light">
      <tr>
        <th>Order #</th>
        <th>Date &amp; Time</th>
        <th>Shift</th>
        <th>Payment</th>
        <th class="text-end">Total</th>
        <th class="text-end no-print">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$orders): ?>
        <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox d-block fs-4 mb-1"></i>Abhi koi order nahi hai.</td></tr>
      <?php else: ?>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td class="fw-semibold num-col"><?= htmlspecialchars($o['order_no']) ?></td>
            <td class="text-muted small"><?= htmlspecialchars($o['created_at']) ?></td>
            <td><?= $o['shift_id'] ? '#' . (int) $o['shift_id'] . ($o['cashier_name'] ? ' (' . htmlspecialchars($o['cashier_name']) . ')' : '') : '-' ?></td>
            <td><span class="badge text-bg-light border"><?= htmlspecialchars($o['payment_method']) ?></span></td>
            <td class="text-end fw-semibold num-col"><?= number_format((float) $o['total_amount'], 2) ?></td>
            <td class="text-end no-print">
              <a href="orders.php?id=<?= (int) $o['id'] ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-printer"></i> View / Reprint</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
