<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

$pageTitle = 'Purchase Orders';

$message = '';
$error = '';

/* CREATE PURCHASE ORDER */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_po'])) {

    $supplier_id = (int) $_POST['supplier_id'];
    $order_date = $_POST['order_date'];
    $expected_date = !empty($_POST['expected_date']) ? $_POST['expected_date'] : null;
    $notes = trim($_POST['notes'] ?? '');

    $item_ids = $_POST['item_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $prices = $_POST['unit_price'] ?? [];

    if ($supplier_id <= 0 || empty($item_ids)) {
        $error = 'Pilih supplier dan minimal satu item.';
    } else {
        try {
            $pdo->beginTransaction();

            $po_number = 'PO-' . date('Ymd-His');

            $stmt = $pdo->prepare("
                INSERT INTO purchase_orders
                (po_number, supplier_id, order_date, expected_date, notes)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $po_number,
                $supplier_id,
                $order_date,
                $expected_date,
                $notes
            ]);

            $po_id = $pdo->lastInsertId();

            $insertItem = $pdo->prepare("
                INSERT INTO purchase_order_items
                (purchase_order_id, item_id, quantity, unit_price)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($item_ids as $index => $item_id) {

                $item_id = (int) $item_id;
                $quantity = (int) ($quantities[$index] ?? 0);
                $price = (float) ($prices[$index] ?? 0);

                if ($item_id <= 0 || $quantity <= 0 || $price < 0) {
                    throw new Exception('Data item tidak valid.');
                }

                $insertItem->execute([
                    $po_id,
                    $item_id,
                    $quantity,
                    $price
                ]);
            }

            $pdo->commit();

            $message = 'Purchase Order berhasil dibuat. Nomor PO: ' . $po_number;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = 'Gagal membuat Purchase Order: ' . $e->getMessage();
        }
    }
}

/* UPDATE STATUS */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {

    $po_id = (int) $_POST['po_id'];
    $new_status = $_POST['status'];

    $allowed_status = ['Draft', 'Ordered', 'Received', 'Cancelled'];

    if (!in_array($new_status, $allowed_status, true)) {
        $error = 'Status tidak valid.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT status
                FROM purchase_orders
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$po_id]);
            $po = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$po) {
                throw new Exception('Purchase Order tidak ditemukan.');
            }

            if ($po['status'] === 'Received' || $po['status'] === 'Cancelled') {
                throw new Exception('Status Purchase Order ini sudah final.');
            }

            if ($new_status === 'Received') {

                $itemsStmt = $pdo->prepare("
                    SELECT item_id, quantity
                    FROM purchase_order_items
                    WHERE purchase_order_id = ?
                ");
                $itemsStmt->execute([$po_id]);

                $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($items)) {
                    throw new Exception('Purchase Order tidak memiliki item.');
                }

                foreach ($items as $item) {

                    $updateStock = $pdo->prepare("
                        UPDATE items
                        SET stock = stock + ?
                        WHERE id = ?
                    ");
                    $updateStock->execute([
                        $item['quantity'],
                        $item['item_id']
                    ]);

                    $transaction = $pdo->prepare("
                        INSERT INTO transactions
                        (item_id, type, quantity, note, user_id)
                        VALUES (?, 'IN', ?, ?, ?)
                    ");

                    $transaction->execute([
                        $item['item_id'],
                        $item['quantity'],
                        'Purchase Order #' . $po_id,
                        $_SESSION['user_id'] ?? null
                    ]);
                }
            }

            $update = $pdo->prepare("
                UPDATE purchase_orders
                SET status = ?
                WHERE id = ?
            ");
            $update->execute([$new_status, $po_id]);

            $pdo->commit();

            $message = 'Status Purchase Order berhasil diperbarui.';

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = 'Gagal memperbarui status: ' . $e->getMessage();
        }
    }
}

/* GET SUPPLIERS */
$suppliers = $pdo->query("
    SELECT id, supplier_name
    FROM suppliers
    ORDER BY supplier_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* GET ITEMS */
$items = $pdo->query("
    SELECT id, name, sku, unit
    FROM items
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* GET PURCHASE ORDERS */
$orders = $pdo->query("
    SELECT
        po.id,
        po.po_number,
        po.order_date,
        po.expected_date,
        po.status,
        s.supplier_name,
        (
            SELECT COALESCE(SUM(poi.quantity * poi.unit_price), 0)
            FROM purchase_order_items poi
            WHERE poi.purchase_order_id = po.id
        ) AS total_amount,
        (
            SELECT COALESCE(SUM(poi.quantity), 0)
            FROM purchase_order_items poi
            WHERE poi.purchase_order_id = po.id
        ) AS total_items
    FROM purchase_orders po
    JOIN suppliers s ON s.id = po.supplier_id
    ORDER BY po.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$totalPO = count($orders);
$draftPO = 0;
$orderedPO = 0;
$receivedPO = 0;

foreach ($orders as $order) {
    if ($order['status'] === 'Draft') $draftPO++;
    if ($order['status'] === 'Ordered') $orderedPO++;
    if ($order['status'] === 'Received') $receivedPO++;
}

require_once 'includes/header.php';
?>

<style>
.po-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.po-stat, .po-panel {
    background: #fff;
    border: 1px solid #e8ebf0;
    border-radius: 14px;
    padding: 20px;
}

.po-stat small {
    color: #7b8494;
    font-size: 12px;
}

.po-stat h2 {
    margin: 8px 0 0;
    font-size: 26px;
}

.po-panel {
    margin-bottom: 24px;
}

.po-panel h3 {
    margin: 0 0 18px;
}

.po-form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.po-field label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 7px;
}

.po-field input,
.po-field select,
.po-field textarea {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #dfe3e9;
    border-radius: 8px;
    font: inherit;
}

.po-items {
    width: 100%;
    border-collapse: collapse;
    margin-top: 18px;
}

.po-items th,
.po-items td {
    padding: 12px 8px;
    border-bottom: 1px solid #edf0f3;
    text-align: left;
    font-size: 13px;
}

.po-items input,
.po-items select {
    width: 100%;
    padding: 9px;
    border: 1px solid #dfe3e9;
    border-radius: 7px;
}

.po-btn {
    border: none;
    border-radius: 8px;
    padding: 10px 15px;
    cursor: pointer;
    font-weight: 600;
}

.po-btn-primary {
    background: #202c45;
    color: white;
}

.po-btn-light {
    background: #edf0f5;
    color: #344054;
}

.po-btn-danger {
    background: #fff0f0;
    color: #c0392b;
}

.po-total {
    text-align: right;
    font-weight: 700;
    margin-top: 18px;
    font-size: 17px;
}

.po-table {
    width: 100%;
    border-collapse: collapse;
}

.po-table th,
.po-table td {
    padding: 14px 12px;
    border-bottom: 1px solid #edf0f3;
    text-align: left;
    font-size: 13px;
}

.po-table th {
    color: #7b8494;
    font-size: 11px;
    text-transform: uppercase;
}

.po-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.po-draft { background: #f1f2f4; color: #596273; }
.po-ordered { background: #fff3d9; color: #a66b00; }
.po-received { background: #e3f7ec; color: #218653; }
.po-cancelled { background: #ffe8e8; color: #c0392b; }

.po-alert {
    padding: 13px 16px;
    border-radius: 9px;
    margin-bottom: 18px;
}

.po-success { background: #e6f7ed; color: #218653; }
.po-error { background: #ffe8e8; color: #c0392b; }

.po-status-form {
    display: flex;
    gap: 7px;
}

.po-status-form select {
    padding: 7px;
    border: 1px solid #dfe3e9;
    border-radius: 7px;
}

@media(max-width: 850px) {
    .po-stats { grid-template-columns: repeat(2, 1fr); }
    .po-form-grid { grid-template-columns: 1fr; }
    .po-table-wrapper { overflow-x: auto; }
}
</style>

<?php if ($message): ?>
    <div class="po-alert po-success">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="po-alert po-error">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="po-stats">
    <div class="po-stat">
        <small>Total Purchase Orders</small>
        <h2><?= $totalPO ?></h2>
    </div>

    <div class="po-stat">
        <small>Draft</small>
        <h2><?= $draftPO ?></h2>
    </div>

    <div class="po-stat">
        <small>Ordered</small>
        <h2><?= $orderedPO ?></h2>
    </div>

    <div class="po-stat">
        <small>Received</small>
        <h2><?= $receivedPO ?></h2>
    </div>
</div>

<div class="po-panel">
    <h3>Create Purchase Order</h3>

    <form method="POST">

        <div class="po-form-grid">

            <div class="po-field">
                <label>Supplier</label>
                <select name="supplier_id" required>
                    <option value="">Select Supplier</option>

                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= $supplier['id'] ?>">
                            <?= htmlspecialchars($supplier['supplier_name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div class="po-field">
                <label>Order Date</label>
                <input type="date" name="order_date"
                       value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="po-field">
                <label>Expected Delivery Date</label>
                <input type="date" name="expected_date">
            </div>

            <div class="po-field">
                <label>Notes</label>
                <input type="text" name="notes"
                       placeholder="Additional information">
            </div>

        </div>

        <h3 style="margin-top:25px;">Order Items</h3>

        <div class="po-table-wrapper">
            <table class="po-items">
                <thead>
                    <tr>
                        <th style="width:35%">Item</th>
                        <th>Quantity</th>
                        <th>Unit Price (Rp)</th>
                        <th>Subtotal</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody id="poItemRows">
                    <tr>
                        <td>
                            <select name="item_id[]" required>
                                <option value="">Select Item</option>

                                <?php foreach ($items as $item): ?>
                                    <option value="<?= $item['id'] ?>">
                                        <?= htmlspecialchars($item['name']) ?>
                                        (<?= htmlspecialchars($item['sku']) ?>)
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </td>

                        <td>
                            <input type="number" name="quantity[]"
                                   min="1" value="1" required
                                   oninput="calculateTotal()">
                        </td>

                        <td>
                            <input type="number" name="unit_price[]"
                                   min="0" value="0" required
                                   oninput="calculateTotal()">
                        </td>

                        <td class="subtotal">Rp 0</td>

                        <td>
                            <button type="button"
                                    class="po-btn po-btn-danger"
                                    onclick="removeRow(this)">×</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <button type="button"
                class="po-btn po-btn-light"
                onclick="addRow()"
                style="margin-top:15px;">
            + Add Item
        </button>

        <div class="po-total">
            Grand Total: <span id="grandTotal">Rp 0</span>
        </div>

        <button type="submit"
                name="create_po"
                class="po-btn po-btn-primary"
                style="margin-top:20px;">
            Create Purchase Order
        </button>

    </form>
</div>

<div class="po-panel">
    <h3>Purchase Order History</h3>

    <div class="po-table-wrapper">
        <table class="po-table">
            <thead>
                <tr>
                    <th>PO Number</th>
                    <th>Supplier</th>
                    <th>Order Date</th>
                    <th>Expected Date</th>
                    <th>Items</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th>Update Status</th>
                </tr>
            </thead>

            <tbody>

            <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="8" style="text-align:center;padding:30px;">
                        No purchase orders yet.
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($orders as $order): ?>

                <?php
                $badge = 'po-draft';

                if ($order['status'] === 'Ordered') $badge = 'po-ordered';
                if ($order['status'] === 'Received') $badge = 'po-received';
                if ($order['status'] === 'Cancelled') $badge = 'po-cancelled';
                ?>

                <tr>
                   <td>
    <a href="purchase_order_detail.php?id=<?= $order['id'] ?>"
       style="color:#344c78;font-weight:700;text-decoration:none;">
        <?= htmlspecialchars($order['po_number']) ?>
    </a>
</td>

                    <td><?= htmlspecialchars($order['supplier_name']) ?></td>

                    <td><?= htmlspecialchars($order['order_date']) ?></td>

                    <td><?= htmlspecialchars($order['expected_date'] ?? '-') ?></td>

                    <td><?= (int) $order['total_items'] ?></td>

                    <td>
                        Rp <?= number_format($order['total_amount'], 0, ',', '.') ?>
                    </td>

                    <td>
                        <span class="po-badge <?= $badge ?>">
                            <?= htmlspecialchars($order['status']) ?>
                        </span>
                    </td>

                    <td>
                        <?php if (!in_array($order['status'], ['Received', 'Cancelled'], true)): ?>

                            <form method="POST" class="po-status-form">

                                <input type="hidden" name="po_id"
                                       value="<?= $order['id'] ?>">

                                <select name="status">
                                    <option value="Draft"
                                        <?= $order['status'] === 'Draft' ? 'selected' : '' ?>>
                                        Draft
                                    </option>

                                    <option value="Ordered"
                                        <?= $order['status'] === 'Ordered' ? 'selected' : '' ?>>
                                        Ordered
                                    </option>

                                    <option value="Received">
                                        Received
                                    </option>

                                    <option value="Cancelled">
                                        Cancelled
                                    </option>
                                </select>

                                <button type="submit"
                                        name="update_status"
                                        class="po-btn po-btn-primary">
                                    Save
                                </button>

                            </form>

                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>

            <?php endforeach; ?>

            </tbody>
        </table>
    </div>
</div>

<script>
function calculateTotal() {
    let grandTotal = 0;

    document.querySelectorAll('#poItemRows tr').forEach(row => {
        const quantity = Number(row.querySelector('[name="quantity[]"]').value) || 0;
        const price = Number(row.querySelector('[name="unit_price[]"]').value) || 0;

        const subtotal = quantity * price;

        row.querySelector('.subtotal').textContent =
            'Rp ' + subtotal.toLocaleString('id-ID');

        grandTotal += subtotal;
    });

    document.getElementById('grandTotal').textContent =
        'Rp ' + grandTotal.toLocaleString('id-ID');
}

function addRow() {
    const tbody = document.getElementById('poItemRows');
    const firstRow = tbody.querySelector('tr');
    const newRow = firstRow.cloneNode(true);

    newRow.querySelector('select').selectedIndex = 0;
    newRow.querySelector('[name="quantity[]"]').value = 1;
    newRow.querySelector('[name="unit_price[]"]').value = 0;
    newRow.querySelector('.subtotal').textContent = 'Rp 0';

    tbody.appendChild(newRow);
    calculateTotal();
}

function removeRow(button) {
    const tbody = document.getElementById('poItemRows');

    if (tbody.querySelectorAll('tr').length > 1) {
        button.closest('tr').remove();
        calculateTotal();
    }
}

calculateTotal();
</script>

<?php require_once 'includes/footer.php'; ?>