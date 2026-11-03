<?php
require 'includes/auth.php';
require 'config/database.php';

$pageTitle = 'Production';
$message = '';
$messageType = 'info';


// =====================================
// AMBIL DATA INVENTORY
// =====================================

$products = $pdo->query("
    SELECT id, name, sku, stock, unit
    FROM items
    WHERE category = 'Finished Goods'
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$materials = $pdo->query("
    SELECT id, name, sku, stock, unit, category
    FROM items
    WHERE category IN ('Raw Material', 'Packaging')
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);


// =====================================
// BUAT PRODUCTION ORDER
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_production'])) {

    $code = trim($_POST['code'] ?? '');
    $productId = (int)($_POST['item_id'] ?? 0);
    $qty = (int)($_POST['quantity'] ?? 0);
    $date = $_POST['planned_date'] ?? '';

    $materialIds = $_POST['material_id'] ?? [];
    $materialQtys = $_POST['material_quantity'] ?? [];

    if (!$code || $productId <= 0 || $qty <= 0 || !$date) {

        $message = 'Please complete all required production information.';
        $messageType = 'error';

    } elseif (empty($materialIds)) {

        $message = 'Please add at least one production material.';
        $messageType = 'error';

    } else {

        try {

            $pdo->beginTransaction();

            // Ambil produk jadi
            $stmt = $pdo->prepare("
                SELECT id, name
                FROM items
                WHERE id = ? AND category = 'Finished Goods'
            ");

            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                throw new Exception('Finished product not found.');
            }

            // Cek kode produksi
            $stmt = $pdo->prepare("
                SELECT id FROM productions WHERE code = ?
            ");

            $stmt->execute([$code]);

            if ($stmt->fetch()) {
                throw new Exception('Production code already exists.');
            }

            // Simpan production order
            $stmt = $pdo->prepare("
                INSERT INTO productions
                (code, product_name, item_id, quantity, planned_date, status)
                VALUES (?, ?, ?, ?, ?, 'Planned')
            ");

            $stmt->execute([
                $code,
                $product['name'],
                $productId,
                $qty,
                $date
            ]);

            $productionId = $pdo->lastInsertId();

            // Simpan kebutuhan bahan baku
            $insertMaterial = $pdo->prepare("
                INSERT INTO production_materials
                (production_id, item_id, quantity_required)
                VALUES (?, ?, ?)
            ");

            foreach ($materialIds as $index => $materialId) {

                $materialId = (int)$materialId;
                $materialQty = (float)($materialQtys[$index] ?? 0);

                if ($materialId <= 0 || $materialQty <= 0) {
                    throw new Exception('Invalid production material.');
                }

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM items
                    WHERE id = ?
                    AND category IN ('Raw Material', 'Packaging')
                ");

                $stmt->execute([$materialId]);

                if (!$stmt->fetch()) {
                    throw new Exception('Selected material was not found.');
                }

                $insertMaterial->execute([
                    $productionId,
                    $materialId,
                    $materialQty
                ]);
            }

            $pdo->commit();

            header('Location: production.php?success=added');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = 'Unable to create production order: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}


// =====================================
// UPDATE STATUS PRODUKSI
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {

    $productionId = (int)($_POST['production_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';

    $allowedStatuses = [
        'Planned',
        'In Progress',
        'Completed'
    ];

    if (!in_array($newStatus, $allowedStatuses, true)) {

        $message = 'Invalid production status.';
        $messageType = 'error';

    } else {

        try {

            $pdo->beginTransaction();

            // Kunci production order
            $stmt = $pdo->prepare("
                SELECT *
                FROM productions
                WHERE id = ?
                FOR UPDATE
            ");

            $stmt->execute([$productionId]);
            $production = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$production) {
                throw new Exception('Production order not found.');
            }

            $oldStatus = $production['status'];

            if ($oldStatus === 'Completed' && $newStatus !== 'Completed') {
                throw new Exception('Completed production cannot be changed.');
            }

            if ($oldStatus === 'Completed') {
                throw new Exception('This production is already completed.');
            }

            // Jika produksi selesai
            if ($newStatus === 'Completed') {

                if (empty($production['item_id'])) {
                    throw new Exception(
                        'This is an old production order without an inventory product. Create a new production order.'
                    );
                }

                // Ambil bahan baku
                $stmt = $pdo->prepare("
                    SELECT
                        pm.item_id,
                        pm.quantity_required,
                        i.name,
                        i.stock
                    FROM production_materials pm
                    JOIN items i ON i.id = pm.item_id
                    WHERE pm.production_id = ?
                    FOR UPDATE
                ");

                $stmt->execute([$productionId]);
                $usedMaterials = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!$usedMaterials) {
                    throw new Exception('No production materials found.');
                }

                // Kurangi stok bahan baku
                foreach ($usedMaterials as $material) {

                    $materialId = (int)$material['item_id'];
                    $required = (float)$material['quantity_required'];

                    $stmt = $pdo->prepare("
                        UPDATE items
                        SET stock = stock - ?
                        WHERE id = ?
                        AND stock >= ?
                    ");

                    $stmt->execute([
                        $required,
                        $materialId,
                        $required
                    ]);

                    if ($stmt->rowCount() !== 1) {
                        throw new Exception(
                            'Insufficient stock for material: ' . $material['name']
                        );
                    }

                    // Catat transaksi bahan baku keluar
                    $stmt = $pdo->prepare("
                        INSERT INTO transactions
                        (item_id, type, quantity, note, user_id)
                        VALUES (?, 'OUT', ?, ?, ?)
                    ");

                    $stmt->execute([
                        $materialId,
                        $required,
                        'Production ' . $production['code'],
                        $_SESSION['user_id'] ?? null
                    ]);
                }

                // Tambahkan stok produk jadi
                $stmt = $pdo->prepare("
                    UPDATE items
                    SET stock = stock + ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $production['quantity'],
                    $production['item_id']
                ]);

                // Catat transaksi produk jadi masuk
                $stmt = $pdo->prepare("
                    INSERT INTO transactions
                    (item_id, type, quantity, note, user_id)
                    VALUES (?, 'IN', ?, ?, ?)
                ");

                $stmt->execute([
                    $production['item_id'],
                    $production['quantity'],
                    'Production Completed ' . $production['code'],
                    $_SESSION['user_id'] ?? null
                ]);
            }

            // Update status
            $stmt = $pdo->prepare("
                UPDATE productions
                SET status = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $newStatus,
                $productionId
            ]);

            $pdo->commit();

            header('Location: production.php?success=status');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = 'Unable to update production: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}


// =====================================
// NOTIFIKASI
// =====================================

if (isset($_GET['success'])) {

    if ($_GET['success'] === 'added') {
        $message = 'Production order successfully created.';
    }

    if ($_GET['success'] === 'status') {
        $message = 'Production status successfully updated.';
    }
}


// =====================================
// AMBIL DATA PRODUCTION
// =====================================

$rows = $pdo->query("
    SELECT
        p.*,
        i.sku,
        i.stock AS product_stock,
        i.unit
    FROM productions p
    LEFT JOIN items i ON i.id = p.item_id
    ORDER BY p.planned_date DESC, p.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);


// =====================================
// STATISTIK
// =====================================

$totalProductions = (int)$pdo->query("
    SELECT COUNT(*) FROM productions
")->fetchColumn();

$plannedProductions = (int)$pdo->query("
    SELECT COUNT(*) FROM productions WHERE status = 'Planned'
")->fetchColumn();

$inProgressProductions = (int)$pdo->query("
    SELECT COUNT(*) FROM productions WHERE status = 'In Progress'
")->fetchColumn();

$completedProductions = (int)$pdo->query("
    SELECT COUNT(*) FROM productions WHERE status = 'Completed'
")->fetchColumn();


require 'includes/header.php';
?>


<style>

.production-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.production-stat {
    background: white;
    padding: 19px;
    border: 1px solid #edf0ed;
    border-radius: 12px;
}

.production-stat span {
    color: #78857d;
    font-size: 12px;
}

.production-stat h2 {
    font-size: 25px;
    margin: 10px 0 0;
    color: #236b4b;
}

.production-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.production-field {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.production-field label {
    font-size: 12px;
    font-weight: 700;
    color: #56645b;
}

.production-field input,
.production-field select {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #e2e9e3;
    border-radius: 7px;
    font-family: inherit;
    font-size: 13px;
    box-sizing: border-box;
}

.production-materials {
    margin-top: 24px;
}

.production-material-row {
    display: grid;
    grid-template-columns: 2fr 1fr auto;
    gap: 10px;
    align-items: end;
    margin-bottom: 12px;
}

.production-material-row select,
.production-material-row input {
    width: 100%;
    padding: 10px;
    border: 1px solid #e2e9e3;
    border-radius: 7px;
    box-sizing: border-box;
}

.production-material-row label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: #56645b;
    margin-bottom: 6px;
}

.production-add,
.production-remove {
    padding: 10px 13px;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
}

.production-add {
    background: #edf8f0;
    color: #347b54;
}

.production-remove {
    background: #fff0ed;
    color: #cf6555;
}

.production-alert {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 13px;
}

.production-alert.info {
    background: #edf8f0;
    color: #347b54;
}

.production-alert.error {
    background: #fff0ed;
    color: #c45b4e;
}

.production-badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
}

.production-badge.planned {
    background: #fff5dc;
    color: #a47a25;
}

.production-badge.in-progress {
    background: #e9f1ff;
    color: #4975b5;
}

.production-badge.completed {
    background: #edf8f0;
    color: #347b54;
}

.production-status-form {
    display: flex;
    gap: 6px;
    align-items: center;
}

.production-status-form select {
    padding: 7px;
    border: 1px solid #e2e9e3;
    border-radius: 6px;
    font-size: 11px;
}

.production-status-form button {
    padding: 7px 10px;
    border: none;
    border-radius: 6px;
    background: #edf5ef;
    color: #347b54;
    cursor: pointer;
}

@media(max-width: 900px) {
    .production-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 700px) {
    .production-form-grid {
        grid-template-columns: 1fr;
    }

    .production-material-row {
        grid-template-columns: 1fr 1fr;
    }
}

</style>


<!-- PAGE INTRO -->

<div class="page-intro">
    <div>
        <p class="muted">PRODUCTION MANAGEMENT</p>
        <h2>Production Planning</h2>
        <p class="muted">
            Manage production orders, materials, and finished goods.
        </p>
    </div>
</div>


<!-- NOTIFICATION -->

<?php if ($message): ?>
    <div class="production-alert <?= $messageType ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>


<!-- STATISTICS -->

<div class="production-stats">

    <div class="production-stat">
        <span>Total Production</span>
        <h2><?= number_format($totalProductions) ?></h2>
    </div>

    <div class="production-stat">
        <span>Planned</span>
        <h2><?= number_format($plannedProductions) ?></h2>
    </div>

    <div class="production-stat">
        <span>In Progress</span>
        <h2><?= number_format($inProgressProductions) ?></h2>
    </div>

    <div class="production-stat">
        <span>Completed</span>
        <h2><?= number_format($completedProductions) ?></h2>
    </div>

</div>


<!-- CREATE PRODUCTION ORDER -->

<section class="panel">

    <div class="panel-head">
        <div>
            <h3>Create Production Order</h3>
            <p>Choose finished goods and required production materials.</p>
        </div>
    </div>

    <?php if (!$products): ?>

        <div class="production-alert error">
            No Finished Goods found in Inventory.
        </div>

    <?php elseif (!$materials): ?>

        <div class="production-alert error">
            No Raw Materials or Packaging items found in Inventory.
        </div>

    <?php else: ?>

    <form method="POST" action="production.php">

        <div class="production-form-grid">

            <div class="production-field">
                <label>Production Code *</label>
                <input
                    type="text"
                    name="code"
                    placeholder="PRD-2026-001"
                    required
                >
            </div>

            <div class="production-field">
                <label>Finished Product *</label>

                <select name="item_id" required>
                    <option value="">Select Finished Product</option>

                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int)$product['id'] ?>">
                            <?= htmlspecialchars($product['name']) ?>
                            (<?= htmlspecialchars($product['sku']) ?>)
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div class="production-field">
                <label>Production Quantity *</label>
                <input
                    type="number"
                    name="quantity"
                    min="1"
                    placeholder="100"
                    required
                >
            </div>

            <div class="production-field">
                <label>Planned Date *</label>
                <input
                    type="date"
                    name="planned_date"
                    value="<?= date('Y-m-d') ?>"
                    required
                >
            </div>

        </div>


        <!-- MATERIALS -->

        <div class="production-materials">

            <div class="panel-head">
                <div>
                    <h3>Required Materials</h3>
                    <p>Specify materials and quantities needed for this production.</p>
                </div>

                <button
                    type="button"
                    class="production-add"
                    onclick="addProductionMaterial()"
                >
                    + Add Material
                </button>
            </div>


            <div id="productionMaterialContainer">

                <div class="production-material-row">

                    <div>
                        <label>Material</label>

                        <select name="material_id[]" required>
                            <option value="">Select Material</option>

                            <?php foreach ($materials as $material): ?>
                                <option value="<?= (int)$material['id'] ?>">
                                    <?= htmlspecialchars($material['name']) ?>
                                    (Stock: <?= number_format($material['stock']) ?>
                                    <?= htmlspecialchars($material['unit']) ?>)
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div>
                        <label>Required Quantity</label>

                        <input
                            type="number"
                            name="material_quantity[]"
                            min="0.01"
                            step="0.01"
                            placeholder="10"
                            required
                        >
                    </div>

                    <div>
                        <label>&nbsp;</label>

                        <button
                            type="button"
                            class="production-remove"
                            onclick="removeProductionMaterial(this)"
                        >
                            ✕
                        </button>
                    </div>

                </div>

            </div>

        </div>


        <div style="margin-top:20px;">
            <button
                type="submit"
                name="add_production"
                class="btn primary"
            >
                + Create Production Order
            </button>
        </div>

    </form>

    <?php endif; ?>

</section>


<!-- PRODUCTION HISTORY -->

<section class="panel" style="margin-top:20px;">

    <div class="panel-head">
        <div>
            <h3>Production History</h3>
            <p><?= count($rows) ?> production orders recorded</p>
        </div>
    </div>


    <div class="table-wrap">

        <table>

            <thead>
                <tr>
                    <th>Production Code</th>
                    <th>Finished Product</th>
                    <th>Quantity</th>
                    <th>Planned Date</th>
                    <th>Status</th>
                    <th>Update Status</th>
                </tr>
            </thead>

            <tbody>

                <?php if (!$rows): ?>

                    <tr>
                        <td colspan="6" class="empty">
                            No production orders found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($rows as $row): ?>

                        <tr>

                            <td>
    <a href="production_detail.php?id=<?= (int)$row['id'] ?>"
       style="color:#344c78;font-weight:700;text-decoration:none;">
        <?= htmlspecialchars($row['code']) ?>
    </a>
</td>

                            <td>
                                <?= htmlspecialchars($row['product_name']) ?>
                            </td>

                            <td>
                                <?= number_format($row['quantity']) ?>
                                <?= htmlspecialchars($row['unit'] ?? '') ?>
                            </td>

                            <td>
                                <?= date('d M Y', strtotime($row['planned_date'])) ?>
                            </td>

                            <td>
                                <span class="production-badge <?= strtolower(str_replace(' ', '-', $row['status'])) ?>">
                                    <?= htmlspecialchars($row['status']) ?>
                                </span>
                            </td>

                            <td>

                                <?php if ($row['status'] !== 'Completed'): ?>

                                    <form method="POST" class="production-status-form">

                                        <input
                                            type="hidden"
                                            name="production_id"
                                            value="<?= (int)$row['id'] ?>"
                                        >

                                        <select name="status">

                                            <?php
                                            $statuses = $row['status'] === 'Planned'
                                                ? ['Planned', 'In Progress', 'Completed']
                                                : ['In Progress', 'Completed'];
                                            ?>

                                            <?php foreach ($statuses as $status): ?>
                                                <option
                                                    value="<?= $status ?>"
                                                    <?= $row['status'] === $status ? 'selected' : '' ?>
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

                                    <span class="muted">Completed</span>

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
// ADD MATERIAL
// =====================================

function addProductionMaterial() {

    const container = document.getElementById('productionMaterialContainer');

    const firstRow = container.querySelector('.production-material-row');

    const newRow = firstRow.cloneNode(true);

    newRow.querySelector('select').selectedIndex = 0;
    newRow.querySelector('input').value = '';

    container.appendChild(newRow);
}


// =====================================
// REMOVE MATERIAL
// =====================================

function removeProductionMaterial(button) {

    const container = document.getElementById('productionMaterialContainer');

    if (container.querySelectorAll('.production-material-row').length <= 1) {
        alert('At least one material is required.');
        return;
    }

    button.closest('.production-material-row').remove();
}

</script>


<?php require 'includes/footer.php'; ?>