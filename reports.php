<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$fromDate = trim((string) ($_GET['from_date'] ?? date('Y-m-d')));
$toDate = trim((string) ($_GET['to_date'] ?? date('Y-m-d')));
$shiftId = (int) ($_GET['shift_id'] ?? 0);
$paymentMethod = strtoupper(trim((string) ($_GET['payment_method'] ?? '')));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
    $fromDate = date('Y-m-d');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
    $toDate = date('Y-m-d');
}
if (!in_array($paymentMethod, ['CASH', 'CARD', 'ONLINE'], true)) {
    $paymentMethod = '';
}

$shifts = $pdo->query('SELECT id, cashier_name, opened_at, status FROM shifts ORDER BY id DESC')->fetchAll();

$where = ['DATE(o.created_at) >= ?', 'DATE(o.created_at) <= ?'];
$params = [$fromDate, $toDate];

if ($shiftId > 0) {
    $where[] = 'o.shift_id = ?';
    $params[] = $shiftId;
}
if ($paymentMethod !== '') {
    $where[] = 'o.payment_method = ?';
    $params[] = $paymentMethod;
}

$whereSql = implode(' AND ', $where);

$sumSql = "SELECT COUNT(*) AS order_count, COALESCE(SUM(o.total_amount), 0) AS total_sales
           FROM orders o
           WHERE {$whereSql}";
$sumStmt = $pdo->prepare($sumSql);
$sumStmt->execute($params);
$summary = $sumStmt->fetch();

$listSql = "SELECT o.id, o.order_no, o.shift_id, o.payment_method, o.total_amount, o.created_at, s.cashier_name
            FROM orders o
            LEFT JOIN shifts s ON s.id = o.shift_id
            WHERE {$whereSql}
            ORDER BY o.id DESC";
$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$orders = $listStmt->fetchAll();

$exportQuery = http_build_query([
    'from_date'      => $fromDate,
    'to_date'        => $toDate,
    'shift_id'       => $shiftId > 0 ? $shiftId : '',
    'payment_method' => $paymentMethod,
]);

require_once __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3 no-print">
  <h3 class="mb-0 page-heading"><i class="bi bi-graph-up-arrow me-2"></i>Sales Reports</h3>
  <div class="d-flex gap-2">
    <a href="export_csv.php?<?= htmlspecialchars($exportQuery) ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-download"></i> Export CSV</a>
    <button type="button" onclick="window.print()" class="btn btn-outline-dark btn-sm"><i class="bi bi-printer"></i> Print Report</button>
  </div>
</div>

<form method="get" class="card card-body mb-4 no-print">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label">From Date</label>
      <input type="date" name="from_date" value="<?= htmlspecialchars($fromDate) ?>" class="form-control">
    </div>
    <div class="col-md-3">
      <label class="form-label">To Date</label>
      <input type="date" name="to_date" value="<?= htmlspecialchars($toDate) ?>" class="form-control">
    </div>
    <div class="col-md-3">
      <label class="form-label">Shift</label>
      <select name="shift_id" class="form-select">
        <option value="">All Shifts</option>
        <?php foreach ($shifts as $s): ?>
          <option value="<?= (int) $s['id'] ?>" <?= $shiftId === (int) $s['id'] ? 'selected' : '' ?>>
            #<?= (int) $s['id'] ?> - <?= htmlspecialchars($s['cashier_name']) ?> (<?= htmlspecialchars($s['status']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label">Payment</label>
      <select name="payment_method" class="form-select">
        <option value="">All</option>
        <option value="CASH" <?= $paymentMethod === 'CASH' ? 'selected' : '' ?>>CASH</option>
        <option value="CARD" <?= $paymentMethod === 'CARD' ? 'selected' : '' ?>>CARD</option>
        <option value="ONLINE" <?= $paymentMethod === 'ONLINE' ? 'selected' : '' ?>>ONLINE</option>
      </select>
    </div>
    <div class="col-md-1">
      <button type="submit" class="btn btn-primary w-100">Go</button>
    </div>
  </div>
</form>

<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card card-body stat-card">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-label">Filtered Total Sales</div>
          <div class="stat-value num-col"><?= number_format((float) $summary['total_sales'], 2) ?></div>
        </div>
        <span class="stat-icon"><i class="bi bi-cash-stack"></i></span>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card card-body stat-card">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="stat-label">Filtered Orders Count</div>
          <div class="stat-value num-col"><?= (int) $summary['order_count'] ?></div>
        </div>
        <span class="stat-icon"><i class="bi bi-receipt"></i></span>
      </div>
    </div>
  </div>
</div>

<div class="table-responsive">
  <table class="table table-bordered table-striped bg-white">
    <thead>
      <tr>
        <th>Order #</th>
        <th>Date & Time</th>
        <th>Shift</th>
        <th>Payment</th>
        <th class="text-end">Total</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$orders): ?>
        <tr><td colspan="5" class="text-center text-muted py-4"><i class="bi bi-inbox d-block fs-4 mb-1"></i>Selected filters ke mutabiq koi order nahi mila.</td></tr>
      <?php else: ?>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td><?= htmlspecialchars($o['order_no']) ?></td>
            <td><?= htmlspecialchars($o['created_at']) ?></td>
            <td><?= $o['shift_id'] ? '#' . (int) $o['shift_id'] . ' (' . htmlspecialchars((string) $o['cashier_name']) . ')' : '-' ?></td>
            <td><?= htmlspecialchars($o['payment_method']) ?></td>
            <td class="text-end"><?= number_format((float) $o['total_amount'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>