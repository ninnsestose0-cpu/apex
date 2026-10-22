<?php
// Usage: $pageTitle = 'Order Online'; require 'include/header.php';
$pageTitle = $pageTitle ?? 'Order Online';
$cartCount = array_sum($_SESSION['cart'] ?? []);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | <?= e(BUSINESS_NAME) ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="topbar">
  <a class="brand" href="index.php">
    <span class="logo"><?= e(APP_NAME) ?></span>
    <span class="biz"><?= e(BUSINESS_NAME) ?></span>
  </a>
  <a class="cart-pill" href="#cart">Cart (<?= (int)$cartCount ?>)</a>
</header>
<main class="wrap">
<?php foreach (flash_all() as $f): ?>
  <div class="alert <?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>