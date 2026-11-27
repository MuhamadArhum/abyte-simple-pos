<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$isLoggedIn = !empty($_SESSION['user_id']);
$cartCount = 0;
if (!empty($_SESSION['pos_cart']) && is_array($_SESSION['pos_cart'])) {
    foreach ($_SESSION['pos_cart'] as $item) {
        $cartCount += (int) ($item['qty'] ?? 0);
    }
}

$navItems = [
    ['href' => 'index.php',       'label' => 'Dashboard', 'icon' => 'bi-speedometer2',    'activeClass' => 'bg-primary'],
    ['href' => 'shifts.php',      'label' => 'Shifts',    'icon' => 'bi-clock-history',   'activeClass' => 'bg-primary'],
    ['href' => 'menu_import.php', 'label' => 'Menu',      'icon' => 'bi-card-list',       'activeClass' => 'bg-primary'],
    ['href' => 'pos.php',         'label' => 'POS',       'icon' => 'bi-cart3',           'activeClass' => 'bg-success', 'cart' => true],
    ['href' => 'orders.php',      'label' => 'Orders',    'icon' => 'bi-receipt',         'activeClass' => 'bg-primary'],
    ['href' => 'reports.php',     'label' => 'Reports',   'icon' => 'bi-graph-up-arrow',  'activeClass' => 'bg-primary'],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#059669">
  <title>Simple POS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-2 mb-4 no-print">
  <div class="container">
    <a class="navbar-brand" href="index.php">
      <span class="brand-mark"><i class="bi bi-shop"></i></span>
      <span>Simple POS</span>
    </a>

    <?php if ($isLoggedIn): ?>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#posNav"
              aria-controls="posNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="posNav">
        <ul class="navbar-nav me-auto gap-1 mt-2 mt-lg-0">
          <?php foreach ($navItems as $item): ?>
            <?php $isActive = $currentPage === $item['href']; ?>
            <li class="nav-item">
              <a class="nav-link px-3 rounded <?= $isActive ? 'active ' . $item['activeClass'] : '' ?>"
                 href="<?= $item['href'] ?>"
                 <?= $isActive ? 'aria-current="page"' : '' ?>>
                <i class="bi <?= $item['icon'] ?>"></i>
                <span><?= htmlspecialchars($item['label']) ?></span>
                <?php if (!empty($item['cart']) && $cartCount > 0): ?>
                  <span class="badge bg-warning text-dark ms-1 num-col"><?= $cartCount ?></span>
                <?php endif; ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>

        <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
          <span class="badge user-chip py-2 px-3">
            <i class="bi bi-person-circle"></i>
            <?= htmlspecialchars((string) ($_SESSION['user_name'] ?? 'User')) ?>
          </span>
          <a href="logout.php" class="btn btn-outline-light btn-sm">
            <i class="bi bi-box-arrow-right"></i> Logout
          </a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</nav>

<div class="container mb-5">
