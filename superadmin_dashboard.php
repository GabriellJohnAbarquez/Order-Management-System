<?php
session_start();
include 'dbconfig.php';

// Check if logged in as superadmin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: index.php");
    exit();
}

$alert = null;

// Add Cashier
if (isset($_POST['add_cashier'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, status, date_added) VALUES (?, ?, 'cashier', 'active', NOW())");
        $stmt->execute([$username, $hashedPassword]);

        $alert = ['type'=>'success', 'title'=>'Success!', 'message'=>'Cashier account created successfully!'];
    } else {
        $alert = ['type'=>'error', 'title'=>'Error!', 'message'=>'Please fill out all fields.'];
    }
}

// Suspend or Activate Cashier
if (isset($_GET['toggle_status'])) {
    $id = $_GET['toggle_status'];
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $newStatus = $user['status'] === 'active' ? 'suspended' : 'active';
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);

        $alert = ['type'=>'info', 'title'=>'Status Updated', 'message'=>"Cashier account is now $newStatus."];
    }
}

// Fetch all cashiers
$stmt = $pdo->query("SELECT * FROM users WHERE role = 'cashier' ORDER BY date_added DESC");
$cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all products
$productsStmt = $pdo->query("SELECT p.*, u.username AS added_by_user FROM products p LEFT JOIN users u ON p.added_by = u.id ORDER BY p.date_added DESC");
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Superadmin Dashboard | Among Us OMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Turret+Road:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-color: #235685;
            font-family: 'Turret Road', sans-serif;
            color: white;
        }
        .navbar {
            background-color: rgba(0, 0, 0, 0.3) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        .card {
            background-color: rgba(117, 219, 244, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }
        .card-header {
            background-color: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        .table {
            color: white;
        }
        .table-bordered {
            border-color: rgba(255, 255, 255, 0.2);
        }
        .table-hover tbody tr:hover {
            background-color: rgba(117, 219, 244, 0.2);
        }
        .modal-content {
            background-color: #235685;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .form-control, .form-select {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        .btn-primary {
            background-color: #FFDE2A;
            border-color: #FFDE2A;
            color: black;
            font-weight: bold;
        }
        .btn-primary:hover {
            background-color: #e6c315;
            border-color: #e6c315;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark px-3">
    <a class="navbar-brand fw-bold" href="#"> impostor OMS</a>
    <div class="ms-auto">
        <a href="reports.php" class="btn btn-outline-light btn-sm"> View Reports</a>
        <a href="logout.php" class="btn btn-danger btn-sm ms-2">Logout</a>
    </div>
</nav>

<div class="container py-4">
    <h3 class="mb-4"> Superadmin Dashboard</h3>

    <!-- ADD PRODUCT BUTTON -->
    <div class="mb-3 text-end">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
             Add Product
        </button>
    </div>

    <!-- ADD PRODUCT MODAL -->
    <div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Add Product</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <form id="addProductForm">
              <div class="mb-3">
                <label>Name</label>
                <input type="text" class="form-control" name="name" required>
              </div>
              <div class="mb-3">
                <label>Price (₱)</label>
                <input type="number" step="0.01" class="form-control" name="price" required>
              </div>
              <div class="mb-3">
                <label>Image URL (optional)</label>
                <input type="text" class="form-control" name="image_url">
              </div>
              <button type="submit" class="btn btn-success w-100">Add Product</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- PRODUCT LIST -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header text-white">Product List</div>
        <div class="card-body">
            <table class="table table-bordered table-hover align-middle text-center">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Image</th>
                        <th>Added By</th>
                        <th>Date Added</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td><?= $p['id']; ?></td>
                            <td><?= htmlspecialchars($p['name']); ?></td>
                            <td>₱<?= number_format($p['price'], 2); ?></td>
                            <td>
                                <?php if ($p['image']): ?>
                                    <img src="<?= htmlspecialchars($p['image']); ?>" width="50" height="50">
                                <?php else: ?>
                                    <span class="text-muted">No Image</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($p['added_by_user']); ?></td>
                            <td><?= $p['date_added']; ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning edit-product-btn" 
                                        data-id="<?= $p['id']; ?>"
                                        data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES); ?>"
                                        data-price="<?= $p['price']; ?>"
                                        data-image="<?= htmlspecialchars($p['image'], ENT_QUOTES); ?>">
                                    Edit
                                </button>
                                <button class="btn btn-sm btn-danger delete-product-btn" data-id="<?= $p['id']; ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-muted">No products found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Cashier Form -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header text-white">Add New Cashier</div>
        <div class="card-body">
            <form method="POST" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <input type="text" name="username" class="form-control" placeholder="Cashier Username" required>
                </div>
                <div class="col-md-5">
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" name="add_cashier" class="btn btn-success w-100">Add</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cashier List -->
    <div class="card shadow-sm">
        <div class="card-header text-white">Cashier Accounts</div>
        <div class="card-body">
            <table class="table table-bordered table-hover align-middle text-center">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Status</th>
                        <th>Date Added</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($cashiers) > 0): ?>
                    <?php foreach ($cashiers as $cashier): ?>
                        <tr>
                            <td><?= $cashier['id']; ?></td>
                            <td><?= htmlspecialchars($cashier['username']); ?></td>
                            <td>
                                <span class="badge bg-<?= $cashier['status'] === 'active' ? 'success' : 'danger'; ?>">
                                    <?= ucfirst($cashier['status']); ?>
                                </span>
                            </td>
                            <td><?= $cashier['date_added']; ?></td>
                            <td>
                                <button 
                                    class="btn btn-sm btn-outline-<?= $cashier['status'] === 'active' ? 'danger' : 'success'; ?> toggle-btn"
                                    data-id="<?= $cashier['id']; ?>"
                                    data-status="<?= $cashier['status']; ?>">
                                    <?= $cashier['status'] === 'active' ? 'Suspend' : 'Activate'; ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-muted">No cashiers found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {

    // -------------------- PHP Alerts --------------------
    <?php if ($alert): ?>
        Swal.fire({
            icon: '<?= $alert['type'] ?>',
            title: '<?= $alert['title'] ?>',
            text: '<?= $alert['message'] ?>',
            confirmButtonColor: '#0d6efd'
        });
    <?php endif; ?>

    // -------------------- Cashier Status Toggle --------------------
    $('.toggle-btn').on('click', function(e){
        e.preventDefault();
        const cashierId = $(this).data('id');
        const currentStatus = $(this).data('status');
        const action = currentStatus === 'active' ? 'suspend' : 'activate';

        Swal.fire({
            title: `Are you sure you want to ${action} this cashier?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'Cancel',
            confirmButtonColor: action === 'suspend' ? '#dc3545' : '#198754'
        }).then((result)=>{
            if(result.isConfirmed){
                window.location.href = `superadmin_dashboard.php?toggle_status=${cashierId}`;
            }
        });
    });

    // -------------------- Add Product --------------------
    $('#addProductForm').on('submit', function(e){
        e.preventDefault();
        $.post('controller.php', $(this).serialize()+'&action=add_product', function(resp){
            Swal.fire({ icon:'success', title:'Product Added', text:resp.message, timer:1200, showConfirmButton:false })
            .then(()=>location.reload());
        }).fail(function(xhr){
            Swal.fire({ icon:'error', title:'Error', text:xhr.responseText || 'Failed to add product.' });
        });
    });

    // -------------------- Edit Product --------------------
    $('.edit-product-btn').on('click', function(){
        const id = $(this).data('id');
        const name = $(this).data('name');
        const price = $(this).data('price');
        const image = $(this).data('image');

        Swal.fire({
            title: 'Edit Product',
            html: `<input id="swalName" class="swal2-input" placeholder="Name" value="${name}">
                   <input id="swalPrice" type="number" class="swal2-input" placeholder="Price" value="${price}">
                   <input id="swalImage" class="swal2-input" placeholder="Image URL" value="${image}">`,
            confirmButtonText: 'Save',
            focusConfirm: false,
            preConfirm: () => {
                return {
                    id: id,
                    name: document.getElementById('swalName').value,
                    price: document.getElementById('swalPrice').value,
                    image: document.getElementById('swalImage').value
                }
            }
        }).then((result)=>{
            if(result.isConfirmed){
                $.post('controller.php', {action:'edit_product', ...result.value}, function(resp){
                    Swal.fire({icon:'success', title:'Product Updated', text:resp.message, timer:1200, showConfirmButton:false})
                    .then(()=>location.reload());
                }).fail(()=> Swal.fire({icon:'error', title:'Error', text:'Failed to update product.'}));
            }
        });
    });

    // -------------------- Delete Product --------------------
    $('.delete-product-btn').on('click', function(){
        const id = $(this).data('id');
        Swal.fire({
            title: 'Are you sure you want to delete this product?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc3545'
        }).then((result)=>{
            if(result.isConfirmed){
                $.post('controller.php', {action:'delete_product', id:id}, function(resp){
                    Swal.fire({icon:'success', title:'Product Deleted', text:resp.message, timer:1200, showConfirmButton:false})
                    .then(()=>location.reload());
                }).fail(()=> Swal.fire({icon:'error', title:'Error', text:'Failed to delete product.'}));
            }
        });
    });

});
</script>

</body>
</html>
