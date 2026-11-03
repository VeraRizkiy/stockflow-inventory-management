<?php
require 'includes/auth.php';
require 'config/database.php';

$pageTitle = 'Dashboard';

// Statistik utama
$totalItems = (int)$pdo->query(
    "SELECT COUNT(*) FROM items"
)->fetchColumn();

$totalUnits = (int)$pdo->query(
    "SELECT COALESCE(SUM(stock), 0) FROM items"
)->fetchColumn();

$lowStock = (int)$pdo->query(
    "SELECT COUNT(*) FROM items WHERE stock <= min_stock"
)->fetchColumn();

$activeProduction = (int)$pdo->query(
    "SELECT COUNT(*) FROM productions
     WHERE status IN ('Planned', 'In Progress')"
)->fetchColumn();

// Transaksi terbaru
$recent = $pdo->query("
    SELECT t.*, i.name AS item_name, i.sku
    FROM transactions t
    JOIN items i ON i.id = t.item_id
    ORDER BY t.created_at DESC
    LIMIT 6
")->fetchAll();

// Item dengan stok rendah
$lowItems = $pdo->query("
    SELECT *
    FROM items
    WHERE stock <= min_stock
    ORDER BY stock ASC
    LIMIT 5
")->fetchAll();

// Data kategori inventory
$categoryRows = $pdo->query("
    SELECT category, SUM(stock) AS total_stock
    FROM items
    GROUP BY category
")->fetchAll();

// Data status produksi
$productionRows = $pdo->query("
    SELECT status, COUNT(*) AS total
    FROM productions
    GROUP BY status
")->fetchAll();

// Pergerakan stok harian
$movementRows = $pdo->query("
    SELECT
        DATE(created_at) AS movement_date,
        SUM(CASE WHEN type = 'IN' THEN quantity ELSE 0 END) AS stock_in,
        SUM(CASE WHEN type = 'OUT' THEN quantity ELSE 0 END) AS stock_out
    FROM transactions
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
")->fetchAll();

// Susun data 7 hari terakhir
$movementMap = [];

foreach ($movementRows as $row) {
    $movementMap[$row['movement_date']] = $row;
}

$dailyLabels = [];
$dailyIn = [];
$dailyOut = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));

    $dailyLabels[] = date('d M', strtotime($date));

    $dailyIn[] = isset($movementMap[$date])
        ? (int)$movementMap[$date]['stock_in']
        : 0;

    $dailyOut[] = isset($movementMap[$date])
        ? (int)$movementMap[$date]['stock_out']
        : 0;
}

// Pergerakan stok bulanan
$monthlyRows = $pdo->query("
    SELECT
        DATE_FORMAT(created_at, '%Y-%m') AS month_key,
        SUM(CASE WHEN type = 'IN' THEN quantity ELSE 0 END) AS stock_in,
        SUM(CASE WHEN type = 'OUT' THEN quantity ELSE 0 END) AS stock_out
    FROM transactions
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
")->fetchAll();

$monthMap = [];

foreach ($monthlyRows as $row) {
    $monthMap[$row['month_key']] = $row;
}

$monthLabels = [];
$monthIn = [];
$monthOut = [];

for ($i = 5; $i >= 0; $i--) {
    $month = date(
        'Y-m',
        strtotime(date('Y-m-01') . " -$i months")
    );

    $monthLabels[] = date('M Y', strtotime($month . '-01'));

    $monthIn[] = isset($monthMap[$month])
        ? (int)$monthMap[$month]['stock_in']
        : 0;

    $monthOut[] = isset($monthMap[$month])
        ? (int)$monthMap[$month]['stock_out']
        : 0;
}

// Data chart kategori
$categoryLabels = array_column($categoryRows, 'category');
$categoryValues = array_map(
    'intval',
    array_column($categoryRows, 'total_stock')
);

// Data chart produksi
$productionOrder = [
    'Planned',
    'In Progress',
    'Completed'
];

$productionMap = [];

foreach ($productionRows as $row) {
    $productionMap[$row['status']] = (int)$row['total'];
}

$productionValues = [];

foreach ($productionOrder as $status) {
    $productionValues[] = $productionMap[$status] ?? 0;
}

require 'includes/header.php';
?>

<!-- Welcome Section -->
<section class="welcome-row">
    <div>
        <p class="muted">Operational Overview</p>

        <h2>
            Good morning,
            <?= htmlspecialchars($_SESSION['name']) ?> 👋
        </h2>

        <p class="muted">
            Here's what's happening with your inventory and production.
        </p>
    </div>

    <a href="transactions.php" class="btn primary">
        + Add Transaction
    </a>
</section>


<!-- Statistics Cards -->
<div class="stats-grid">

    <article class="stat-card">
        <div class="stat-top">
            <span>Total Items</span>
            <span class="stat-icon">▤</span>
        </div>

        <strong><?= number_format($totalItems) ?></strong>
        <small>Registered inventory items</small>
    </article>


    <article class="stat-card">
        <div class="stat-top">
            <span>Total Stock</span>
            <span class="stat-icon">▦</span>
        </div>

        <strong><?= number_format($totalUnits) ?></strong>
        <small>Units available in inventory</small>
    </article>


    <article class="stat-card">
        <div class="stat-top">
            <span>Low Stock</span>
            <span class="stat-icon warning">!</span>
        </div>

        <strong><?= number_format($lowStock) ?></strong>
        <small>Items below minimum level</small>
    </article>


    <article class="stat-card">
        <div class="stat-top">
            <span>Active Production</span>
            <span class="stat-icon">⚙</span>
        </div>

        <strong><?= number_format($activeProduction) ?></strong>
        <small>Ongoing production orders</small>
    </article>

</div>


<!-- Charts Section -->
<div class="chart-grid">

    <!-- Chart 1: Daily Stock Movement -->
    <section class="panel chart-panel">

        <div class="panel-head">
            <div>
                <h3>Stock Movement Overview</h3>
                <p>Stock in and stock out over the last 7 days</p>
            </div>

            <span class="chart-note">DAILY</span>
        </div>

        <div class="chart-wrap">
            <canvas id="dailyMovementChart"></canvas>
        </div>

    </section>


    <!-- Chart 2: Inventory Category -->
    <section class="panel chart-panel">

        <div class="panel-head">
            <div>
                <h3>Inventory by Category</h3>
                <p>Stock distribution across categories</p>
            </div>
        </div>

        <div class="chart-wrap">
            <canvas id="categoryChart"></canvas>
        </div>

    </section>


    <!-- Chart 3: Monthly Movement -->
    <section class="panel chart-panel">

        <div class="panel-head">
            <div>
                <h3>Monthly Stock Movement</h3>
                <p>Monthly stock movement trends</p>
            </div>

            <span class="chart-note">MONTHLY</span>
        </div>

        <div class="chart-wrap">
            <canvas id="monthlyMovementChart"></canvas>
        </div>

    </section>


    <!-- Chart 4: Production Overview -->
    <section class="panel chart-panel">

        <div class="panel-head">
            <div>
                <h3>Production Overview</h3>
                <p>Production orders by status</p>
            </div>
        </div>

        <div class="chart-wrap">
            <canvas id="productionChart"></canvas>
        </div>

    </section>

</div>


<!-- Recent Transactions and Low Stock -->
<div class="two-col">

    <section class="panel">

        <div class="panel-head">
            <div>
                <h3>Recent Transactions</h3>
                <p>Latest inventory activities</p>
            </div>

            <a href="transactions.php" class="text-link">
                View all →
            </a>
        </div>

        <?php if (!$recent): ?>

            <div class="empty">
                No transactions yet.
            </div>

        <?php else: ?>

            <div class="table-wrap">

                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Type</th>
                            <th>Quantity</th>
                            <th>Date</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($recent as $r): ?>

                            <tr>
                                <td>
                                    <b>
                                        <?= htmlspecialchars($r['item_name']) ?>
                                    </b>

                                    <small class="subcell">
                                        <?= htmlspecialchars($r['sku']) ?>
                                    </small>
                                </td>

                                <td>
                                    <span class="badge <?= $r['type'] === 'IN' ? 'success' : 'neutral' ?>">
                                        <?= $r['type'] === 'IN' ? 'Stock In' : 'Stock Out' ?>
                                    </span>
                                </td>

                                <td>
                                    <?= number_format($r['quantity']) ?>
                                </td>

                                <td>
                                    <?= date('d M Y', strtotime($r['created_at'])) ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>
                </table>

            </div>

        <?php endif; ?>

    </section>


    <section class="panel">

        <div class="panel-head">
            <div>
                <h3>Low Stock Alert</h3>
                <p>Items below minimum stock level</p>
            </div>

            <span class="badge warning">
                <?= count($lowItems) ?> Items
            </span>
        </div>

        <?php if (!$lowItems): ?>

            <div class="empty">
                All inventory levels are healthy.
            </div>

        <?php else: ?>

            <div class="alert-list">

                <?php foreach ($lowItems as $item): ?>

                    <div class="alert-item">

                        <div>
                            <b>
                                <?= htmlspecialchars($item['name']) ?>
                            </b>

                            <small>
                                <?= htmlspecialchars($item['sku']) ?>
                            </small>
                        </div>

                        <div class="stock-number">
                            <b>
                                <?= number_format($item['stock']) ?>
                            </b>

                            <small>
                                Min. <?= number_format($item['min_stock']) ?>
                            </small>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</div>


<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

const axisStyle = {
    grid: {
        color: '#edf1ee',
        drawBorder: false
    },

    ticks: {
        color: '#89968e',
        font: {
            size: 11
        }
    }
};

const legendStyle = {
    position: 'bottom',

    labels: {
        usePointStyle: true,
        pointStyle: 'circle',
        boxWidth: 8,
        padding: 18,
        color: '#65736b'
    }
};


// 1. DAILY STOCK MOVEMENT

new Chart(document.getElementById('dailyMovementChart'), {

    type: 'bar',

    data: {
        labels: <?= json_encode($dailyLabels) ?>,

        datasets: [

            {
                label: 'Stock In',
                data: <?= json_encode($dailyIn) ?>,
                backgroundColor: '#3b9469',
                borderRadius: 5,
                maxBarThickness: 24
            },

            {
                label: 'Stock Out',
                data: <?= json_encode($dailyOut) ?>,
                backgroundColor: '#e6ad65',
                borderRadius: 5,
                maxBarThickness: 24
            }

        ]
    },

    options: {
        responsive: true,
        maintainAspectRatio: false,

        plugins: {
            legend: legendStyle
        },

        scales: {
            x: {
                ...axisStyle,
                grid: {
                    display: false
                }
            },

            y: {
                ...axisStyle,
                beginAtZero: true
            }
        }
    }

});


// 2. INVENTORY BY CATEGORY

new Chart(document.getElementById('categoryChart'), {

    type: 'doughnut',

    data: {
        labels: <?= json_encode($categoryLabels) ?>,

        datasets: [{
            data: <?= json_encode($categoryValues) ?>,

            backgroundColor: [
                '#236b4b',
                '#75a98a',
                '#e6ad65',
                '#8aa7bd',
                '#b9c9bf'
            ],

            borderWidth: 0,
            hoverOffset: 5
        }]
    },

    options: {
        responsive: true,
        maintainAspectRatio: false,

        cutout: '68%',

        plugins: {
            legend: legendStyle
        }
    }

});


// 3. MONTHLY STOCK MOVEMENT

new Chart(document.getElementById('monthlyMovementChart'), {

    type: 'line',

    data: {
        labels: <?= json_encode($monthLabels) ?>,

        datasets: [

            {
                label: 'Stock In',
                data: <?= json_encode($monthIn) ?>,
                borderColor: '#31845d',
                backgroundColor: '#31845d',
                tension: 0.35,
                fill: false,
                pointRadius: 4
            },

            {
                label: 'Stock Out',
                data: <?= json_encode($monthOut) ?>,
                borderColor: '#e0a052',
                backgroundColor: '#e0a052',
                tension: 0.35,
                fill: false,
                pointRadius: 4
            }

        ]
    },

    options: {
        responsive: true,
        maintainAspectRatio: false,

        plugins: {
            legend: legendStyle
        },

        scales: {
            x: {
                ...axisStyle,
                grid: {
                    display: false
                }
            },

            y: {
                ...axisStyle,
                beginAtZero: true
            }
        }
    }

});


// 4. PRODUCTION OVERVIEW

new Chart(document.getElementById('productionChart'), {

    type: 'doughnut',

    data: {
        labels: <?= json_encode($productionOrder) ?>,

        datasets: [{
            data: <?= json_encode($productionValues) ?>,

            backgroundColor: [
                '#e6ad65',
                '#5b91a6',
                '#3b9469'
            ],

            borderWidth: 0,
            hoverOffset: 5
        }]
    },

    options: {
        responsive: true,
        maintainAspectRatio: false,

        cutout: '68%',

        plugins: {
            legend: legendStyle
        }
    }

});

</script>

<?php require 'includes/footer.php'; ?>