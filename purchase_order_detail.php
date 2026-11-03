<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

$pageTitle = 'Purchase Order Detail';

$po_id = (int) ($_GET['id'] ?? 0);

if ($po_id <= 0) {
    die('Purchase Order tidak valid.');
}

/* GET PURCHASE ORDER */
$stmt = $pdo->prepare("
    SELECT
        po.*,
        s.supplier_name,
        s.contact_person,
        s.phone,
        s.email,
        s.address
    FROM purchase_orders po
    JOIN suppliers s ON s.id = po.supplier_id
    WHERE po.id = ?
");

$stmt->execute([$po_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die('Purchase Order tidak ditemukan.');
}

/* GET ORDER ITEMS */
$stmt = $pdo->prepare("
    SELECT
        poi.quantity,
        poi.unit_price,
        i.name AS item_name,
        i.sku,
        i.unit
    FROM purchase_order_items poi
    JOIN items i ON i.id = poi.item_id
    WHERE poi.purchase_order_id = ?
");

$stmt->execute([$po_id]);
$orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grandTotal = 0;

foreach ($orderItems as $item) {
    $grandTotal += $item['quantity'] * $item['unit_price'];
}

$badge = 'po-draft';

if ($order['status'] === 'Ordered') $badge = 'po-ordered';
if ($order['status'] === 'Received') $badge = 'po-received';
if ($order['status'] === 'Cancelled') $badge = 'po-cancelled';

require_once 'includes/header.php';
?>

<style>
.detail-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
}

.detail-btn {
    display: inline-block;
    padding: 10px 16px;
    border-radius: 8px;
    background: #202c45;
    color: white;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.detail-card {
    background: white;
    border: 1px solid #e8ebf0;
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 20px;
}

.detail-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding-bottom: 22px;
    border-bottom: 1px solid #edf0f3;
    margin-bottom: 22px;
}

.detail-header h2 {
    margin: 0 0 8px;
}

.detail-muted {
    color: #7b8494;
    font-size: 13px;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
}

.detail-info h4 {
    font-size: 12px;
    color: #7b8494;
    text-transform: uppercase;
    margin: 0 0 12px;
}

.detail-info p {
    margin: 6px 0;
    font-size: 13px;
}

.po-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.po-draft { background: #f1f2f4; color: #596273; }
.po-ordered { background: #fff3d9; color: #a66b00; }
.po-received { background: #e3f7ec; color: #218653; }
.po-cancelled { background: #ffe8e8; color: #c0392b; }

.detail-table {
    width: 100%;
    border-collapse: collapse;
}

.detail-table th,
.detail-table td {
    padding: 15px 12px;
    border-bottom: 1px solid #edf0f3;
    text-align: left;
    font-size: 13px;
}

.detail-table th {
    color: #7b8494;
    font-size: 11px;
    text-transform: uppercase;
}

.detail-total {
    display: flex;
    justify-content: flex-end;
    padding-top: 22px;
}

.detail-total div {
    min-width: 220px;
    text-align: right;
}

.detail-total span {
    display: block;
    color: #7b8494;
    font-size: 12px;
    margin-bottom: 7px;
}

.detail-total strong {
    font-size: 23px;
}

.detail-notes {
    background: #f8f9fb;
    border-radius: 9px;
    padding: 15px;
    font-size: 13px;
    margin-top: 20px;
}

@media(max-width: 700px) {
    .detail-grid {
        grid-template-columns: 1fr;
    }

    .detail-header {
        flex-direction: column;
        gap: 12px;
    }

    .detail-table-wrapper {
        overflow-x: auto;
    }
}
</style>

<div class="detail-actions">
    <a href="purchase_orders.php" class="detail-btn">
        ← Back to Purchase Orders
    </a>

    <button onclick="window.print()" class="detail-btn">
        Print Purchase Order
    </button>
</div>

<div class="detail-card">

    <div class="detail-header">
        <div>
            <h2>Purchase Order</h2>
            <div class="detail-muted">
                <?= htmlspecialchars($order['po_number']) ?>
            </div>
        </div>

        <span class="po-badge <?= $badge ?>">
            <?= htmlspecialchars($order['status']) ?>
        </span>
    </div>

    <div class="detail-grid">

        <div class="detail-info">
            <h4>Supplier Information</h4>

            <p><strong><?= htmlspecialchars($order['supplier_name']) ?></strong></p>

            <p>Contact: <?= htmlspecialchars($order['contact_person'] ?: '-') ?></p>

            <p>Phone: <?= htmlspecialchars($order['phone'] ?: '-') ?></p>

            <p>Email: <?= htmlspecialchars($order['email'] ?: '-') ?></p>

            <p>Address: <?= nl2br(htmlspecialchars($order['address'] ?: '-')) ?></p>
        </div>

        <div class="detail-info">
            <h4>Order Information</h4>

            <p>PO Number: <strong><?= htmlspecialchars($order['po_number']) ?></strong></p>

            <p>Order Date: <?= htmlspecialchars($order['order_date']) ?></p>

            <p>Expected Date: <?= htmlspecialchars($order['expected_date'] ?: '-') ?></p>

            <p>Created At: <?= htmlspecialchars($order['created_at']) ?></p>
        </div>

    </div>

</div>

<div class="detail-card">

    <h3 style="margin-top:0;">Order Items</h3>

    <div class="detail-table-wrapper">
        <table class="detail-table">

            <thead>
                <tr>
                    <th>No.</th>
                    <th>Item Name</th>
                    <th>SKU</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Subtotal</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($orderItems as $index => $item): ?>

                    <?php
                    $subtotal = $item['quantity'] * $item['unit_price'];
                    ?>

                    <tr>
                        <td><?= $index + 1 ?></td>

                        <td>
                            <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                        </td>

                        <td><?= htmlspecialchars($item['sku']) ?></td>

                        <td>
                            <?= number_format($item['quantity']) ?>
                            <?= htmlspecialchars($item['unit']) ?>
                        </td>

                        <td>
                            Rp <?= number_format($item['unit_price'], 0, ',', '.') ?>
                        </td>

                        <td>
                            Rp <?= number_format($subtotal, 0, ',', '.') ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>
    </div>

    <div class="detail-total">
        <div>
            <span>Grand Total</span>
            <strong>
                Rp <?= number_format($grandTotal, 0, ',', '.') ?>
            </strong>
        </div>
    </div>

    <?php if (!empty($order['notes'])): ?>
        <div class="detail-notes">
            <strong>Notes</strong>
            <p><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
        </div>
    <?php endif; ?>

</div>

<?php require_once 'includes/footer.php'; ?>