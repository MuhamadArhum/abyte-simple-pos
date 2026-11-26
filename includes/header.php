<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$isLoggedIn = !empty($_SESSION['user_id']);
$cartCount = 0;
if (!empty($_SESSION['pos_cart']) && is_array($_SESSION['pos_cart'])) {
    foreach ($_SESSION['pos_cart'] as $item) {
        $cartCount += (int) ($item['qty'] ?? 0);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Simple POS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --pos-bg: #f4f6f9;
      --pos-surface: #ffffff;
      --pos-border: #e2e8f0;
    }
    body {
      background-color: var(--pos-bg);
      color: #0f172a;
      font-size: 0.95rem;
    }
    .navbar {
      box-shadow: 0 2px 10px rgba(15, 23, 42, 0.08);
    }
    .pos-card {
      background: var(--pos-surface);
      border: 1px solid var(--pos-border);
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    }
    .num-col {
      font-variant-numeric: tabular-nums;
    }
    .menu-tile {
      border: 1px solid var(--pos-border);
      border-radius: 10px;
      background: var(--pos-surface);
      transition: transform 0.1s ease, border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .menu-tile:hover {
      border-color: #0d6efd;
      box-shadow: 0 4px 12px rgba(13, 110, 253, 0.12);
      transform: translateY(-1px);
    }
    .menu-tile:active {
      transform: translateY(0);
    }
    .receipt-box {
      max-width: 400px;
      margin: 0 auto;
      background: #fff;
      border: 1px dashed #94a3b8;
      border-radius: 10px;
    }
    @media print {
      .no-print { display: none !important; }
      body { background: #fff !important; color: #000 !important; }
      .receipt-box { border: none !important; box-shadow: none !important; max-width: 100% !important; }
      .pos-card { border: 1px solid #cbd5e1 !important; box-shadow: none !important; }
    }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-2 mb-4 no-print">
  <div class="container">
    <a class="navbar-brand fw-bold tracking-tight" href="index.php">Simple POS</a>
    <?php if ($isLoggedIn): ?>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#posNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="posNav">
        <ul class="navbar-nav me-auto gap-1">
          <li class="nav-item">
            <a class="nav-link px-3 rounded <?= $currentPage === 'index.php' ? 'active bg-primary' : '' ?>" href="index.php">Dashboard</a>
          </li>
          <li class="nav-item">
            <a class="nav-link px-3 rounded <?= $currentPage === 'shifts.php' ? 'active bg-primary' : '' ?>" href="shifts.php">Shifts</a>
          </li>
          <li class="nav-item">
            <a class="nav-link px-3 rounded <?= $currentPage === 'menu_import.php' ? 'active bg-primary' : '' ?>" href="menu_import.php">Menu</a>
          </li>
          <li class="nav-item">
            <a class="nav-link px-3 rounded <?= $currentPage === 'pos.php' ? 'active bg-success' : '' ?>" href="pos.php">
              POS
              <?php if ($cartCount > 0): ?>
                <span class="badge bg-warning text-dark ms-1 num-col"><?= $cartCount ?></span>
              <?php endif; ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link px-3 rounded <?= $currentPage === 'reports.php' ? 'active bg-primary' : '' ?>" href="reports.php">Reports</a>
          </li>
        </ul>
        <div class="d-flex align-items-center gap-2 mt-2 mt-lg-0">
          <span class="badge text-bg-secondary py-2 px-3">
            <?= htmlspecialchars((string) ($_SESSION['user_name'] ?? 'User')) ?>
          </span>
          <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</nav>
<div class="container mb-5">