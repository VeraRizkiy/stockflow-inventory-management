<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$pageTitle = $pageTitle ?? 'Dashboard';
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> | StockFlow</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app-shell">
<aside class="sidebar">
  <a class="brand" href="index.php"><span class="brand-mark">S</span><span>Stock<span class="brand-light">Flow</span><small>OPERATIONS SYSTEM</small></span></a>
  <div class="nav-label">WORKSPACE</div>
  <nav>
    <a class="<?= $current==='index.php'?'active':'' ?>" href="index.php"><span>▦</span> Dashboard</a>
    <a class="<?= $current==='inventory.php'?'active':'' ?>" href="inventory.php"><span>▤</span> Inventory</a>
    <a class="<?= $current==='suppliers.php'?'active':'' ?>" href="suppliers.php"><span>♙</span> Suppliers</a>
    <a class="<?= $current==='customers.php'?'active':'' ?>" href="customers.php"><span>♙</span> Customers</a>
    <a class="<?= $current==='sales_orders.php'?'active':'' ?>" href="sales_orders.php"><span>▤</span> Sales Orders</a>
    <a class="<?= $current==='purchase_orders.php'?'active':'' ?>" href="purchase_orders.php"><span>▣</span> Purchase Orders</a>
    <a class="<?= $current==='transactions.php'?'active':'' ?>" href="transactions.php"><span>⇄</span> Stock Transactions</a>
    <a class="<?= $current==='production.php'?'active':'' ?>" href="production.php"><span>⚙</span> Production</a>
    <a class="<?= $current==='reports.php'?'active':'' ?>" href="reports.php"><span>▥</span> Reports</a>
  </nav>
  <div class="sidebar-bottom"><div class="user-mini"><div class="avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'A',0,1)) ?></div><div><b><?= htmlspecialchars($_SESSION['name'] ?? 'Administrator') ?></b><small>Administrator</small></div></div><a class="logout" href="logout.php">↗ Keluar</a></div>
</aside>
<main class="main-area">
<header class="topbar"><div><span class="eyebrow">OPERATIONS / <?= strtoupper(str_replace('.php','',$current)) ?></span><h1><?= htmlspecialchars($pageTitle) ?></h1></div><div class="topbar-right"><span class="status-dot"></span><span>System Online</span><div class="top-avatar"><?= strtoupper(substr($_SESSION['name'] ?? 'A',0,1)) ?></div></div></header>
<div class="content">
