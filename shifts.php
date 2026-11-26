<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$error = '';
$success = '';

$openShiftStmt = $pdo->query("SELECT * FROM shifts WHERE status = 'OPEN' ORDER BY id DESC LIMIT 1");
$openShift = $openShiftStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $stmt->execute([$_SESSION['user_id'], $cashierName, $openingCash]);
            header('Location: shifts.php?msg=opened');
            exit;
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
            header('Location: shifts.php?msg=closed');
            exit;
        }
    }
}

if (($_GET['msg'] ?? '') === 'opened') {
    $success = 'Shift open ho gayi hai.';
} elseif (($_GET['msg'] ?? '') === 'closed') {
    $success = 'Shift close ho gayi hai.';
}

$openShiftStmt = $pdo->query("SELECT * FROM shifts WHERE status = 'OPEN' ORDER BY id DESC LIMIT 1");
$openShift = $openShiftStmt->fetch();

$shiftsList = $pdo->query('SELECT * FROM shifts ORDER BY id DESC LIMIT 30')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<h3 class="mb-3">Shifts</h3>

<?php if ($error !== ''): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success !== ''): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($openShift): ?>
  <div class="card card-body border-success mb-4">
    <h5 class="card-title text-success">Active Open Shift #<?= (int) $openShift['id'] ?></h5>
    <p class="mb-3">
      <strong>Cashier:</strong> <?= htmlspecialchars($openShift['cashier_name']) ?><br>
      <strong>Opened At:</strong> <?= htmlspecialchars($openShift['opened_at']) ?><br>
      <strong>Opening Cash:</strong> <?= number_format((float) $openShift['opening_cash'], 2) ?>
    </p>
    <form method="post" class="row g-2 align-items-end">
      <input type="hidden" name="action" value="close_shift">
      <input type="hidden" name="shift_id" value="<?= (int) $openShift['id'] ?>">
      <div class="col-md-4">
        <label class="form-label">Closing Cash</label>
        <input type="number" step="0.01" min="0" name="closing_cash" class="form-control" required>
      </div>
      <div class="col-md-3">
        <button type="submit" class="btn btn-danger w-100">Close Shift</button>
      </div>
    </form>
  </div>
<?php else: ?>
  <div class="card card-body mb-4">
    <h5 class="card-title">Start New Shift</h5>
    <form method="post" class="row g-2 align-items-end">
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
        <button type="submit" class="btn btn-primary w-100">Open Shift</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<h5>Recent Shifts</h5>
<div class="table-responsive">
  <table class="table table-bordered table-striped bg-white">
    <thead>
      <tr>
        <th>#</th>
        <th>Cashier</th>
        <th>Opened</th>
        <th>Closed</th>
        <th>Opening Cash</th>
        <th>Closing Cash</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$shiftsList): ?>
        <tr><td colspan="7" class="text-center text-muted">Abhi koi shift record nahi hai.</td></tr>
      <?php else: ?>
        <?php foreach ($shiftsList as $s): ?>
          <tr>
            <td><?= (int) $s['id'] ?></td>
            <td><?= htmlspecialchars($s['cashier_name']) ?></td>
            <td><?= htmlspecialchars($s['opened_at']) ?></td>
            <td><?= htmlspecialchars((string) ($s['closed_at'] ?? '-')) ?></td>
            <td><?= number_format((float) $s['opening_cash'], 2) ?></td>
            <td><?= $s['closing_cash'] !== null ? number_format((float) $s['closing_cash'], 2) : '-' ?></td>
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