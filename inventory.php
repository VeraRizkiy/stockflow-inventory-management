<?php
require 'includes/auth.php'; require 'config/database.php';
$pageTitle='Inventory'; $message='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $name=trim($_POST['name']??''); $sku=trim($_POST['sku']??''); $category=trim($_POST['category']??''); $unit=trim($_POST['unit']??'pcs'); $stock=(int)($_POST['stock']??0); $min=(int)($_POST['min_stock']??0);
 if($name && $sku && $stock>=0 && $min>=0){try{$s=$pdo->prepare("INSERT INTO items(name,sku,category,unit,stock,min_stock) VALUES(?,?,?,?,?,?)");$s->execute([$name,$sku,$category,$unit,$stock,$min]);$message='Item berhasil ditambahkan.';}catch(PDOException $e){$message='Gagal menambahkan item. Pastikan SKU belum digunakan.';}}
}
if(isset($_GET['delete'])){$s=$pdo->prepare("DELETE FROM items WHERE id=?");$s->execute([(int)$_GET['delete']]);header('Location: inventory.php');exit;}
$items=$pdo->query("SELECT * FROM items ORDER BY created_at DESC")->fetchAll();
require 'includes/header.php';
?>
<?php if($message): ?><div class="alert info"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<div class="page-intro"><div><h2>Inventory list</h2><p class="muted">Kelola data bahan baku dan barang jadi.</p></div></div>
<div class="panel form-panel"><div class="panel-head"><div><h3>Tambah item baru</h3><p>Lengkapi informasi persediaan</p></div></div><form method="post" class="inline-form">
<label>Nama item<input name="name" required placeholder="Contoh: Packaging Box"></label><label>SKU<input name="sku" required placeholder="PKG-001"></label><label>Kategori<select name="category"><option>Raw Material</option><option>Packaging</option><option>Finished Goods</option><option>Other</option></select></label><label>Unit<input name="unit" value="pcs" required></label><label>Stok awal<input type="number" name="stock" min="0" value="0" required></label><label>Min. stok<input type="number" name="min_stock" min="0" value="10" required></label><button class="btn primary">＋ Tambah Item</button></form></div>
<div class="panel"><div class="panel-head"><div><h3>All inventory</h3><p><?= count($items) ?> item terdaftar</p></div></div><div class="table-wrap"><table><thead><tr><th>Item / SKU</th><th>Kategori</th><th>Stok</th><th>Minimum</th><th>Status</th><th>Aksi</th></tr></thead><tbody><?php foreach($items as $i): ?><tr><td><b><?= htmlspecialchars($i['name']) ?></b><small class="subcell"><?= htmlspecialchars($i['sku']) ?></small></td><td><?= htmlspecialchars($i['category']) ?></td><td><?= number_format($i['stock']) ?> <?= htmlspecialchars($i['unit']) ?></td><td><?= number_format($i['min_stock']) ?></td><td><span class="badge <?= $i['stock']<=$i['min_stock']?'warning':'success' ?>"><?= $i['stock']<=$i['min_stock']?'Low Stock':'In Stock' ?></span></td><td><a class="text-link danger-text" href="?delete=<?= $i['id'] ?>" onclick="return confirm('Hapus item ini?')">Hapus</a></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php require 'includes/footer.php'; ?>
