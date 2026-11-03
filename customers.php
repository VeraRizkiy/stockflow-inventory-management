<?php
require 'includes/auth.php';
require 'config/database.php';

$pageTitle = 'Customer Management';

$success = '';
$error = '';


// =====================================
// TAMBAH CUSTOMER
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {

    $customerName = trim($_POST['customer_name'] ?? '');
    $contactPerson = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($customerName === '') {
        $error = 'Customer name is required.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO customers
                (customer_name, contact_person, phone, email, address)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $customerName,
                $contactPerson,
                $phone,
                $email,
                $address
            ]);

            header('Location: customers.php?success=added');
            exit;

        } catch (PDOException $e) {
            $error = 'Unable to add customer. Please try again.';
        }
    }
}


// =====================================
// EDIT CUSTOMER
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {

    $id = (int)($_POST['id'] ?? 0);
    $customerName = trim($_POST['customer_name'] ?? '');
    $contactPerson = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($customerName === '') {
        $error = 'Customer name is required.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE customers
                SET customer_name = ?,
                    contact_person = ?,
                    phone = ?,
                    email = ?,
                    address = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $customerName,
                $contactPerson,
                $phone,
                $email,
                $address,
                $id
            ]);

            header('Location: customers.php?success=updated');
            exit;

        } catch (PDOException $e) {
            $error = 'Unable to update customer. Please try again.';
        }
    }
}


// =====================================
// HAPUS CUSTOMER
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {

    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        $stmt = $pdo->prepare("
            DELETE FROM customers WHERE id = ?
        ");

        $stmt->execute([$id]);
    }

    header('Location: customers.php?success=deleted');
    exit;
}


// =====================================
// NOTIFIKASI
// =====================================

if (isset($_GET['success'])) {

    if ($_GET['success'] === 'added') {
        $success = 'Customer successfully added.';
    } elseif ($_GET['success'] === 'updated') {
        $success = 'Customer successfully updated.';
    } elseif ($_GET['success'] === 'deleted') {
        $success = 'Customer successfully deleted.';
    }

}


// =====================================
// EDIT MODE
// =====================================

$editCustomer = null;

if (isset($_GET['edit'])) {

    $editId = (int)$_GET['edit'];

    $stmt = $pdo->prepare("
        SELECT * FROM customers WHERE id = ?
    ");

    $stmt->execute([$editId]);

    $editCustomer = $stmt->fetch();

}


// =====================================
// SEARCH CUSTOMER
// =====================================

$search = trim($_GET['search'] ?? '');

if ($search !== '') {

    $stmt = $pdo->prepare("
        SELECT *
        FROM customers
        WHERE customer_name LIKE ?
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

    $customers = $stmt->fetchAll();

} else {

    $customers = $pdo->query("
        SELECT *
        FROM customers
        ORDER BY created_at DESC
    ")->fetchAll();

}


// =====================================
// STATISTIK CUSTOMER
// =====================================

$totalCustomers = (int)$pdo->query("
    SELECT COUNT(*) FROM customers
")->fetchColumn();

$recentCustomers = (int)$pdo->query("
    SELECT COUNT(*)
    FROM customers
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetchColumn();


require 'includes/header.php';
?>


<style>

.customer-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 17px;
    margin-bottom: 20px;
}

.customer-stat {
    background: white;
    padding: 20px;
    border: 1px solid #edf0ed;
    border-radius: 12px;
}

.customer-stat span {
    color: #78857d;
    font-size: 13px;
}

.customer-stat h2 {
    font-size: 28px;
    margin: 10px 0 0;
    color: #236b4b;
}

.customer-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.customer-field {
    display: flex;
    flex-direction: column;
    gap: 7px;
}

.customer-field label {
    font-size: 12px;
    font-weight: 700;
    color: #56645b;
}

.customer-field input,
.customer-field textarea {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #e2e9e3;
    border-radius: 7px;
    font-family: inherit;
    font-size: 13px;
    outline: none;
    box-sizing: border-box;
}

.customer-field input:focus,
.customer-field textarea:focus {
    border-color: #3b9469;
}

.customer-field.full {
    grid-column: 1 / -1;
}

.customer-field textarea {
    min-height: 85px;
    resize: vertical;
}

.customer-actions {
    display: flex;
    gap: 9px;
    margin-top: 18px;
}

.customer-search {
    display: flex;
    gap: 9px;
}

.customer-search input {
    width: 230px;
    padding: 10px 12px;
    border: 1px solid #e2e9e3;
    border-radius: 7px;
    outline: none;
}

.customer-table td {
    vertical-align: middle;
}

.customer-name {
    font-weight: 700;
    color: #26372d;
}

.customer-contact {
    font-size: 11px;
    color: #89968e;
    margin-top: 4px;
}

.customer-delete {
    border: none;
    background: #fff0ed;
    color: #cf6555;
    padding: 7px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 11px;
}

.customer-edit {
    display: inline-block;
    text-decoration: none;
    background: #edf5ef;
    color: #347b54;
    padding: 7px 10px;
    border-radius: 6px;
    font-size: 11px;
    margin-right: 5px;
}

.customer-alert {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 13px;
}

.customer-alert.success {
    background: #edf8f0;
    color: #347b54;
}

.customer-alert.error {
    background: #fff0ed;
    color: #c45b4e;
}

@media(max-width: 700px) {

    .customer-stats {
        grid-template-columns: 1fr;
    }

    .customer-form-grid {
        grid-template-columns: 1fr;
    }

    .customer-field.full {
        grid-column: auto;
    }

    .customer-search input {
        width: 100%;
    }

}

</style>


<!-- PAGE INTRO -->

<section class="welcome-row">

    <div>
        <p class="muted">CUSTOMER DIRECTORY</p>

        <h2>Manage Your Customers</h2>

        <p class="muted">
            Manage customer information and business contacts.
        </p>
    </div>

</section>


<!-- NOTIFICATION -->

<?php if ($success): ?>

    <div class="customer-alert success">
        ✓ <?= htmlspecialchars($success) ?>
    </div>

<?php endif; ?>


<?php if ($error): ?>

    <div class="customer-alert error">
        <?= htmlspecialchars($error) ?>
    </div>

<?php endif; ?>


<!-- STATISTICS -->

<div class="customer-stats">

    <div class="customer-stat">
        <span>Total Customers</span>

        <h2>
            <?= number_format($totalCustomers) ?>
        </h2>
    </div>

    <div class="customer-stat">
        <span>New Customers · Last 30 Days</span>

        <h2>
            <?= number_format($recentCustomers) ?>
        </h2>
    </div>

</div>


<!-- ADD / EDIT FORM -->

<section class="panel">

    <div class="panel-head">

        <div>
            <h3>
                <?= $editCustomer ? 'Edit Customer' : 'Add New Customer' ?>
            </h3>

            <p>
                <?= $editCustomer
                    ? 'Update customer information.'
                    : 'Enter the customer details below.' ?>
            </p>
        </div>

    </div>


    <form method="POST" action="customers.php<?= $editCustomer ? '?edit=' . (int)$editCustomer['id'] : '' ?>">

        <?php if ($editCustomer): ?>

            <input
                type="hidden"
                name="id"
                value="<?= (int)$editCustomer['id'] ?>"
            >

        <?php endif; ?>


        <div class="customer-form-grid">

            <div class="customer-field">

                <label>Customer Name *</label>

                <input
                    type="text"
                    name="customer_name"
                    placeholder="Enter customer or company name"
                    value="<?= htmlspecialchars($editCustomer['customer_name'] ?? '') ?>"
                    required
                >

            </div>


            <div class="customer-field">

                <label>Contact Person</label>

                <input
                    type="text"
                    name="contact_person"
                    placeholder="Enter contact person"
                    value="<?= htmlspecialchars($editCustomer['contact_person'] ?? '') ?>"
                >

            </div>


            <div class="customer-field">

                <label>Phone Number</label>

                <input
                    type="tel"
                    name="phone"
                    placeholder="Enter phone number"
                    value="<?= htmlspecialchars($editCustomer['phone'] ?? '') ?>"
                >

            </div>


            <div class="customer-field">

                <label>Email Address</label>

                <input
                    type="email"
                    name="email"
                    placeholder="Enter email address"
                    value="<?= htmlspecialchars($editCustomer['email'] ?? '') ?>"
                >

            </div>


            <div class="customer-field full">

                <label>Address</label>

                <textarea
                    name="address"
                    placeholder="Enter customer address"
                ><?= htmlspecialchars($editCustomer['address'] ?? '') ?></textarea>

            </div>

        </div>


        <div class="customer-actions">

            <button
                type="submit"
                name="<?= $editCustomer ? 'update_customer' : 'add_customer' ?>"
                class="btn primary"
            >
                <?= $editCustomer ? 'Save Changes' : '+ Add Customer' ?>
            </button>


            <?php if ($editCustomer): ?>

                <a href="customers.php" class="btn">
                    Cancel
                </a>

            <?php endif; ?>

        </div>

    </form>

</section>


<!-- CUSTOMER TABLE -->

<section class="panel" style="margin-top:20px;">

    <div class="panel-head">

        <div>
            <h3>Customer Directory</h3>

            <p>
                <?= count($customers) ?> customer records found
            </p>
        </div>


        <form method="GET" class="customer-search">

            <input
                type="text"
                name="search"
                placeholder="Search customer..."
                value="<?= htmlspecialchars($search) ?>"
            >

            <button type="submit" class="btn primary">
                Search
            </button>

            <?php if ($search !== ''): ?>

                <a href="customers.php" class="btn">
                    Reset
                </a>

            <?php endif; ?>

        </form>

    </div>


    <div class="table-wrap">

        <table class="customer-table">

            <thead>

                <tr>
                    <th>Customer</th>
                    <th>Contact Person</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Date Added</th>
                    <th>Actions</th>
                </tr>

            </thead>


            <tbody>

                <?php if (!$customers): ?>

                    <tr>
                        <td colspan="6" class="empty">
                            No customers found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($customers as $customer): ?>

                        <tr>

                            <td>
                                <div class="customer-name">
                                    <?= htmlspecialchars($customer['customer_name']) ?>
                                </div>

                                <div class="customer-contact">
                                    <?= htmlspecialchars($customer['address'] ?? '') ?>
                                </div>
                            </td>


                            <td>
                                <?= htmlspecialchars($customer['contact_person'] ?: '-') ?>
                            </td>


                            <td>
                                <?= htmlspecialchars($customer['phone'] ?: '-') ?>
                            </td>


                            <td>
                                <?= htmlspecialchars($customer['email'] ?: '-') ?>
                            </td>


                            <td>
                                <?= date('d M Y', strtotime($customer['created_at'])) ?>
                            </td>


                            <td>

                                <a
                                    href="customers.php?edit=<?= (int)$customer['id'] ?>"
                                    class="customer-edit"
                                >
                                    Edit
                                </a>


                                <form
                                    method="POST"
                                    action="customers.php"
                                    style="display:inline;"
                                    onsubmit="return confirm('Are you sure you want to delete this customer?')"
                                >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int)$customer['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="delete_customer"
                                        class="customer-delete"
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