<?php
// Usage: $pageTitle = 'Kitchen'; $refresh = 15; require 'include/staff_header.php';
$u = current_user();
$role = $u['role'] ?? '';
$pageTitle = $pageTitle ?? 'Staff';
$nav = [
    'kitchen.php'   => ['Kitchen',   'kitchen'],
    'cashier.php'   => ['Cashier',   'cashier'],
    'inventory.php' => ['Inventory', 'admin'],
    'reports.php'   => ['Reports',   'admin'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if (!empty($refresh)): ?><meta http-equiv="refresh" content="<?= (int)$refresh ?>"><?php endif; ?>
  <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?> Staff</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/staff.css">
</head>
<body>
<header class="topbar">
  <a class="brand" href="index.php"><span class="logo"><?= e(APP_NAME) ?></span><span class="biz">Staff</span></a>
  <?php if ($u): ?>
  <nav class="snav">
    <?php foreach ($nav as $file => [$label, $need]): ?>
      <?php if ($role === 'admin' || $role === $need): ?>
        <a href="<?= $file ?>" class="<?= basename($_SERVER['PHP_SELF']) === $file ? 'on' : '' ?>"><?= $label ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
    <span class="who"><?= e($u['name']) ?> (<?= e($u['role']) ?>)</span>
    <a href="logout.php">Logout</a>
  </nav>
  <?php endif; ?>
</header>
<main class="wrap">
<?php foreach (flash_all() as $f): ?>
  <div class="alert <?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>