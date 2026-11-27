<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

$error = '';
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

// --- Handle POST (update / delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($id <= 0) {
        header('Location: menu_import.php');
        exit;
    }

    if ($action === 'delete') {
        // Soft delete: hide from menu/POS; keep order history intact.
        $stmt = $pdo->prepare('UPDATE menu_items SET is_active = 0 WHERE id = ?');
        $stmt->execute([$id]);
        header('Location: menu_import.php?msg=deleted');
        exit;
    }

    if ($action === 'update') {
        $sku      = trim((string) ($_POST['sku'] ?? ''));
        $name     = trim((string) ($_POST['name'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $priceRaw = trim((string) ($_POST['price'] ?? ''));

        if ($name === '') {
            $error = 'Item name zaroori hai.';
        } elseif (!is_numeric($priceRaw) || (float) $priceRaw < 0) {
            $error = 'Valid price enter karein.';
        } else {
            $stmt = $pdo->prepare(
                'UPDATE menu_items SET sku = ?, name = ?, category = ?, price = ? WHERE id = ? AND is_active = 1'
            );
            $stmt->execute([
                $sku !== '' ? $sku : null,
                $name,
                $category !== '' ? $category : 'General',
                round((float) $priceRaw, 2),
                $id,
            ]);
            header('Location: menu_import.php?msg=updated');
            exit;
        }
    }
}

// --- Load item ---
if ($id <= 0) { header('Location: menu_import.php'); exit; }
$stmt = $pdo->prepare('SELECT id, sku, name, category, price FROM menu_items WHERE id = ? AND is_active = 1 LIMIT 1');
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) { header('Location: menu_import.php'); exit; }

require_once __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0 page-heading"><i class="bi bi-pencil-square me-2"></i>Edit Menu Item</h3>
  <a href="menu_import.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Menu</a>
</div>

<?php if ($error !== ''): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card card-body" style="max-width:640px;">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">

    <div class="row g-2">
      <div class="col-md-4">
        <label class="form-label">SKU (Optional)</label>
        <input type="text" name="sku" class="form-control" value="<?= htmlspecialchars((string) ($item['sku'] ?? '')) ?>" placeholder="D01">
      </div>
      <div class="col-md-8">
        <label class="form-label">Item Name</label>
        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($item['name']) ?>" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Category</label>
        <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($item['category']) ?>" placeholder="Drinks">
      </div>
      <div class="col-md-6">
        <label class="form-label">Price</label>
        <input type="number" step="0.01" min="0" name="price" class="form-control" value="<?= number_format((float) $item['price'], 2, '.', '') ?>" required>
      </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3">
      <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Changes</button>
    </div>
  </form>

  <hr>

  <form method="post" onsubmit="return confirm('Is item ko remove karna chahte hain? (Order history safe rahega)');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash3"></i> Remove Item</button>
    <span class="text-muted small ms-2">Item menu se hat jayega; purane orders safe rahenge.</span>
  </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
