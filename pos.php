<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

if (!isset($_SESSION['pos_cart']) || !is_array($_SESSION['pos_cart'])) {
    $_SESSION['pos_cart'] = [];
}

$error = '';
$success = '';
$printedOrder = null;
$printedItems = [];

$openShiftStmt = $pdo->query("SELECT id, cashier_name FROM shifts WHERE status = 'OPEN' ORDER BY id DESC LIMIT 1");
$openShift = $openShiftStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_item') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        if ($itemId > 0) {
            $stmt = $pdo->prepare('SELECT id, name, price FROM menu_items WHERE id = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$itemId]);
            $menuItem = $stmt->fetch();

            if ($menuItem) {
                $id = (int) $menuItem['id'];
                if (isset($_SESSION['pos_cart'][$id])) {
                    $_SESSION['pos_cart'][$id]['qty'] += 1;
                } else {
                    $_SESSION['pos_cart'][$id] = [
                        'item_id' => $id,
                        'name'    => (string) $menuItem['name'],
                        'price'   => (float) $menuItem['price'],
                        'qty'     => 1,
                    ];
                }
            }
        }
        header('Location: pos.php');
        exit;
    } elseif ($action === 'inc_qty' || $action === 'dec_qty' || $action === 'remove_item') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        if ($itemId > 0 && isset($_SESSION['pos_cart'][$itemId])) {
            if ($action === 'inc_qty') {
                $_SESSION['pos_cart'][$itemId]['qty'] += 1;
            } elseif ($action === 'dec_qty') {
                $_SESSION['pos_cart'][$itemId]['qty'] -= 1;
                if ($_SESSION['pos_cart'][$itemId]['qty'] <= 0) {
                    unset($_SESSION['pos_cart'][$itemId]);
                }
            } elseif ($action === 'remove_item') {
                unset($_SESSION['pos_cart'][$itemId]);
            }
        }
        header('Location: pos.php');
        exit;
    } elseif ($action === 'clear_cart') {
        $_SESSION['pos_cart'] = [];
        header('Location: pos.php');
        exit;
    } elseif ($action === 'checkout') {
        if (empty($_SESSION['pos_cart'])) {
            $error = 'Cart khaali hai. Pehle koi item add karein.';
        } else {
            $paymentMethod = strtoupper(trim((string) ($_POST['payment_method'] ?? 'CASH')));
            if (!in_array($paymentMethod, ['CASH', 'CARD', 'ONLINE'], true)) {
                $paymentMethod = 'CASH';
            }

            $totalAmount = 0.0;
            foreach ($_SESSION['pos_cart'] as $row) {
                $totalAmount += ((float) $row['price']) * ((int) $row['qty']);
            }
            $totalAmount = round($totalAmount, 2);

            $shiftId = $openShift ? (int) $openShift['id'] : null;
            $orderNo = 'ORD-' . date('Ymd-His') . '-' . random_int(100, 999);

            $pdo->beginTransaction();
            try {
                $orderStmt = $pdo->prepare(
                    'INSERT INTO orders (shift_id, order_no, payment_method, total_amount, created_at)
                     VALUES (?, ?, ?, ?, NOW())'
                );
                $orderStmt->execute([$shiftId, $orderNo, $paymentMethod, $totalAmount]);
                $orderId = (int) $pdo->lastInsertId();

                $itemStmt = $pdo->prepare(
                    'INSERT INTO order_items (order_id, item_id, item_name, qty, unit_price, line_total)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );

                foreach ($_SESSION['pos_cart'] as $row) {
                    $qty = (int) $row['qty'];
                    $unitPrice = round((float) $row['price'], 2);
                    $lineTotal = round($unitPrice * $qty, 2);
                    $itemStmt->execute([
                        $orderId,
                        (int) $row['item_id'],
                        (string) $row['name'],
                        $qty,
                        $unitPrice,
                        $lineTotal,
                    ]);
                }

                $pdo->commit();
                $_SESSION['pos_cart'] = [];
                header('Location: pos.php?receipt=' . $orderId);
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = 'Order save karne mein error aya.';
            }
        }
    }
}

$receiptId = (int) ($_GET['receipt'] ?? 0);
if ($receiptId > 0) {
    $oStmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
    $oStmt->execute([$receiptId]);
    $printedOrder = $oStmt->fetch();

    if ($printedOrder) {
        $iStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
        $iStmt->execute([$receiptId]);
        $printedItems = $iStmt->fetchAll();
        $success = 'Order ' . $printedOrder['order_no'] . ' save ho gaya hai.';
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$selectedCat = trim((string) ($_GET['cat'] ?? ''));

$categories = $pdo->query(
    'SELECT DISTINCT category FROM menu_items WHERE is_active = 1 ORDER BY category ASC'
)->fetchAll(PDO::FETCH_COLUMN);

$sql = 'SELECT id, sku, name, category, price FROM menu_items WHERE is_active = 1';
$params = [];
if ($selectedCat !== '') {
    $sql .= ' AND category = ?';
    $params[] = $selectedCat;
}
if ($search !== '') {
    $sql .= ' AND (name LIKE ? OR sku LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$sql .= ' ORDER BY category ASC, name ASC';

$menuStmt = $pdo->prepare($sql);
$menuStmt->execute($params);
$menuItems = $menuStmt->fetchAll();

$cartTotal = 0.0;
foreach ($_SESSION['pos_cart'] as $cItem) {
    $cartTotal += ((float) $cItem['price']) * ((int) $cItem['qty']);
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($printedOrder): ?>
  <div class="receipt-box p-4 mb-4">
    <div class="text-center border-bottom pb-3 mb-3">
      <h5 class="fw-bold mb-1">POS RECEIPT</h5>
      <div class="small text-muted num-col">Order #: <?= htmlspecialchars($printedOrder['order_no']) ?></div>
      <div class="small text-muted num-col">Date: <?= htmlspecialchars($printedOrder['created_at']) ?></div>
    </div>

    <table class="table table-sm table-borderless mb-3">
      <thead class="border-bottom small text-muted">
        <tr>
          <th>Item</th>
          <th class="text-center">Qty</th>
          <th class="text-end">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($printedItems as $pItem): ?>
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
        <span class="fw-semibold"><?= htmlspecialchars($printedOrder['payment_method']) ?></span>
      </div>
      <div class="d-flex justify-content-between fs-5 fw-bold">
        <span>Total</span>
        <span class="num-col"><?= number_format((float) $printedOrder['total_amount'], 2) ?></span>
      </div>
    </div>

    <div class="d-flex gap-2 no-print">
      <button type="button" onclick="window.print()" class="btn btn-dark w-100 fw-semibold">Print Bill</button>
      <a href="pos.php" class="btn btn-primary w-100 fw-semibold">New Order</a>
    </div>
  </div>
<?php else: ?>

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
    <div>
      <h3 class="mb-0 fw-bold">POS Counter</h3>
      <small class="text-muted">Item click karein, quantity set karein, aur bill complete karein</small>
    </div>
    <div>
      <?php if ($openShift): ?>
        <span class="badge text-bg-success px-3 py-2">
          Shift #<?= (int) $openShift['id'] ?> &middot; <?= htmlspecialchars($openShift['cashier_name']) ?>
        </span>
      <?php else: ?>
        <a href="shifts.php" class="badge text-bg-warning text-dark text-decoration-none px-3 py-2">
          No Open Shift &rarr; Open Shift
        </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($error !== ''): ?>
    <div class="alert alert-danger no-print"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success !== ''): ?>
    <div class="alert alert-success no-print"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <div class="row g-4 align-items-start">
    <!-- Left: Filter + Menu Grid -->
    <div class="col-lg-7 no-print">
      <div class="pos-card p-3 mb-3">
        <form method="get" class="row g-2">
          <div class="col-md-6">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Search item name or SKU...">
          </div>
          <div class="col-md-4">
            <select name="cat" class="form-select">
              <option value="">All Categories</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>" <?= $selectedCat === $cat ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cat) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-dark w-100">Go</button>
            <?php if ($search !== '' || $selectedCat !== ''): ?>
              <a href="pos.php" class="btn btn-outline-secondary" title="Reset">&times;</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div class="row g-2">
        <?php if (!$menuItems): ?>
          <div class="col-12">
            <div class="pos-card p-4 text-center text-muted">
              Koi menu item nahi mila. <a href="menu_import.php">Menu add ya CSV import karein</a>.
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($menuItems as $m): ?>
            <div class="col-sm-6 col-md-4">
              <form method="post" class="h-100">
                <input type="hidden" name="action" value="add_item">
                <input type="hidden" name="item_id" value="<?= (int) $m['id'] ?>">
                <button type="submit" class="menu-tile w-100 h-100 text-start p-3 d-flex flex-column justify-content-between">
                  <div>
                    <span class="badge text-bg-light border mb-2"><?= htmlspecialchars($m['category']) ?></span>
                    <div class="fw-bold text-dark"><?= htmlspecialchars($m['name']) ?></div>
                  </div>
                  <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-primary num-col"><?= number_format((float) $m['price'], 2) ?></span>
                    <span class="small text-muted">+ Add</span>
                  </div>
                </button>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right: Current Bill Cart -->
    <div class="col-lg-5">
      <div class="pos-card p-4">
        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3 no-print">
          <h5 class="fw-bold mb-0">Current Bill</h5>
          <?php if (!empty($_SESSION['pos_cart'])): ?>
            <form method="post" class="d-inline">
              <input type="hidden" name="action" value="clear_cart">
              <button type="submit" class="btn btn-outline-danger btn-sm">Clear Cart</button>
            </form>
          <?php endif; ?>
        </div>

        <?php if (empty($_SESSION['pos_cart'])): ?>
          <div class="text-center text-muted py-5">
            <div class="fw-semibold mb-1">Bill khaali hai</div>
            <div class="small">Left side se menu items click karke add karein.</div>
          </div>
        <?php else: ?>
          <div class="table-responsive mb-3">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Item</th>
                  <th class="text-center">Qty</th>
                  <th class="text-end">Line Total</th>
                  <th class="no-print"></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($_SESSION['pos_cart'] as $row): ?>
                  <?php $line = ((float) $row['price']) * ((int) $row['qty']); ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= htmlspecialchars($row['name']) ?></div>
                      <div class="small text-muted num-col"><?= number_format((float) $row['price'], 2) ?> each</div>
                    </td>
                    <td class="text-center">
                      <div class="d-inline-flex align-items-center gap-1 no-print">
                        <form method="post" class="d-inline">
                          <input type="hidden" name="action" value="dec_qty">
                          <input type="hidden" name="item_id" value="<?= (int) $row['item_id'] ?>">
                          <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-2">-</button>
                        </form>
                        <span class="px-2 fw-semibold num-col"><?= (int) $row['qty'] ?></span>
                        <form method="post" class="d-inline">
                          <input type="hidden" name="action" value="inc_qty">
                          <input type="hidden" name="item_id" value="<?= (int) $row['item_id'] ?>">
                          <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-2">+</button>
                        </form>
                      </div>
                    </td>
                    <td class="text-end fw-semibold num-col"><?= number_format($line, 2) ?></td>
                    <td class="text-end no-print">
                      <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="remove_item">
                        <input type="hidden" name="item_id" value="<?= (int) $row['item_id'] ?>">
                        <button type="submit" class="btn btn-close btn-sm" aria-label="Remove"></button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="bg-light rounded p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center">
              <span class="fw-bold fs-6">Grand Total</span>
              <span class="fw-bold fs-4 text-success num-col"><?= number_format($cartTotal, 2) ?></span>
            </div>
          </div>

          <form method="post" class="no-print">
            <input type="hidden" name="action" value="checkout">
            <div class="mb-3">
              <label class="form-label fw-semibold">Payment Method</label>
              <select name="payment_method" class="form-select">
                <option value="CASH">CASH</option>
                <option value="CARD">CARD</option>
                <option value="ONLINE">ONLINE</option>
              </select>
            </div>
            <button type="submit" class="btn btn-success w-100 py-2 fw-bold fs-6">Complete &amp; Print Bill</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>