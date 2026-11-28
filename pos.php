<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

if (!isset($_SESSION['pos_cart']) || !is_array($_SESSION['pos_cart'])) {
    $_SESSION['pos_cart'] = [];
}

function generateOrderNo(PDO $pdo): string
{
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $no = 'ORD-' . date('Ymd-His') . '-' . random_int(1000, 9999);
        $st = $pdo->prepare('SELECT 1 FROM orders WHERE order_no = ? LIMIT 1');
        $st->execute([$no]);
        if (!$st->fetchColumn()) {
            return $no;
        }
    }
    return 'ORD-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));
}

$error = '';
$success = '';
$printedOrder = null;
$printedItems = [];

$openShiftStmt = $pdo->query("SELECT id, cashier_name FROM shifts WHERE status = 'OPEN' ORDER BY id DESC LIMIT 1");
$openShift = $openShiftStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
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
        } elseif (!$openShift || (int) ($_POST['shift_id'] ?? 0) !== (int) $openShift['id']) {
            $error = 'Active shift check karke checkout dobara karein.';
        } else {
            $paymentMethod = $_POST['payment_method'] ?? '';
            $shiftId = (int) $openShift['id'];

            try {
                if (!in_array($paymentMethod, ['CASH', 'CARD', 'ONLINE'], true)) {
                    throw new DomainException('Valid payment method select karein.');
                }
                $pdo->beginTransaction();
                $shiftStmt = $pdo->prepare("SELECT id FROM shifts WHERE id = ? AND status = 'OPEN' FOR UPDATE");
                $shiftStmt->execute([$shiftId]);
                if (!$shiftStmt->fetchColumn()) {
                    throw new DomainException('Shift close ho gayi hai. Checkout dobara karein.');
                }
                // Re-validate every cart line against the DB (ignore session price/name).
                $orderItems = [];
                $totalAmount = 0.0;
                $menuStmt = $pdo->prepare('SELECT id, name, price FROM menu_items WHERE id = ? AND is_active = 1 LIMIT 1');

                foreach ($_SESSION['pos_cart'] as $row) {
                    $itemId = (int) ($row['item_id'] ?? 0);
                    $qty = (int) ($row['qty'] ?? 0);
                    if ($itemId <= 0 || $qty < 1) {
                        throw new RuntimeException('Invalid cart item.');
                    }
                    $menuStmt->execute([$itemId]);
                    $dbItem = $menuStmt->fetch();
                    if (!$dbItem) {
                        throw new DomainException('Cart mein ek item ab available nahi hai. Usay remove karke dobara checkout karein.');
                    }
                    $unitPrice = round((float) $dbItem['price'], 2);
                    $lineTotal = round($unitPrice * $qty, 2);
                    $totalAmount += $lineTotal;
                    $orderItems[] = [
                        'item_id'    => $itemId,
                        'item_name'  => (string) $dbItem['name'],
                        'qty'        => $qty,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                    ];
                }
                $totalAmount = round($totalAmount, 2);

                if (empty($orderItems)) {
                    throw new RuntimeException('Empty cart.');
                }

                $orderNo = generateOrderNo($pdo);

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

                foreach ($orderItems as $oi) {
                    $itemStmt->execute([
                        $orderId,
                        $oi['item_id'],
                        $oi['item_name'],
                        $oi['qty'],
                        $oi['unit_price'],
                        $oi['line_total'],
                    ]);
                }

                $pdo->commit();
                $_SESSION['pos_cart'] = [];
                header('Location: pos.php?receipt=' . $orderId);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e instanceof DomainException ? $e->getMessage() : 'Order save karne mein error aya.';
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
      <h5 class="fw-bold mb-1"><i class="bi bi-receipt me-1"></i>POS RECEIPT</h5>
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
      <button type="button" onclick="window.print()" class="btn btn-dark w-100 fw-semibold"><i class="bi bi-printer"></i> Print Bill</button>
      <a href="pos.php" class="btn btn-primary w-100 fw-semibold"><i class="bi bi-plus-circle"></i> New Order</a>
    </div>
  </div>
<?php else: ?>

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
    <div>
      <h3 class="mb-0 page-heading"><i class="bi bi-cart3 me-2"></i>POS Counter</h3>
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
    <div class="col-lg-7 no-print pos-menu">
      <div class="pos-card p-3 mb-3 pos-filter">
        <form method="get" class="row g-2">
          <div class="col-md-6">
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Search item name or SKU...">
            </div>
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
              <a href="pos.php" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div class="row g-2">
        <?php if (!$menuItems): ?>
          <div class="col-12">
            <div class="pos-card empty-state">
              <i class="bi bi-search"></i>
              Koi menu item nahi mila. <a href="menu_import.php">Menu add ya CSV import karein</a>.
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($menuItems as $m): ?>
            <div class="col-sm-6 col-md-4">
              <form method="post" class="h-100">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_item">
                <input type="hidden" name="item_id" value="<?= (int) $m['id'] ?>">
                <button type="submit" class="menu-tile w-100 h-100 text-start p-3 d-flex flex-column justify-content-between">
                  <div>
                    <span class="badge text-bg-light border mb-2"><?= htmlspecialchars($m['category']) ?></span>
                    <div class="fw-bold text-dark"><?= htmlspecialchars($m['name']) ?></div>
                  </div>
                  <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
                    <span class="tile-price num-col"><?= number_format((float) $m['price'], 2) ?></span>
                    <span class="tile-add"><i class="bi bi-plus-circle"></i> Add</span>
                  </div>
                </button>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right: Current Bill Cart -->
    <div class="col-lg-5 pos-cart">
      <div class="pos-card p-4">
        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3 no-print">
          <h5 class="fw-bold mb-0"><i class="bi bi-cart3 me-1"></i>Current Bill</h5>
          <?php if (!empty($_SESSION['pos_cart'])): ?>
            <form method="post" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="clear_cart">
              <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash3"></i> Clear Cart</button>
            </form>
          <?php endif; ?>
        </div>

        <?php if (empty($_SESSION['pos_cart'])): ?>
          <div class="empty-state py-5">
            <i class="bi bi-cart-x"></i>
            <div class="fw-semibold mb-1">Bill khaali hai</div>
            <div class="small">Left side se menu items click karke add karein.</div>
          </div>
        <?php else: ?>
          <div class="table-responsive mb-3">
            <table class="table table-sm align-middle mb-0 cart-table">
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
                      <div class="qty-control no-print">
                        <form method="post" class="d-inline">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="dec_qty">
                          <input type="hidden" name="item_id" value="<?= (int) $row['item_id'] ?>">
                          <button type="submit" class="btn btn-outline-secondary qty-btn">-</button>
                        </form>
                        <span class="px-1 fw-semibold num-col"><?= (int) $row['qty'] ?></span>
                        <form method="post" class="d-inline">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="inc_qty">
                          <input type="hidden" name="item_id" value="<?= (int) $row['item_id'] ?>">
                          <button type="submit" class="btn btn-outline-secondary qty-btn">+</button>
                        </form>
                      </div>
                    </td>
                    <td class="text-end fw-semibold num-col"><?= number_format($line, 2) ?></td>
                    <td class="text-end no-print">
                      <form method="post" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove_item">
                        <input type="hidden" name="item_id" value="<?= (int) $row['item_id'] ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm qty-btn" aria-label="Remove"><i class="bi bi-x-lg"></i></button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="total-bar mb-3">
            <div class="d-flex justify-content-between align-items-center">
              <span class="total-label">Grand Total</span>
              <span class="total-value num-col"><?= number_format($cartTotal, 2) ?></span>
            </div>
          </div>

          <?php if (!$openShift): ?>
            <a href="shifts.php" class="btn btn-primary w-100 no-print">Open a Shift to Check Out</a>
          <?php else: ?>
          <form method="post" class="no-print">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="checkout">
            <input type="hidden" name="shift_id" value="<?= (int) $openShift['id'] ?>">
            <div class="mb-3">
              <label class="form-label fw-semibold">Payment Method</label>
              <select name="payment_method" class="form-select">
                <option value="CASH">CASH</option>
                <option value="CARD">CARD</option>
                <option value="ONLINE">ONLINE</option>
              </select>
            </div>
            <button type="submit" class="btn btn-success w-100 py-2 fw-bold fs-6"><i class="bi bi-printer"></i> Complete &amp; Print Bill</button>
          </form>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
