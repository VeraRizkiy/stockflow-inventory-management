<?php
require 'includes/auth.php';
require 'config/database.php';

$pageTitle = 'Production Detail';


// =====================================
// VALIDASI ID
// =====================================

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: production.php');
    exit;
}

$productionId = (int) $_GET['id'];


// =====================================
// AMBIL DATA PRODUCTION
// =====================================

$stmt = $pdo->prepare("
    SELECT
        p.*,
        i.sku,
        i.unit,
        i.stock AS current_stock
    FROM productions p
    LEFT JOIN items i ON i.id = p.item_id
    WHERE p.id = ?
");

$stmt->execute([$productionId]);

$production = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$production) {
    header('Location: production.php');
    exit;
}


// =====================================
// AMBIL DATA MATERIAL
// =====================================

$stmt = $pdo->prepare("
    SELECT
        pm.*,
        i.name AS material_name,
        i.sku,
        i.unit,
        i.category
    FROM production_materials pm
    JOIN items i ON i.id = pm.item_id
    WHERE pm.production_id = ?
    ORDER BY i.name ASC
");

$stmt->execute([$productionId]);

$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);


// =====================================
// TOTAL MATERIAL
// =====================================

$totalMaterials = count($materials);


// =====================================
// STATUS CLASS
// =====================================

$statusClass = strtolower(str_replace(' ', '-', $production['status']));


require 'includes/header.php';
?>


<style>

.production-detail {
    max-width: 1100px;
    margin: 0 auto;
}

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

.production-detail-card {
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
    margin-bottom: 25px;
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

.detail-status {
    display: inline-block;
    padding: 7px 13px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.detail-status.planned {
    background: #fff5dc;
    color: #a47a25;
}

.detail-status.in-progress {
    background: #e9f1ff;
    color: #4975b5;
}

.detail-status.completed {
    background: #edf8f0;
    color: #347b54;
}

.detail-section {
    margin-bottom: 28px;
}

.detail-section h3 {
    font-size: 15px;
    color: #263957;
    margin-bottom: 16px;
}

.detail-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.detail-info-item {
    padding: 15px;
    background: #f7f8fa;
    border-radius: 9px;
}

.detail-info-item span {
    display: block;
    color: #888;
    font-size: 11px;
    margin-bottom: 7px;
}

.detail-info-item strong {
    color: #344c78;
    font-size: 14px;
}

.detail-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 12px;
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

.material-count {
    display: inline-block;
    padding: 5px 9px;
    background: #edf8f0;
    color: #347b54;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.detail-note {
    padding: 15px;
    background: #f7f8fa;
    border-radius: 9px;
    color: #666;
    font-size: 13px;
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

    .production-detail-card {
        box-shadow: none;
        border: 1px solid #eee;
    }

    body {
        background: white;
    }
}

@media(max-width: 700px) {

    .detail-info-grid {
        grid-template-columns: 1fr;
    }

    .production-detail-card {
        padding: 18px;
        overflow-x: auto;
    }

    .detail-header {
        flex-direction: column;
        gap: 15px;
    }

}

</style>


<div class="production-detail">

    <!-- ACTIONS -->

    <div class="detail-actions">

        <a href="production.php" class="detail-btn">
            ← Back to Production
        </a>

        <button onclick="window.print()" class="detail-btn">
            Print Production Order
        </button>

    </div>


    <div class="production-detail-card">

        <!-- HEADER -->

        <div class="detail-header">

            <div>
                <h2>Production Order</h2>

                <p>
                    Production Code:
                    <?= htmlspecialchars($production['code']) ?>
                </p>
            </div>

            <span class="detail-status <?= $statusClass ?>">
                <?= htmlspecialchars($production['status']) ?>
            </span>

        </div>


        <!-- PRODUCTION INFORMATION -->

        <div class="detail-section">

            <h3>Production Information</h3>

            <div class="detail-info-grid">

                <div class="detail-info-item">
                    <span>Production Code</span>

                    <strong>
                        <?= htmlspecialchars($production['code']) ?>
                    </strong>
                </div>

                <div class="detail-info-item">
                    <span>Finished Product</span>

                    <strong>
                        <?= htmlspecialchars($production['product_name']) ?>
                    </strong>
                </div>

                <div class="detail-info-item">
                    <span>Production Quantity</span>

                    <strong>
                        <?= number_format($production['quantity']) ?>
                        <?= htmlspecialchars($production['unit'] ?? '') ?>
                    </strong>
                </div>

                <div class="detail-info-item">
                    <span>Planned Date</span>

                    <strong>
                        <?= date('d M Y', strtotime($production['planned_date'])) ?>
                    </strong>
                </div>

                <div class="detail-info-item">
                    <span>Production Status</span>

                    <strong>
                        <?= htmlspecialchars($production['status']) ?>
                    </strong>
                </div>

                <div class="detail-info-item">
                    <span>Required Materials</span>

                    <strong>
                        <?= number_format($totalMaterials) ?> Materials
                    </strong>
                </div>

            </div>

        </div>


        <!-- MATERIAL INFORMATION -->

        <div class="detail-section">

            <h3>
                Required Materials

                <span class="material-count">
                    <?= number_format($totalMaterials) ?> Items
                </span>
            </h3>


            <table class="detail-table">

                <thead>

                    <tr>
                        <th>No.</th>
                        <th>Material Name</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th>Required Quantity</th>
                    </tr>

                </thead>


                <tbody>

                    <?php if ($materials): ?>

                        <?php foreach ($materials as $index => $material): ?>

                            <tr>

                                <td>
                                    <?= $index + 1 ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($material['material_name']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= htmlspecialchars($material['sku']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($material['category']) ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        (float)$material['quantity_required'],
                                        2
                                    ) ?>

                                    <?= htmlspecialchars($material['unit']) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="5" style="text-align:center;">
                                No materials recorded.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>


        <!-- PRODUCTION SUMMARY -->

        <div class="detail-section">

            <h3>Production Summary</h3>

            <div class="detail-note">

                <p>
                    <strong>Production Order:</strong>
                    <?= htmlspecialchars($production['code']) ?>
                </p>

                <p>
                    <strong>Finished Product:</strong>
                    <?= htmlspecialchars($production['product_name']) ?>
                </p>

                <p>
                    <strong>Target Production:</strong>
                    <?= number_format($production['quantity']) ?>
                    <?= htmlspecialchars($production['unit'] ?? '') ?>
                </p>

                <p>
                    <strong>Materials Required:</strong>
                    <?= number_format($totalMaterials) ?> items
                </p>

                <p>
                    <strong>Current Status:</strong>
                    <?= htmlspecialchars($production['status']) ?>
                </p>

            </div>

        </div>

    </div>

</div>


<?php require 'includes/footer.php'; ?>