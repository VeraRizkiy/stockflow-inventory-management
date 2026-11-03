<?php
require 'includes/auth.php';
require 'config/database.php';

$pageTitle = 'Supplier Management';

$success = '';
$error = '';


// =====================================
// TAMBAH SUPPLIER
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_supplier'])) {

    $supplierName = trim($_POST['supplier_name'] ?? '');
    $contactPerson = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($supplierName === '') {
        $error = 'Supplier name is required.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO suppliers
                (supplier_name, contact_person, phone, email, address)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $supplierName,
                $contactPerson,
                $phone,
                $email,
                $address
            ]);

            header('Location: suppliers.php?success=added');
            exit;

        } catch (PDOException $e) {
            $error = 'Unable to add supplier. Please try again.';
        }
    }
}


// =====================================
// EDIT SUPPLIER
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_supplier'])) {

    $id = (int)($_POST['id'] ?? 0);
    $supplierName = trim($_POST['supplier_name'] ?? '');
    $contactPerson = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($supplierName === '') {
        $error = 'Supplier name is required.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE suppliers
                SET supplier_name = ?,
                    contact_person = ?,
                    phone = ?,
                    email = ?,
                    address = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $supplierName,
                $contactPerson,
                $phone,
                $email,
                $address,
                $id
            ]);

            header('Location: suppliers.php?success=updated');
            exit;

        } catch (PDOException $e) {
            $error = 'Unable to update supplier. Please try again.';
        }
    }
}


// =====================================
// HAPUS SUPPLIER
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_supplier'])) {

    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        $stmt = $pdo->prepare("
            DELETE FROM suppliers WHERE id = ?
        ");

        $stmt->execute([$id]);
    }

    header('Location: suppliers.php?success=deleted');
    exit;
}


// =====================================
// NOTIFIKASI
// =====================================

if (isset($_GET['success'])) {

    if ($_GET['success'] === 'added') {
        $success = 'Supplier successfully added.';
    } elseif ($_GET['success'] === 'updated') {
        $success = 'Supplier successfully updated.';
    } elseif ($_GET['success'] === 'deleted') {
        $success = 'Supplier successfully deleted.';
    }

}


// =====================================
// EDIT MODE
// =====================================

$editSupplier = null;

if (isset($_GET['edit'])) {

    $editId = (int)$_GET['edit'];

    $stmt = $pdo->prepare("
        SELECT * FROM suppliers WHERE id = ?
    ");

    $stmt->execute([$editId]);

    $editSupplier = $stmt->fetch();

}


// =====================================
// SEARCH SUPPLIER
// =====================================

$search = trim($_GET['search'] ?? '');

if ($search !== '') {

    $stmt = $pdo->prepare("
        SELECT *
        FROM suppliers
        WHERE supplier_name LIKE ?
           OR contact_person LIKE ?
           OR email LIKE ?
           OR phone LIKE ?
        ORDER BY created_at DESC
    ");

    $keyword = "%$search%";

    $stmt->execute([
        $keyword,
        $keyword,
        $keyword,
        $keyword
    ]);

    $suppliers = $stmt->fetchAll();

} else {

    $suppliers = $pdo->query("
        SELECT *
        FROM suppliers
        ORDER BY created_at DESC
    ")->fetchAll();

}


// =====================================
// STATISTIK SUPPLIER
// =====================================

$totalSuppliers = (int)$pdo->query("
    SELECT COUNT(*) FROM suppliers
")->fetchColumn();

$recentSuppliers = (int)$pdo->query("
    SELECT COUNT(*)
    FROM suppliers
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetchColumn();


require 'includes/header.php';
?>


<style>

.supplier-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 17px;
    margin-bottom: 20px;
}

.supplier-stat {
    background: white;
    padding: 20px;
    border: 1px solid #edf0ed;
    border-radius: 12px;
}

.supplier-stat span {
    color: #78857d;
    font-size: 13px;
}

.supplier-stat h2 {
    font-size: 28px;
    margin: 10px 0 0;
    color: #236b4b;
}

.supplier-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.supplier-field {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.supplier-field label {
    font-size: 12px;
    font-weight: 700;
    color: #56645b;
}

.supplier-field input,
.supplier-field textarea {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #e2e9e3;
    border-radius: 7px;
    font-family: inherit;
    font-size: 13px;
    outline: none;
    box-sizing: border-box;
}

.supplier-field input:focus,
.supplier-field textarea:focus {
    border-color: #3b9469;
}

.supplier-field.full {
    grid-column: 1 / -1;
}

.supplier-field textarea {
    min-height: 85px;
    resize: vertical;
}

.supplier-actions {
    display: flex;
    gap: 9px;
    margin-top: 18px;
}

.supplier-search {
    display: flex;
    gap: 9px;
}

.supplier-search input {
    width: 230px;
    padding: 10px 12px;
    border: 1px solid #e2e9e3;
    border-radius: 7px;
    outline: none;
}

.supplier-table td {
    vertical-align: middle;
}

.supplier-name {
    font-weight: 700;
    color: #26372d;
}

.supplier-contact {
    font-size: 11px;
    color: #89968e;
    margin-top: 4px;
}

.supplier-delete {
    border: none;
    background: #fff0ed;
    color: #cf6555;
    padding: 7px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 11px;
}

.supplier-edit {
    display: inline-block;
    text-decoration: none;
    background: #edf5ef;
    color: #347b54;
    padding: 7px 10px;
    border-radius: 6px;
    font-size: 11px;
    margin-right: 5px;
}

.supplier-alert {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 13px;
}

.supplier-alert.success {
    background: #edf8f0;
    color: #347b54;
}

.supplier-alert.error {
    background: #fff0ed;
    color: #c45b4e;
}

@media(max-width: 700px) {

    .supplier-stats {
        grid-template-columns: 1fr;
    }

    .supplier-form-grid {
        grid-template-columns: 1fr;
    }

    .supplier-field.full {
        grid-column: auto;
    }

    .supplier-search input {
        width: 100%;
    }

}

</style>


<!-- PAGE INTRO -->

<section class="welcome-row">

    <div>
        <p class="muted">SUPPLIER DIRECTORY</p>

        <h2>Manage Your Suppliers</h2>

        <p class="muted">
            Manage supplier information and business contacts.
        </p>
    </div>

</section>


<!-- NOTIFICATION -->

<?php if ($success): ?>

    <div class="supplier-alert success">
        ✓ <?= htmlspecialchars($success) ?>
    </div>

<?php endif; ?>


<?php if ($error): ?>

    <div class="supplier-alert error">
        <?= htmlspecialchars($error) ?>
    </div>

<?php endif; ?>


<!-- STATISTICS -->

<div class="supplier-stats">

    <div class="supplier-stat">
        <span>Total Suppliers</span>

        <h2>
            <?= number_format($totalSuppliers) ?>
        </h2>
    </div>

    <div class="supplier-stat">
        <span>New Suppliers · Last 30 Days</span>

        <h2>
            <?= number_format($recentSuppliers) ?>
        </h2>
    </div>

</div>


<!-- ADD / EDIT FORM -->

<section class="panel">

    <div class="panel-head">

        <div>
            <h3>
                <?= $editSupplier ? 'Edit Supplier' : 'Add New Supplier' ?>
            </h3>

            <p>
                <?= $editSupplier
                    ? 'Update supplier information.'
                    : 'Enter the supplier details below.' ?>
            </p>
        </div>

    </div>


    <form method="POST" action="suppliers.php<?= $editSupplier ? '?edit=' . (int)$editSupplier['id'] : '' ?>">

        <?php if ($editSupplier): ?>

            <input
                type="hidden"
                name="id"
                value="<?= (int)$editSupplier['id'] ?>"
            >

        <?php endif; ?>


        <div class="supplier-form-grid">

            <div class="supplier-field">

                <label>Supplier Name *</label>

                <input
                    type="text"
                    name="supplier_name"
                    placeholder="Enter supplier company name"
                    value="<?= htmlspecialchars($editSupplier['supplier_name'] ?? '') ?>"
                    required
                >

            </div>


            <div class="supplier-field">

                <label>Contact Person</label>

                <input
                    type="text"
                    name="contact_person"
                    placeholder="Enter contact person"
                    value="<?= htmlspecialchars($editSupplier['contact_person'] ?? '') ?>"
                >

            </div>


            <div class="supplier-field">

                <label>Phone Number</label>

                <input
                    type="tel"
                    name="phone"
                    placeholder="Enter phone number"
                    value="<?= htmlspecialchars($editSupplier['phone'] ?? '') ?>"
                >

            </div>


            <div class="supplier-field">

                <label>Email Address</label>

                <input
                    type="email"
                    name="email"
                    placeholder="Enter email address"
                    value="<?= htmlspecialchars($editSupplier['email'] ?? '') ?>"
                >

            </div>


            <div class="supplier-field full">

                <label>Address</label>

                <textarea
                    name="address"
                    placeholder="Enter supplier address"
                ><?= htmlspecialchars($editSupplier['address'] ?? '') ?></textarea>

            </div>

        </div>


        <div class="supplier-actions">

            <button
                type="submit"
                name="<?= $editSupplier ? 'update_supplier' : 'add_supplier' ?>"
                class="btn primary"
            >
                <?= $editSupplier ? 'Save Changes' : '+ Add Supplier' ?>
            </button>


            <?php if ($editSupplier): ?>

                <a href="suppliers.php" class="btn">
                    Cancel
                </a>

            <?php endif; ?>

        </div>

    </form>

</section>


<!-- SUPPLIER TABLE -->

<section class="panel" style="margin-top:20px;">

    <div class="panel-head">

        <div>
            <h3>Supplier Directory</h3>

            <p>
                <?= count($suppliers) ?> supplier records found
            </p>
        </div>


        <form method="GET" class="supplier-search">

            <input
                type="text"
                name="search"
                placeholder="Search supplier..."
                value="<?= htmlspecialchars($search) ?>"
            >

            <button type="submit" class="btn primary">
                Search
            </button>

            <?php if ($search !== ''): ?>

                <a href="suppliers.php" class="btn">
                    Reset
                </a>

            <?php endif; ?>

        </form>

    </div>


    <div class="table-wrap">

        <table class="supplier-table">

            <thead>

                <tr>
                    <th>Supplier</th>
                    <th>Contact Person</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Date Added</th>
                    <th>Actions</th>
                </tr>

            </thead>


            <tbody>

                <?php if (!$suppliers): ?>

                    <tr>
                        <td colspan="6" class="empty">
                            No suppliers found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($suppliers as $supplier): ?>

                        <tr>

                            <td>
                                <div class="supplier-name">
                                    <?= htmlspecialchars($supplier['supplier_name']) ?>
                                </div>

                                <div class="supplier-contact">
                                    <?= htmlspecialchars($supplier['address'] ?? '') ?>
                                </div>
                            </td>


                            <td>
                                <?= htmlspecialchars($supplier['contact_person'] ?: '-') ?>
                            </td>


                            <td>
                                <?= htmlspecialchars($supplier['phone'] ?: '-') ?>
                            </td>


                            <td>
                                <?= htmlspecialchars($supplier['email'] ?: '-') ?>
                            </td>


                            <td>
                                <?= date('d M Y', strtotime($supplier['created_at'])) ?>
                            </td>


                            <td>

                                <a
                                    href="suppliers.php?edit=<?= (int)$supplier['id'] ?>"
                                    class="supplier-edit"
                                >
                                    Edit
                                </a>


                                <form
                                    method="POST"
                                    action="suppliers.php"
                                    style="display:inline;"
                                    onsubmit="return confirm('Are you sure you want to delete this supplier?')"
                                >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$supplier['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="delete_supplier"
                                        class="supplier-delete"
                                    >
                                        Delete
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


<?php require 'includes/footer.php'; ?>