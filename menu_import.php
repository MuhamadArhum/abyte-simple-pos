<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_manual') {
        $sku = trim((string) ($_POST['sku'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $priceRaw = trim((string) ($_POST['price'] ?? ''));

        if ($name === '') {
            $error = 'Item name zaroori hai.';
        } elseif (!is_numeric($priceRaw) || (float) $priceRaw < 0) {
            $error = 'Valid price enter karein.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO menu_items (sku, name, category, price, is_active) VALUES (?, ?, ?, ?, 1)'
            );
            $stmt->execute([
                $sku !== '' ? $sku : null,
                $name,
                $category !== '' ? $category : 'General',
                round((float) $priceRaw, 2),
            ]);
            header('Location: menu_import.php?msg=added');
            exit;
        }
    } elseif ($action === 'import_csv' && isset($_FILES['csv_file'])) {
        if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $tmpPath = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($tmpPath, 'r');

            if ($handle === false) {
                $error = 'CSV file open nahi ho saki.';
            } else {
                $rowNum = 0;
                $imported = 0;
                $skipped = 0;

                $stmt = $pdo->prepare(
                    'INSERT INTO menu_items (sku, name, category, price, is_active) VALUES (?, ?, ?, ?, 1)'
                );

                $pdo->beginTransaction();
                try {
                    while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                        $rowNum++;

                        if ($rowNum === 1 && isset($row[0])) {
                            $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
                        }

                        $sku = trim((string) ($row[0] ?? ''));
                        $name = trim((string) ($row[1] ?? ''));
                        $category = trim((string) ($row[2] ?? ''));
                        $priceRaw = trim((string) ($row[3] ?? ''));

                        if ($sku === '' && $name === '' && $priceRaw === '') {
                            continue;
                        }

                        if ($rowNum === 1 && strtolower($name) === 'name' && strtolower($priceRaw) === 'price') {
                            continue;
                        }

                        if ($name === '' || !is_numeric($priceRaw) || (float) $priceRaw < 0) {
                            $skipped++;
                            continue;
                        }

                        $stmt->execute([
                            $sku !== '' ? $sku : null,
                            $name,
                            $category !== '' ? $category : 'General',
                            round((float) $priceRaw, 2),
                        ]);
                        $imported++;
                    }

                    $pdo->commit();
                    $success = "Imported {$imported} items" . ($skipped > 0 ? " (skipped {$skipped} invalid rows)." : '.');
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $error = 'CSV import ke dauran error aya.';
                }

                fclose($handle);
            }
        } else {
            $error = 'Please ek valid .csv file select karein.';
        }
    }
}

if (($_GET['msg'] ?? '') === 'added') {
    $success = 'Manual menu item add ho gaya hai.';
}

$items = $pdo->query(
    'SELECT id, sku, name, category, price, is_active FROM menu_items WHERE is_active = 1 ORDER BY id DESC'
)->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<h3 class="mb-3">Menu Management (CSV + Manual)</h3>

<?php if ($error !== ''): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success !== ''): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <!-- Manual Add Form -->
  <div class="col-lg-6">
    <div class="card card-body h-100">
      <h5 class="card-title">Add Single Item Manually</h5>
      <form method="post" class="row g-2">
        <input type="hidden" name="action" value="add_manual">
        <div class="col-md-4">
          <label class="form-label">SKU (Optional)</label>
          <input type="text" name="sku" class="form-control" placeholder="D01">
        </div>
        <div class="col-md-8">
          <label class="form-label">Item Name</label>
          <input type="text" name="name" class="form-control" placeholder="Cold Drink" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Category</label>
          <input type="text" name="category" class="form-control" placeholder="Drinks">
        </div>
        <div class="col-md-6">
          <label class="form-label">Price</label>
          <input type="number" step="0.01" min="0" name="price" class="form-control" placeholder="120.00" required>
        </div>
        <div class="col-12 mt-2">
          <button type="submit" class="btn btn-success w-100">Add Item</button>
        </div>
      </form>
    </div>
  </div>

  <!-- CSV Import Form -->
  <div class="col-lg-6">
    <div class="card card-body h-100">
      <h5 class="card-title">Import Menu via CSV</h5>
      <p class="text-muted small mb-2">
        Format: <code>sku,name,category,price</code> (e.g. <code>B01,Zinger Burger,Burgers,550</code>)
      </p>
      <form method="post" enctype="multipart/form-data" class="row g-2">
        <input type="hidden" name="action" value="import_csv">
        <div class="col-12">
          <label class="form-label">Select CSV File</label>
          <input type="file" name="csv_file" accept=".csv,text/csv" class="form-control" required>
        </div>
        <div class="col-12 mt-2">
          <button type="submit" class="btn btn-primary w-100">Import CSV</button>
        </div>
      </form>
    </div>
  </div>
</div>

<h5>Active Menu Items (<?= count($items) ?>)</h5>
<div class="table-responsive">
  <table class="table table-bordered table-striped bg-white">
    <thead>
      <tr>
        <th>SKU</th>
        <th>Name</th>
        <th>Category</th>
        <th>Price</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="4" class="text-center text-muted">Abhi koi menu item nahi hai.</td></tr>
      <?php else: ?>
        <?php foreach ($items as $item): ?>
          <tr>
            <td><?= htmlspecialchars((string) ($item['sku'] ?? '-')) ?></td>
            <td><?= htmlspecialchars($item['name']) ?></td>
            <td><?= htmlspecialchars($item['category']) ?></td>
            <td><?= number_format((float) $item['price'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>