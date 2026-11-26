<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$isLoggedIn = !empty($_SESSION['user_id']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Simple POS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    @media print {
      .no-print { display: none !important; }
      body { background: #fff !important; }
    }
  </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 no-print">
  <div class="container">
    <a class="navbar-brand" href="index.php">Simple POS</a>
    <?php if ($isLoggedIn): ?>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#posNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="posNav">
        <ul class="navbar-nav me-auto">
          <li class="nav-item"><a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="index.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link <?= $currentPage === 'shifts.php' ? 'active' : '' ?>" href="shifts.php">Shifts</a></li>
          <li class="nav-item"><a class="nav-link <?= $currentPage === 'menu_import.php' ? 'active' : '' ?>" href="menu_import.php">Menu CSV</a></li>
          <li class="nav-item"><a class="nav-link <?= $currentPage === 'pos.php' ? 'active' : '' ?>" href="pos.php">POS</a></li>
          <li class="nav-item"><a class="nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>" href="reports.php">Reports</a></li>
        </ul>
        <span class="navbar-text text-light me-3">
          <?= htmlspecialchars((string) ($_SESSION['user_name'] ?? 'User')) ?>
        </span>
        <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
      </div>
    <?php endif; ?>
  </div>
</nav>
<div class="container mb-5">