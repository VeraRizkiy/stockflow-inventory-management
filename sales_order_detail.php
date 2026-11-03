<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: sales_orders.php');
    exit;
}

$orderId = (int) $_GET['id'];

// Ambil data Sales Order dan Customer
$stmt = $pdo->prepare("
    SELECT 
        so.*,
        c.customer_name,
        c.contact_person,
        c.phone,
        c.email,
        c.address
    FROM sales_orders so
    JOIN customers c ON so.customer_id = c.id
    WHERE so.id = ?
");

$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: sales_orders.php');
    exit;
}

// Ambil daftar produk dalam Sales Order
$stmt = $pdo->prepare("
    SELECT 
        soi.*,
        i.name AS item_name,
        i.sku,
        i.unit
    FROM sales_order_items soi
    JOIN items i ON soi.item_id = i.id
    WHERE soi.sales_order_id = ?
");

$stmt->execute([$orderId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung total pesanan
$total = 0;

foreach ($items as $item) {
    $total += $item['quantity'] * $item['unit_price'];
}

$pageTitle = 'Sales Order Detail';

require_once 'includes/header.php';
?>

<style>
.sales-detail {
    max-width: 1100px;
    margin: 0 auto;
}

.detail-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.detail-btn {
    display: inline-block;
    padding: 10px 16px;
    border-radius: 8px;
    border: none;
    background: #344c78;
    color: white;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}

.detail-btn:hover {
    background: #263957;
    color: white;
}

.detail-card {
    background: white;
    border-radius: 14px;
    padding: 28px;
    margin-bottom: 20px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
}

.detail-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding-bottom: 24px;
    border-bottom: 1px solid #eee;
    margin-bottom: 24px;
}

.detail-header h2 {
    margin: 0 0 8px;
    color: #263957;
}

.detail-header p {
    margin: 0;
    color: #888;
    font-size: 13px;
}

.so-badge {
    padding: 7px 13px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    background: #edf2fa;
    color: #344c78;
}

.detail-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 28px;
    margin-bottom: 28px;
}

.detail-info h3 {
    font-size: 15px;
    color: #263957;
    margin-bottom: 16px;
}

.detail-info p {
    font-size: 13px;
    color: #666;
    margin: 8px 0;
    line-height: 1.6;
}

.detail-info strong {
    color: #333;
}

.detail-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.detail-table th {
    background: #f7f8fa;
    color: #555;
    font-size: 12px;
    text-align: left;
    padding: 14px;
}

.detail-table td {
    padding: 14px;
    border-bottom: 1px solid #eee;
    font-size: 13px;
    color: #555;
}

.detail-total {
    display: flex;
    justify-content: flex-end;
    margin-top: 24px;
}

.total-box {
    min-width: 250px;
    padding: 18px;
    background: #f7f8fa;
    border-radius: 10px;
}

.total-box p {
    margin: 0;
    color: #777;
    font-size: 13px;
}

.total-box h2 {
    margin: 8px 0 0;
    color: #263957;
}

@media print {
    .sidebar,
    .topbar,
    .detail-actions {
        display: none !important;
    }

    .main-content {
        margin: 0 !important;
        padding: 0 !important;
    }

    .detail-card {
        box-shadow: none;
        border: 1px solid #eee;
    }

    body {
        background: white;
    }
}

@media (max-width: 700px) {
    .detail-info-grid {
        grid-template-columns: 1fr;
    }

    .detail-card {
        padding: 18px;
        overflow-x: auto;
    }

    .detail-header {
        gap: 15px;
        flex-direction: column;
    }
}
</style>

<div class="sales-detail">

    <div class="detail-actions">
        <a href="sales_orders.php" class="detail-btn">
            ← Back to Sales Orders
        </a>

        <button onclick="window.print()" class="detail-btn">
            Print Sales Order
        </button>
    </div>

    <div class="detail-card">

        <div class="detail-header">
            <div>
                <h2>Sales Order</h2>
                <p>Order Number: <?= htmlspecialchars($order['so_number']) ?></p>
            </div>

            <span class="so-badge">
                <?= htmlspecialchars($order['status']) ?>
            </span>
        </div>

        <div class="detail-info-grid">

            <div class="detail-info">
                <h3>Order Information</h3>

                <p>
                    <strong>SO Number:</strong>
                    <?= htmlspecialchars($order['so_number']) ?>
                </p>

                <p>
                    <strong>Order Date:</strong>
                    <?= date('d M Y', strtotime($order['order_date'])) ?>
                </p>

                <p>
                    <strong>Delivery Date:</strong>
                    <?= !empty($order['delivery_date'])
                        ? date('d M Y', strtotime($order['delivery_date']))
                        : '-' ?>
                </p>

                <p>
                    <strong>Status:</strong>
                    <?= htmlspecialchars($order['status']) ?>
                </p>
            </div>

            <div class="detail-info">
                <h3>Customer Information</h3>

                <p>
                    <strong>Customer:</strong>
                    <?= htmlspecialchars($order['customer_name']) ?>
                </p>

                <p>
                    <strong>Contact Person:</strong>
                    <?= htmlspecialchars($order['contact_person'] ?? '-') ?>
                </p>

                <p>
                    <strong>Phone:</strong>
                    <?= htmlspecialchars($order['phone'] ?? '-') ?>
                </p>

                <p>
                    <strong>Email:</strong>
                    <?= htmlspecialchars($order['email'] ?? '-') ?>
                </p>

                <p>
                    <strong>Address:</strong>
                    <?= nl2br(htmlspecialchars($order['address'] ?? '-')) ?>
                </p>
            </div>

        </div>

        <h3>Ordered Products</h3>

        <table class="detail-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Subtotal</th>
                </tr>
            </thead>

            <tbody>
                <?php if (count($items) > 0): ?>
                    <?php foreach ($items as $index => $item): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>

                            <td>
                                <?= htmlspecialchars($item['item_name']) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($item['sku']) ?>
                            </td>

                            <td>
                                <?= number_format($item['quantity']) ?>
                                <?= htmlspecialchars($item['unit']) ?>
                            </td>

                            <td>
                                Rp <?= number_format($item['unit_price'], 0, ',', '.') ?>
                            </td>

                            <td>
                                Rp <?= number_format(
                                    $item['quantity'] * $item['unit_price'],
                                    0,
                                    ',',
                                    '.'
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center;">
                            No products found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="detail-total">
            <div class="total-box">
                <p>Total Order Amount</p>
                <h2>
                    Rp <?= number_format($total, 0, ',', '.') ?>
                </h2>
            </div>
        </div>

        <?php if (!empty($order['notes'])): ?>
            <div class="detail-info" style="margin-top:28px;">
                <h3>Notes</h3>
                <p><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>