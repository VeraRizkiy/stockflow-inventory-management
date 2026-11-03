<?php
require 'includes/auth.php'; require 'config/database.php';
$pageTitle='Reports';
$summary=$pdo->query("SELECT type, SUM(quantity) total FROM transactions GROUP BY type")->fetchAll();
$in=0;$out=0;foreach($summary as $s){if($s['type']==='IN')$in=(int)$s['total'];else $out=(int)$s['total'];}
$byCategory=$pdo->query("SELECT category,COUNT(*) item_count,SUM(stock) total_stock FROM items GROUP BY category ORDER BY category")->fetchAll();
$rows=$pdo->query("SELECT t.created_at,i.name item_name,i.sku,t.type,t.quantity,t.note FROM transactions t JOIN items i ON i.id=t.item_id ORDER BY t.created_at DESC")->fetchAll();
require 'includes/header.php';
?>
<div class="page-intro"><div><h2>Inventory reports</h2><p class="muted">Ringkasan pergerakan stok dan komposisi persediaan.</p></div><button class="btn secondary" onclick="window.print()">↓ Print report</button></div>
<div class="stats-grid two-stats"><article class="stat-card"><div class="stat-top"><span>Total Stock In</span><span class="stat-icon">↙</span></div><strong><?= number_format($in) ?></strong><small>Unit masuk tercatat</small></article><article class="stat-card"><div class="stat-top"><span>Total Stock Out</span><span class="stat-icon">↗</span></div><strong><?= number_format($out) ?></strong><small>Unit keluar tercatat</small></article></div>
<div class="panel"><div class="panel-head"><div><h3>Inventory by category</h3><p>Jumlah item dan total unit per kategori</p></div></div><div class="table-wrap"><table><thead><tr><th>Kategori</th><th>Jumlah item</th><th>Total stok</th></tr></thead><tbody><?php foreach($byCategory as $r): ?><tr><td><b><?= htmlspecialchars($r['category']) ?></b></td><td><?= number_format($r['item_count']) ?></td><td><?= number_format($r['total_stock']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="panel"><div class="panel-head"><div><h3>Complete transaction log</h3><p><?= count($rows) ?> catatan transaksi</p></div></div><div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Item</th><th>SKU</th><th>Tipe</th><th>Jumlah</th><th>Catatan</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= date('d M Y H:i',strtotime($r['created_at'])) ?></td><td><?= htmlspecialchars($r['item_name']) ?></td><td><?= htmlspecialchars($r['sku']) ?></td><td><?= $r['type']==='IN'?'Stock In':'Stock Out' ?></td><td><?= number_format($r['quantity']) ?></td><td><?= htmlspecialchars($r['note']?:'—') ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php require 'includes/footer.php'; ?>
