<?php
require 'includes/auth.php'; require 'config/database.php';
$pageTitle='Stock Transactions'; $message='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $id=(int)($_POST['item_id']??0);$type=$_POST['type']??'IN';$qty=(int)($_POST['quantity']??0);$note=trim($_POST['note']??'');
 if($id>0 && $qty>0 && in_array($type,['IN','OUT'],true)){
  try{$pdo->beginTransaction();$s=$pdo->prepare("SELECT stock FROM items WHERE id=? FOR UPDATE");$s->execute([$id]);$item=$s->fetch();
   if(!$item) throw new Exception('Item tidak ditemukan.');
   if($type==='OUT' && $item['stock']<$qty) throw new Exception('Stok tidak mencukupi untuk transaksi keluar.');
   $delta=$type==='IN'?$qty:-$qty;$pdo->prepare("UPDATE items SET stock=stock+? WHERE id=?")->execute([$delta,$id]);
   $pdo->prepare("INSERT INTO transactions(item_id,type,quantity,note,user_id) VALUES(?,?,?,?,?)")->execute([$id,$type,$qty,$note,$_SESSION['user_id']]);
   $pdo->commit();$message='Transaksi berhasil disimpan.';
  }catch(Exception $e){if($pdo->inTransaction())$pdo->rollBack();$message=$e->getMessage();}
 }
}
$items=$pdo->query("SELECT id,name,sku,stock,unit FROM items ORDER BY name")->fetchAll();
$rows=$pdo->query("SELECT t.*,i.name item_name,i.sku FROM transactions t JOIN items i ON i.id=t.item_id ORDER BY t.created_at DESC LIMIT 100")->fetchAll();
require 'includes/header.php';
?>
<?php if($message): ?><div class="alert info"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<div class="page-intro"><div><h2>Record stock movement</h2><p class="muted">Setiap transaksi akan memperbarui jumlah stok secara otomatis.</p></div></div>
<div class="panel form-panel"><form method="post" class="inline-form transaction-form"><label>Item<select name="item_id" required><option value="">Pilih item</option><?php foreach($items as $i): ?><option value="<?= $i['id'] ?>"><?= htmlspecialchars($i['name'].' — '.$i['sku'].' (stok: '.$i['stock'].' '.$i['unit'].')') ?></option><?php endforeach; ?></select></label><label>Tipe transaksi<select name="type"><option value="IN">Stock In (+)</option><option value="OUT">Stock Out (−)</option></select></label><label>Jumlah<input type="number" name="quantity" min="1" required></label><label>Catatan<input name="note" placeholder="Keterangan transaksi"></label><button class="btn primary">Simpan Transaksi</button></form></div>
<div class="panel"><div class="panel-head"><div><h3>Transaction history</h3><p>100 transaksi terbaru</p></div></div><div class="table-wrap"><table><thead><tr><th>Tanggal</th><th>Item</th><th>Tipe</th><th>Jumlah</th><th>Catatan</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= date('d M Y H:i',strtotime($r['created_at'])) ?></td><td><b><?= htmlspecialchars($r['item_name']) ?></b><small class="subcell"><?= htmlspecialchars($r['sku']) ?></small></td><td><span class="badge <?= $r['type']==='IN'?'success':'neutral' ?>"><?= $r['type']==='IN'?'Stock In':'Stock Out' ?></span></td><td><?= number_format($r['quantity']) ?></td><td><?= htmlspecialchars($r['note']?:'—') ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php require 'includes/footer.php'; ?>
