<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

$error = '';
$success = '';

$openShiftSql = "SELECT s.*, COALESCE((
    SELECT SUM(o.total_amount) FROM orders o
    WHERE o.shift_id = s.id AND o.payment_method = 'CASH'
), 0) AS cash_sales
FROM shifts s WHERE s.status = 'OPEN' ORDER BY s.id DESC LIMIT 1";
$openShiftStmt = $pdo->query($openShiftSql);
$openShift = $openShiftStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'open_shift') {
        if ($openShift) {
            $error = 'Ek shift pehle se open hai. Pehle usay close karein.';
        } else {
            $cashierName = trim($_POST['cashier_name'] ?? '');
            if ($cashierName === '') {
                $cashierName = (string) ($_SESSION['user_name'] ?? 'Cashier');
            }
            $openingCash = is_numeric($_POST['opening_cash'] ?? '') ? (float) $_POST['opening_cash'] : 0.00;
            if ($openingCash < 0) {
                $openingCash = 0.00;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO shifts (user_id, cashier_name, opened_at, opening_cash, status)
                 VALUES (?, ?, NOW(), ?, "OPEN")'
            );
            // Use a database-scoped lock so two cashiers cannot open a shift together.
            $lockName = $pdo->query("SELECT SHA2(CONCAT(DATABASE(), ':open_shift'), 256)")->fetchColumn();
            $lock = $pdo->prepare('SELECT GET_LOCK(?, 5)');
            $lock->execute([$lockName]);
            if ((int) $lock->fetchColumn() !== 1) {
                $error = 'Shift open nahi ho saki. Dobara koshish karein.';
            } else {
                try {
                    if ($pdo->query("SELECT id FROM shifts WHERE status = 'OPEN' LIMIT 1")->fetchColumn()) {
                        $error = 'Ek shift pehle se open hai. Pehle usay close karein.';
                    } else {
                        $stmt->execute([$_SESSION['user_id'], $cashierName, $openingCash]);
                    }
                } finally {
                    $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                    $release->execute([$lockName]);
                }
                if ($error === '') {
                    header('Location: shifts.php?msg=opened');
                    exit;
                }
            }
        }
    } elseif ($action === 'close_shift') {
        $shiftId = (int) ($_POST['shift_id'] ?? 0);
        $closingCashRaw = $_POST['closing_cash'] ?? '';

        if (!$openShift || (int) $openShift['id'] !== $shiftId) {
            $error = 'Valid open shift nahi mili.';
        } elseif (!is_numeric($closingCashRaw) || (float) $closingCashRaw < 0) {
            $error = 'Valid closing cash enter karein.';
        } else {
            $closingCash = (float) $closingCashRaw;
            $stmt = $pdo->prepare(
                'UPDATE shifts
                 SET closed_at = NOW(), closing_cash = ?, status = "CLOSED"
                 WHERE id = ? AND status = "OPEN"'
            );
            $stmt->execute([$closingCash, $shiftId]);
            if ($stmt->rowCount() === 1) {
                header('Location: shifts.php?msg=closed');
                exit;
            }
            $error = 'Shift pehle hi close ho chuki hai.';
        }
    }
}

if (($_GET['msg'] ?? '') === 'opened') {
    $success = 'Shift open ho gayi hai.';
} elseif (($_GET['msg'] ?? '') === 'closed') {
    $success = 'Shift close ho gayi hai.';
}

$openShiftStmt = $pdo->query($openShiftSql);
$openShift = $openShiftStmt->fetch();

$shiftsList = $pdo->query("SELECT s.*, COALESCE((
    SELECT SUM(o.total_amount) FROM orders o
    WHERE o.shift_id = s.id AND o.payment_method = 'CASH'
), 0) AS cash_sales FROM shifts s ORDER BY s.id DESC LIMIT 30")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<h3 class="mb-3 page-heading"><i class="bi bi-clock-history me-2"></i>Shifts</h3>

<?php if ($error !== ''): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success !== ''): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($openShift): ?>
  <div class="card card-body border-success mb-4">
    <h5 class="card-title text-success"><i class="bi bi-record-circle-fill me-1"></i>Active Open Shift #<?= (int) $openShift['id'] ?></h5>
    <p class="mb-3">
      <strong>Cashier:</strong> <?= htmlspecialchars($openShift['cashier_name']) ?><br>
      <strong>Opened At:</strong> <?= htmlspecialchars($openShift['opened_at']) ?><br>
      <strong>Opening Cash:</strong> <?= number_format((float) $openShift['opening_cash'], 2) ?><br>
      <strong>Cash Sales:</strong> <?= number_format((float) $openShift['cash_sales'], 2) ?><br>
      <strong>Expected Cash:</strong> <?= number_format((float) $openShift['opening_cash'] + (float) $openShift['cash_sales'], 2) ?>
    </p>
    <form method="post" class="row g-2 align-items-end">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="close_shift">
      <input type="hidden" name="shift_id" value="<?= (int) $openShift['id'] ?>">
      <div class="col-md-4">
        <label class="form-label">Closing Cash</label>
        <input type="number" step="0.01" min="0" name="closing_cash" class="form-control" required>
      </div>
      <div class="col-md-3">
        <button type="submit" class="btn btn-danger w-100"><i class="bi bi-stop-circle"></i> Close Shift</button>
      </div>
    </form>
  </div>
<?php else: ?>
  <div class="card card-body mb-4">
    <h5 class="card-title"><i class="bi bi-play-circle me-1"></i>Start New Shift</h5>
    <form method="post" class="row g-2 align-items-end">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="open_shift">
      <div class="col-md-5">
        <label class="form-label">Cashier Name</label>
        <input type="text" name="cashier_name" class="form-control" value="<?= htmlspecialchars((string) ($_SESSION['user_name'] ?? '')) ?>" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Opening Cash</label>
        <input type="number" step="0.01" min="0" name="opening_cash" value="0.00" class="form-control" required>
      </div>
      <div class="col-md-3">
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-play-circle"></i> Open Shift</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<h5 class="page-heading mb-3"><i class="bi bi-list-ul me-1"></i>Recent Shifts</h5>
<div class="table-responsive">
  <table class="table table-bordered table-striped bg-white">
    <thead>
      <tr>
        <th>#</th>
        <th>Cashier</th>
        <th>Opened</th>
        <th>Closed</th>
        <th>Opening Cash</th>
      <th>Expected Cash</th>
        <th>Closing Cash</th>
      <th>Cash Difference</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$shiftsList): ?>
        <tr><td colspan="9" class="text-center text-muted py-4"><i class="bi bi-inbox d-block fs-4 mb-1"></i>Abhi koi shift record nahi hai.</td></tr>
      <?php else: ?>
        <?php foreach ($shiftsList as $s): ?>
          <?php $expectedCash = (float) $s['opening_cash'] + (float) $s['cash_sales']; ?>
          <tr>
            <td><?= (int) $s['id'] ?></td>
            <td><?= htmlspecialchars($s['cashier_name']) ?></td>
            <td><?= htmlspecialchars($s['opened_at']) ?></td>
            <td><?= htmlspecialchars((string) ($s['closed_at'] ?? '-')) ?></td>
            <td><?= number_format((float) $s['opening_cash'], 2) ?></td>
            <td><?= number_format($expectedCash, 2) ?></td>
            <td><?= $s['closing_cash'] !== null ? number_format((float) $s['closing_cash'], 2) : '-' ?></td>
            <td><?= $s['closing_cash'] !== null ? number_format(round((float) $s['closing_cash'] - $expectedCash, 2), 2) : '-' ?></td>
            <td>
              <span class="badge <?= $s['status'] === 'OPEN' ? 'bg-success' : 'bg-secondary' ?>">
                <?= htmlspecialchars($s['status']) ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
