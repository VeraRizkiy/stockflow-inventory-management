<?php
require 'includes/auth.php';
require 'config/database.php';

$pageTitle = 'Sales Order Management';

$success = '';
$error = '';


// =====================================
// FUNGSI GENERATE NOMOR SO
// =====================================

function generateSONumber($pdo) {
    do {
        $number = 'SO-' . date('Ymd-His') . '-' . random_int(100, 999);

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM sales_orders WHERE so_number = ?
        ");

        $stmt->execute([$number]);

    } while ($stmt->fetchColumn() > 0);

    return $number;
}


// =====================================
// TAMBAH SALES ORDER
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_order'])) {

    $customerId = (int)($_POST['customer_id'] ?? 0);
    $orderDate = $_POST['order_date'] ?? '';
    $deliveryDate = $_POST['delivery_date'] ?: null;
    $notes = trim($_POST['notes'] ?? '');

    $itemIds = $_POST['item_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $prices = $_POST['unit_price'] ?? [];

    if ($customerId <= 0 || !$orderDate) {
        $error = 'Please complete the required order information.';
    } elseif (empty($itemIds)) {
        $error = 'Please add at least one product.';
    } else {

        try {
            $pdo->beginTransaction();

            // Validasi customer
            $stmt = $pdo->prepare("
                SELECT id FROM customers WHERE id = ?
            ");
            $stmt->execute([$customerId]);

            if (!$stmt->fetch()) {
                throw new Exception('Selected customer was not found.');
            }

            // Buat nomor SO
            $soNumber = generateSONumber($pdo);

            // Simpan sales order
            $stmt = $pdo->prepare("
                INSERT INTO sales_orders
                (so_number, customer_id, order_date, delivery_date, status, notes)
                VALUES (?, ?, ?, ?, 'Pending', ?)
            ");

            $stmt->execute([
                $soNumber,
                $customerId,
                $orderDate,
                $deliveryDate,
                $notes
            ]);

            $orderId = $pdo->lastInsertId();

            // Simpan produk
            $insertItem = $pdo->prepare("
                INSERT INTO sales_order_items
                (sales_order_id, item_id, quantity, unit_price)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($itemIds as $index => $itemId) {

                $itemId = (int)$itemId;
                $quantity = (int)($quantities[$index] ?? 0);
                $price = (float)($prices[$index] ?? 0);

                if ($itemId <= 0 || $quantity <= 0 || $price < 0) {
                    throw new Exception('Invalid product, quantity, or price.');
                }

                // Pastikan produk tersedia
                $stmt = $pdo->prepare("
                    SELECT id FROM items WHERE id = ?
                ");
                $stmt->execute([$itemId]);

                if (!$stmt->fetch()) {
                    throw new Exception('Selected product was not found.');
                }

                $insertItem->execute([
                    $orderId,
                    $itemId,
                    $quantity,
                    $price
                ]);
            }

            $pdo->commit();

            header('Location: sales_orders.php?success=added');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = 'Unable to create sales order: ' . $e->getMessage();
        }
    }
}


// =====================================
// UPDATE STATUS SALES ORDER
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {

    $orderId = (int)($_POST['order_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';

    $allowedStatuses = [
        'Pending',
        'Processing',
        'Completed',
        'Cancelled'
    ];

    if (!in_array($newStatus, $allowedStatuses, true)) {
        $error = 'Invalid order status.';
    } else {

        try {
            $pdo->beginTransaction();

            // Kunci order agar tidak diproses bersamaan
            $stmt = $pdo->prepare("
                SELECT * FROM sales_orders
                WHERE id = ?
                FOR UPDATE
            ");

            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new Exception('Sales order not found.');
            }

            $oldStatus = $order['status'];

            if ($oldStatus === 'Cancelled' && $newStatus !== 'Cancelled') {
                throw new Exception('Cancelled orders cannot be reactivated.');
            }

            if ($oldStatus === 'Completed' && $newStatus !== 'Completed') {
                throw new Exception('Completed orders cannot be changed.');
            }

            // Jika status menjadi Completed
            if ($newStatus === 'Completed' && $oldStatus !== 'Completed') {

                $stmt = $pdo->prepare("
                    SELECT soi.item_id, soi.quantity, i.name
                    FROM sales_order_items soi
                    JOIN items i ON i.id = soi.item_id
                    WHERE soi.sales_order_id = ?
                    FOR UPDATE
                ");

                $stmt->execute([$orderId]);
                $orderItems = $stmt->fetchAll();

                if (!$orderItems) {
                    throw new Exception('This order has no products.');
                }

                foreach ($orderItems as $orderItem) {

                    $itemId = (int)$orderItem['item_id'];
                    $quantity = (int)$orderItem['quantity'];

                    // Kurangi stok hanya jika mencukupi
                    $stmt = $pdo->prepare("
                        UPDATE items
                        SET stock = stock - ?
                        WHERE id = ? AND stock >= ?
                    ");

                    $stmt->execute([
                        $quantity,
                        $itemId,
                        $quantity
                    ]);

                    if ($stmt->rowCount() !== 1) {
                        throw new Exception(
                            'Insufficient stock for product: ' .
                            $orderItem['name']
                        );
                    }

                    // Catat transaksi stok keluar
                    $stmt = $pdo->prepare("
                        INSERT INTO transactions
                        (item_id, type, quantity, note, user_id)
                        VALUES (?, 'OUT', ?, ?, ?)
                    ");

                    $stmt->execute([
                        $itemId,
                        $quantity,
                        'Sales Order ' . $order['so_number'],
                        $_SESSION['user_id'] ?? null
                    ]);
                }
            }

            // Update status
            $stmt = $pdo->prepare("
                UPDATE sales_orders
                SET status = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $newStatus,
                $orderId
            ]);

            $pdo->commit();

            header('Location: sales_orders.php?success=status');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = 'Unable to update order: ' . $e->getMessage();
        }
    }
}


// =====================================
// HAPUS SALES ORDER
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {

    $orderId = (int)($_POST['order_id'] ?? 0);

    try {
        $stmt = $pdo->prepare("
            SELECT status FROM sales_orders WHERE id = ?
        ");

        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            throw new Exception('Sales order not found.');
        }

        if ($order['status'] !== 'Pending' && $order['status'] !== 'Cancelled') {
            throw new Exception('Only Pending or Cancelled orders can be deleted.');
        }

        $stmt = $pdo->prepare("
            DELETE FROM sales_orders WHERE id = ?
        ");

        $stmt->execute([$orderId]);

        header('Location: sales_orders.php?success=deleted');
        exit;

    } catch (Throwable $e) {
        $error = 'Unable to delete order: ' . $e->getMessage();
    }
}


// =====================================
// NOTIFIKASI
// =====================================

if (isset($_GET['success'])) {

    if ($_GET['success'] === 'added') {
        $success = 'Sales order successfully created.';
    } elseif ($_GET['success'] === 'status') {
        $success = 'Sales order status successfully updated.';
    } elseif ($_GET['success'] === 'deleted') {
        $success = 'Sales order successfully deleted.';
    }
}


// =====================================
// DATA CUSTOMER & ITEM
// =====================================

$customers = $pdo->query("
    SELECT id, customer_name
    FROM customers
    ORDER BY customer_name ASC
")->fetchAll();

$items = $pdo->query("
    SELECT id, name, sku, stock
    FROM items
    ORDER BY name ASC
")->fetchAll();


// =====================================
// SEARCH SALES ORDER
// =====================================

$search = trim($_GET['search'] ?? '');

if ($search !== '') {

    $stmt = $pdo->prepare("
        SELECT so.*, c.customer_name,
            (
                SELECT COALESCE(SUM(quantity * unit_price), 0)
                FROM sales_order_items
                WHERE sales_order_id = so.id
            ) AS total_amount
        FROM sales_orders so
        JOIN customers c ON c.id = so.customer_id
        WHERE so.so_number LIKE ?
           OR c.customer_name LIKE ?
        ORDER BY so.created_at DESC
    ");

    $keyword = "%$search%";

    $stmt->execute([$keyword, $keyword]);
    $orders = $stmt->fetchAll();

} else {

    $orders = $pdo->query("
        SELECT so.*, c.customer_name,
            (
                SELECT COALESCE(SUM(quantity * unit_price), 0)
                FROM sales_order_items
                WHERE sales_order_id = so.id
            ) AS total_amount
        FROM sales_orders so
        JOIN customers c ON c.id = so.customer_id
        ORDER BY so.created_at DESC
    ")->fetchAll();
}


// =====================================
// STATISTIK
// =====================================

$totalOrders = (int)$pdo->query("
    SELECT COUNT(*) FROM sales_orders
")->fetchColumn();

$pendingOrders = (int)$pdo->query("
    SELECT COUNT(*) FROM sales_orders WHERE status = 'Pending'
")->fetchColumn();

$completedOrders = (int)$pdo->query("
    SELECT COUNT(*) FROM sales_orders WHERE status = 'Completed'
")->fetchColumn();

$totalSales = (float)$pdo->query("
    SELECT COALESCE(SUM(soi.quantity * soi.unit_price), 0)
    FROM sales_order_items soi
    JOIN sales_orders so ON so.id = soi.sales_order_id
    WHERE so.status = 'Completed'
")->fetchColumn();


require 'includes/header.php';
?>


<style>

.sales-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.sales-stat {
    background: white;
    padding: 19px;
    border: 1px solid #edf0ed;
    border-radius: 12px;
}

.sales-stat span {
    color: #78857d;
    font-size: 12px;
}

.sales-stat h2 {
    font-size: 25px;
    margin: 10px 0 0;
    color: #236b4b;
}

.sales-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.sales-field {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.sales-field label {
    font-size: 12px;
    font-weight: 700;
    color: #56645b;
}

.sales-field input,
.sales-field select,
.sales-field textarea {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #e2e9e3;
    border-radius: 7px;
    font-family: inherit;
    font-size: 13px;
    outline: none;
    box-sizing: border-box;
}

.sales-field input:focus,
.sales-field select:focus,
.sales-field textarea:focus {
    border-color: #3b9469;
}

.sales-field.full {
    grid-column: 1 / -1;
}

.sales-field textarea {
    min-height: 75px;
    resize: vertical;
}

.sales-items {
    margin-top: 20px;
}

.sales-items-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.sales-items-header h4 {
    margin: 0;
    color: #34483a;
}

.sales-item-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto;
    gap: 10px;
    margin-bottom: 10px;
    align-items: end;
}

.sales-item-row select,
.sales-item-row input {
    width: 100%;
    padding: 10px;
    border: 1px solid #e2e9e3;
    border-radius: 7px;
    box-sizing: border-box;
    font-size: 12px;
}

.sales-item-row label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: #56645b;
    margin-bottom: 6px;
}

.sales-remove {
    border: none;
    background: #fff0ed;
    color: #cf6555;
    padding: 10px 12px;
    border-radius: 7px;
    cursor: pointer;
}

.sales-add {
    border: 1px solid #d8e9dc;
    background: #edf8f0;
    color: #347b54;
    padding: 9px 13px;
    border-radius: 7px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
}

.sales-total {
    display: flex;
    justify-content: flex-end;
    margin-top: 18px;
    padding: 15px;
    background: #f5f8f5;
    border-radius: 8px;
    font-weight: 700;
    color: #236b4b;
}

.sales-actions {
    display: flex;
    gap: 8px;
    margin-top: 18px;
}

.sales-search {
    display: flex;
    gap: 9px;
}

.sales-search input {
    width: 220px;
    padding: 10px 12px;
    border: 1px solid #e2e9e3;
    border-radius: 7px;
}

.sales-table td {
    vertical-align: middle;
}

.sales-number {
    font-weight: 700;
    color: #344c78;
}

.sales-customer {
    font-weight: 700;
    color: #26372d;
}

.sales-badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
}

.sales-badge.pending {
    background: #fff5dc;
    color: #a47a25;
}

.sales-badge.processing {
    background: #e9f1ff;
    color: #4975b5;
}

.sales-badge.completed {
    background: #edf8f0;
    color: #347b54;
}

.sales-badge.cancelled {
    background: #fff0ed;
    color: #c45b4e;
}

.sales-status-form {
    display: flex;
    gap: 5px;
    align-items: center;
}

.sales-status-form select {
    padding: 7px;
    border: 1px solid #e2e9e3;
    border-radius: 6px;
    font-size: 11px;
}

.sales-status-form button {
    border: none;
    background: #edf5ef;
    color: #347b54;
    padding: 7px 9px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 11px;
}

.sales-delete {
    border: none;
    background: #fff0ed;
    color: #cf6555;
    padding: 7px 9px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 11px;
}

.sales-alert {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 13px;
}

.sales-alert.success {
    background: #edf8f0;
    color: #347b54;
}

.sales-alert.error {
    background: #fff0ed;
    color: #c45b4e;
}

@media(max-width: 900px) {
    .sales-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 700px) {
    .sales-form-grid {
        grid-template-columns: 1fr;
    }

    .sales-field.full {
        grid-column: auto;
    }

    .sales-item-row {
        grid-template-columns: 1fr 1fr;
    }

    .sales-search input {
        width: 100%;
    }
}

</style>


<!-- PAGE INTRO -->

<section class="welcome-row">

    <div>
        <p class="muted">ORDER MANAGEMENT</p>

        <h2>Manage Sales Orders</h2>

        <p class="muted">
            Create and manage customer orders and product deliveries.
        </p>
    </div>

</section>


<!-- NOTIFICATION -->

<?php if ($success): ?>
    <div class="sales-alert success">
        ✓ <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="sales-alert error">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>


<!-- STATISTICS -->

<div class="sales-stats">

    <div class="sales-stat">
        <span>Total Orders</span>
        <h2><?= number_format($totalOrders) ?></h2>
    </div>

    <div class="sales-stat">
        <span>Pending Orders</span>
        <h2><?= number_format($pendingOrders) ?></h2>
    </div>

    <div class="sales-stat">
        <span>Completed Orders</span>
        <h2><?= number_format($completedOrders) ?></h2>
    </div>

    <div class="sales-stat">
        <span>Total Completed Sales</span>
        <h2 style="font-size:19px;">
            Rp <?= number_format($totalSales, 0, ',', '.') ?>
        </h2>
    </div>

</div>


<!-- CREATE SALES ORDER -->

<section class="panel">

    <div class="panel-head">
        <div>
            <h3>Create New Sales Order</h3>
            <p>Enter customer and product order details.</p>
        </div>
    </div>


    <?php if (!$customers): ?>

        <div class="sales-alert error">
            No customers found. Please add a customer first in Customer Management.
        </div>

    <?php elseif (!$items): ?>

        <div class="sales-alert error">
            No products found. Please add inventory items first.
        </div>

    <?php else: ?>

    <form method="POST" action="sales_orders.php" id="salesOrderForm">

        <div class="sales-form-grid">

            <div class="sales-field">
                <label>Customer *</label>

                <select name="customer_id" required>
                    <option value="">Select Customer</option>

                    <?php foreach ($customers as $customer): ?>
                        <option value="<?= (int)$customer['id'] ?>">
                            <?= htmlspecialchars($customer['customer_name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>


            <div class="sales-field">
                <label>Order Date *</label>

                <input
                    type="date"
                    name="order_date"
                    value="<?= date('Y-m-d') ?>"
                    required
                >
            </div>


            <div class="sales-field">
                <label>Expected Delivery Date</label>

                <input
                    type="date"
                    name="delivery_date"
                    min="<?= date('Y-m-d') ?>"
                >
            </div>

            <div class="sales-field full">
                <label>Order Notes</label>

                <textarea
                    name="notes"
                    placeholder="Enter additional order information..."
                ></textarea>
            </div>

        </div>


        <!-- PRODUCT ITEMS -->

        <div class="sales-items">

            <div class="sales-items-header">
                <h4>Order Items</h4>

                <button
                    type="button"
                    class="sales-add"
                    onclick="addSalesItem()"
                >
                    + Add Product
                </button>
            </div>


            <div id="salesItemContainer">

                <div class="sales-item-row">

                    <div>
                        <label>Product</label>

                        <select name="item_id[]" class="product-select" required>
                            <option value="">Select Product</option>

                            <?php foreach ($items as $item): ?>
                                <option
                                    value="<?= (int)$item['id'] ?>"
                                    data-stock="<?= (int)$item['stock'] ?>"
                                >
                                    <?= htmlspecialchars($item['name']) ?>
                                    (Stock: <?= number_format($item['stock']) ?>)
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>


                    <div>
                        <label>Quantity</label>

                        <input
                            type="number"
                            name="quantity[]"
                            class="product-quantity"
                            min="1"
                            value="1"
                            required
                        >
                    </div>


                    <div>
                        <label>Unit Price (Rp)</label>

                        <input
                            type="number"
                            name="unit_price[]"
                            class="product-price"
                            min="0"
                            step="0.01"
                            value="0"
                            required
                        >
                    </div>


                    <div>
                        <label>&nbsp;</label>

                        <button
                            type="button"
                            class="sales-remove"
                            onclick="removeSalesItem(this)"
                        >
                            ✕
                        </button>
                    </div>

                </div>

            </div>


            <div class="sales-total">
                Total: Rp <span id="salesTotal" style="margin-left:6px;">0</span>
            </div>

        </div>


        <div class="sales-actions">

            <button
                type="submit"
                name="add_order"
                class="btn primary"
            >
                + Create Sales Order
            </button>

        </div>

    </form>

    <?php endif; ?>

</section>


<!-- SALES ORDER HISTORY -->

<section class="panel" style="margin-top:20px;">

    <div class="panel-head">

        <div>
            <h3>Sales Order History</h3>

            <p>
                <?= count($orders) ?> order records found
            </p>
        </div>


        <form method="GET" class="sales-search">

            <input
                type="text"
                name="search"
                placeholder="Search order or customer..."
                value="<?= htmlspecialchars($search) ?>"
            >

            <button type="submit" class="btn primary">
                Search
            </button>

            <?php if ($search !== ''): ?>
                <a href="sales_orders.php" class="btn">
                    Reset
                </a>
            <?php endif; ?>

        </form>

    </div>


    <div class="table-wrap">

        <table class="sales-table">

            <thead>
                <tr>
                    <th>SO Number</th>
                    <th>Customer</th>
                    <th>Order Date</th>
                    <th>Delivery Date</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th>Update Status</th>
                    <th>Actions</th>
                </tr>
            </thead>


            <tbody>

                <?php if (!$orders): ?>

                    <tr>
                        <td colspan="8" class="empty">
                            No sales orders found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($orders as $order): ?>

                        <tr>

                            <td>
    <a
        href="sales_order_detail.php?id=<?= (int)$order['id'] ?>"
        class="sales-number"
        style="text-decoration:none;"
    >
        <?= htmlspecialchars($order['so_number']) ?>
    </a>
</td>


                            <td>
                                <span class="sales-customer">
                                    <?= htmlspecialchars($order['customer_name']) ?>
                                </span>
                            </td>


                            <td>
                                <?= date('d M Y', strtotime($order['order_date'])) ?>
                            </td>


                            <td>
                                <?= $order['delivery_date']
                                    ? date('d M Y', strtotime($order['delivery_date']))
                                    : '-' ?>
                            </td>


                            <td>
                                Rp <?= number_format($order['total_amount'], 0, ',', '.') ?>
                            </td>


                            <td>
                                <span class="sales-badge <?= strtolower(str_replace(' ', '-', $order['status'])) ?>">
                                    <?= htmlspecialchars($order['status']) ?>
                                </span>
                            </td>


                            <td>

                                <?php if (!in_array($order['status'], ['Completed', 'Cancelled'], true)): ?>

                                    <form method="POST" class="sales-status-form">

                                        <input
                                            type="hidden"
                                            name="order_id"
                                            value="<?= (int)$order['id'] ?>"
                                        >

                                        <select name="status">

                                            <?php
                                            $availableStatuses = $order['status'] === 'Pending'
                                                ? ['Pending', 'Processing', 'Completed', 'Cancelled']
                                                : ['Processing', 'Completed', 'Cancelled'];
                                            ?>

                                            <?php foreach ($availableStatuses as $status): ?>
                                                <option
                                                    value="<?= $status ?>"
                                                    <?= $order['status'] === $status ? 'selected' : '' ?>
                                                >
                                                    <?= $status ?>
                                                </option>
                                            <?php endforeach; ?>

                                        </select>

                                        <button type="submit" name="update_status">
                                            Save
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <span class="muted">No changes</span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php if (in_array($order['status'], ['Pending', 'Cancelled'], true)): ?>

                                    <form
                                        method="POST"
                                        style="display:inline;"
                                        onsubmit="return confirm('Are you sure you want to delete this sales order?')"
                                    >

                                        <input
                                            type="hidden"
                                            name="order_id"
                                            value="<?= (int)$order['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="delete_order"
                                            class="sales-delete"
                                        >
                                            Delete
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <span class="muted">-</span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


<script>

// =====================================
// ADD PRODUCT ROW
// =====================================

function addSalesItem() {

    const container = document.getElementById('salesItemContainer');

    const firstRow = container.querySelector('.sales-item-row');

    const newRow = firstRow.cloneNode(true);

    newRow.querySelector('select').selectedIndex = 0;
    newRow.querySelector('.product-quantity').value = 1;
    newRow.querySelector('.product-price').value = 0;

    container.appendChild(newRow);

    updateSalesTotal();
}


// =====================================
// REMOVE PRODUCT ROW
// =====================================

function removeSalesItem(button) {

    const container = document.getElementById('salesItemContainer');

    if (container.querySelectorAll('.sales-item-row').length <= 1) {
        alert('At least one product is required.');
        return;
    }

    button.closest('.sales-item-row').remove();

    updateSalesTotal();
}


// =====================================
// CALCULATE TOTAL
// =====================================

function updateSalesTotal() {

    let total = 0;

    document.querySelectorAll('.sales-item-row').forEach(row => {

        const quantity = parseFloat(
            row.querySelector('.product-quantity').value
        ) || 0;

        const price = parseFloat(
            row.querySelector('.product-price').value
        ) || 0;

        total += quantity * price;

    });

    document.getElementById('salesTotal').textContent =
        new Intl.NumberFormat('id-ID').format(total);
}


document.addEventListener('input', function(e) {

    if (
        e.target.classList.contains('product-quantity') ||
        e.target.classList.contains('product-price')
    ) {
        updateSalesTotal();
    }

});

updateSalesTotal();


// =====================================
// VALIDASI JUMLAH PRODUK
// =====================================

document.getElementById('salesOrderForm')?.addEventListener('submit', function(e) {

    const rows = document.querySelectorAll('.sales-item-row');

    for (const row of rows) {

        const select = row.querySelector('.product-select');
        const quantityInput = row.querySelector('.product-quantity');

        const selectedOption = select.options[select.selectedIndex];

        if (!select.value) {
            alert('Please select a product.');
            e.preventDefault();
            return;
        }

        const stock = parseInt(selectedOption.dataset.stock || 0);
        const quantity = parseInt(quantityInput.value || 0);

        if (quantity <= 0) {
            alert('Quantity must be greater than zero.');
            e.preventDefault();
            return;
        }

        if (quantity > stock) {
            alert('Order quantity cannot exceed current stock.');
            e.preventDefault();
            return;
        }
    }

});

</script>


<?php require 'includes/footer.php'; ?>