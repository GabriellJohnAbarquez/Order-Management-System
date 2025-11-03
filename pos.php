<?php
session_start();
require_once 'dbconfig.php';

// Access control
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['cashier', 'superadmin'])) {
    header("Location: index.php");
    exit();
}

// Fetch products
$products = $pdo->query("SELECT * FROM products ORDER BY date_added DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch orders
$orders = $pdo->query("
    SELECT o.id, o.cashier_id, o.total, o.payment_method, o.amount_paid, o.amount_change, o.date_added, u.username AS cashier_name
    FROM orders o
    LEFT JOIN users u ON o.cashier_id = u.id
    ORDER BY o.date_added DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch order items
$orderItemsRaw = $pdo->query("
    SELECT oi.order_id, oi.product_id, oi.quantity, oi.price, p.name AS product_name
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    ORDER BY oi.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$orderItemsGrouped = [];
foreach ($orderItemsRaw as $item) {
    $oid = $item['order_id'];
    if (!isset($orderItemsGrouped[$oid])) $orderItemsGrouped[$oid] = [];
    $orderItemsGrouped[$oid][] = $item;
}

$orders_with_items = [];
foreach ($orders as $o) {
    $oid = $o['id'];
    $orders_with_items[$oid] = $orderItemsGrouped[$oid] ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>POS | Among Us OMS</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Turret+Road:wght@400;700&display=swap" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
.product-card img {
    height: 150px;
    object-fit: cover;
    border-radius: 0.5rem;
}
.cursor-pointer { cursor: pointer; }

#productsGrid .product-card { transition: transform 0.2s; }
#productsGrid .product-card:hover { transform: scale(1.03); box-shadow: 0 4px 15px rgba(0,0,0,0.15); }

#cartTable th, #cartTable td { vertical-align: middle; }
#cartTable td { color: black; }
#cartTable .cart-qty {
    color: black;
}
#cartTable tbody tr { transition: background 0.15s; }
#cartTable tbody tr:hover { background-color: rgba(0,123,255,0.05); }

.table-striped > tbody > tr:nth-of-type(odd) { background-color: rgba(117, 219, 244, 0.05); }
.table-dark th { background-color: rgba(0, 0, 0, 0.2) !important; color:#fff; }

.modal-content {
    background-color: #235685;
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white; /* Reverted to white */
}
.modal-header {
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    color: white;
}
.modal-title {
    color: white;
}
.modal-content .form-control, .modal-content .form-select {
    color: black; /* Set to black for visibility */
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
.table-light th {
    color: black;
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="#"> impostor OMS - POS</a>
        <div class="d-flex align-items-center">
            <span class="text-white me-3"> <?= htmlspecialchars($_SESSION['username']) ?> (<?= htmlspecialchars($_SESSION['role']) ?>)</span>
            <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <!-- ADD PRODUCT BUTTON -->
    <?php if (in_array($_SESSION['role'], ['superadmin','cashier'])): ?>
    <div class="mb-3 text-end">
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
             Add Product
        </button>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- PRODUCTS GRID -->
        <div class="col-lg-8">
            <h4 class="mb-3 fw-bold">Available Products</h4>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3" id="productsGrid">
                <?php if($products): foreach($products as $p): ?>
                <div class="col product-card-wrapper" data-id="<?= $p['id'] ?>">
                    <div class="card product-card h-100 shadow-sm">
                        <?php if(!empty($p['image'])): ?>
                        <img src="<?= htmlspecialchars($p['image']) ?>" class="card-img-top" alt="<?= htmlspecialchars($p['name']) ?>">
                        <?php else: ?>
                        <div class="bg-secondary text-white text-center p-5">No Image</div>
                        <?php endif; ?>
                        <div class="card-body text-center">
                            <h6 class="card-title mb-1 fw-bold"><?= htmlspecialchars($p['name']) ?></h6>
                            <p class="text-warning fw-semibold mb-2">₱<?= number_format($p['price'],2) ?></p>
                            <div class="d-flex justify-content-center gap-2 mb-2">
                                <input type="number" min="1" value="1" class="form-control form-control-sm qty-input" style="width:80px;">
                                <button class="btn btn-sm btn-success addToCartBtn"
                                    data-id="<?= $p['id'] ?>"
                                    data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                                    data-price="<?= htmlspecialchars($p['price']) ?>">
                                    Add
                                </button>
                            </div>
                            <?php if($_SESSION['role']==='superadmin'): ?>
                            <div class="d-flex justify-content-center gap-1">
                                <button class="btn btn-sm btn-warning edit-product-btn" data-id="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>" data-price="<?= htmlspecialchars($p['price']) ?>" data-image="<?= htmlspecialchars($p['image'], ENT_QUOTES) ?>">Edit</button>
                                <button class="btn btn-sm btn-danger delete-product-btn" data-id="<?= $p['id'] ?>">Delete</button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; else: ?>
                <p class="text-muted">No products available.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- CART PANEL -->
        <div class="col-lg-4">
            <h4 class="mb-3 fw-bold">Shopping Cart</h4>
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="table-responsive mb-3" style="max-height:360px; overflow:auto;">
                        <table class="table table-sm table-bordered text-center" id="cartTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Item</th>
                                    <th style="width:70px">Qty</th>
                                    <th style="width:90px">Total</th>
                                    <th style="width:40px"></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0 text-warning fw-bold">Total: ₱<span id="cartTotal">0.00</span></h5>
                        <button id="clearCartBtn" class="btn btn-outline-secondary btn-sm">Clear</button>
                    </div>
                    <!-- Checkout now opens the Payment modal -->
                    <button id="checkoutBtn" class="btn btn-success w-100 fw-bold" disabled data-bs-toggle="modal" data-bs-target="#paymentModal">Checkout</button>
                </div>
            </div>
            <div class="mt-3 text-light small fst-italic">
                Tip: Change quantities directly in the cart. Items persist until checkout or Clear.
            </div>
        </div>
    </div>

    <!-- ORDER HISTORY -->
    <hr class="my-4">
    <h4 class="mb-3 fw-bold">Order History</h4>
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Order ID</th>
                            <th>Cashier</th>
                            <th>Total (₱)</th>
                            <th>Payment Method</th>
                            <th>Cash Given</th>
                            <th>Change</th>
                            <th>Date</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($orders): foreach($orders as $o): ?>
                        <tr>
                            <td><?= (int)$o['id'] ?></td>
                            <td><?= htmlspecialchars($o['cashier_name']??'N/A') ?></td>
                            <td><?= number_format($o['total'],2) ?></td>
                            <td><?= htmlspecialchars($o['payment_method']) ?></td>
                            <td><?= number_format($o['amount_paid'], 2) ?></td>
                            <td><?= number_format($o['amount_change'], 2) ?></td>
                            <td><?= htmlspecialchars($o['date_added']) ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary view-order-btn" data-order-id="<?= $o['id'] ?>">View</button>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="8" class="text-center text-muted">No orders yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODALS -->
<!-- Add Product Modal -->
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

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="editProductForm">
          <input type="hidden" name="id" id="editProductId">
          <div class="mb-3">
            <label>Name</label>
            <input type="text" class="form-control" name="name" id="editProductName" required>
          </div>
          <div class="mb-3">
            <label>Price (₱)</label>
            <input type="number" step="0.01" class="form-control" name="price" id="editProductPrice" required>
          </div>
          <div class="mb-3">
            <label>Image URL (optional)</label>
            <input type="text" class="form-control" name="image_url" id="editProductImage">
          </div>
          <button type="submit" class="btn btn-success w-100">Update Product</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Payment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Total</label>
            <div class="form-control bg-light">₱<span id="paymentTotalDisplay">0.00</span></div>
        </div>
        <div class="mb-3">
            <label for="paymentMethod" class="form-label">Payment Method</label>
            <select class="form-select" id="paymentMethod">
                <option value="Cash" selected>Cash</option>
                <option value="Card">Card</option>
                <option value="GCash">GCash</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div id="cashFields">
            <div class="mb-3">
                <label for="paymentCash" class="form-label">Cash Given (₱)</label>
                <input type="number" min="0" step="0.01" class="form-control" id="paymentCash" placeholder="Enter cash amount">
            </div>
            <div class="mb-3">
                <label class="form-label">Change</label>
                <div class="form-control bg-light">₱<span id="paymentChangeDisplay">0.00</span></div>
            </div>
        </div>
        <div class="small text-light fst-italic" id="paymentHint">For cash payments, ensure the amount given is equal to or greater than the total.</div>
      </div>
      <div class="modal-footer">
        <button type="button" id="cancelPaymentBtn" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirmPaymentBtn" class="btn btn-success" disabled>Confirm Payment</button>
      </div>
    </div>
  </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Order Receipt</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="receiptContent">
        <!-- Receipt content will be injected here -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" id="printReceiptBtn" class="btn btn-primary">Print</button>
      </div>
    </div>
  </div>
</div>

<!-- Order Details Modal -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Order Details — <span id="modalOrderId"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="orderItemsContainer"></div>
        <hr>
        <div id="orderDetailsPayment"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// -------------------- CLIENT-SIDE CART (localStorage) --------------------
const STORAGE_KEY = 'oms_cart';

function loadCart() {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return [];
    try { return JSON.parse(raw); } catch(e) { console.error('Cart parse error', e); return []; }
}

function saveCart(cart) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(cart));
}

function findCartItem(cart, id) {
    return cart.findIndex(it => String(it.id) === String(id));
}

function renderCart() {
    const cart = loadCart();
    const tbody = $('#cartTable tbody').empty();
    let total = 0;

    cart.forEach((it, idx) => {
        const itemTotal = (parseFloat(it.price) * parseInt(it.qty));
        total += itemTotal;

        const row = $(`
            <tr class="cart-row">
                <td class="text-start">${$('<div>').text(it.name).html()}</td>
                <td>
                    <input type="number" min="1" value="${it.qty}" class="form-control form-control-sm cart-qty" data-index="${idx}" style="width:70px;">
                </td>
                <td>₱${itemTotal.toFixed(2)}</td>
                <td><button class="btn btn-sm btn-danger remove-cart-item" data-index="${idx}">✖</button></td>
            </tr>
        `);
        tbody.append(row);
    });

    $('#cartTotal').text(total.toFixed(2));
    $('#checkoutBtn').prop('disabled', cart.length === 0);
}

// -------------------- CART ACTIONS --------------------
$(document).on('click', '.addToCartBtn', function() {
    const btn = $(this);
    const id = btn.data('id');
    const name = btn.data('name');
    const price = parseFloat(btn.data('price'));
    const qtyInput = btn.closest('.card-body').find('.qty-input');
    const qty = Math.max(1, parseInt(qtyInput.val()) || 1);

    let cart = loadCart();
    const idx = findCartItem(cart, id);
    if (idx >= 0) {
        cart[idx].qty = parseInt(cart[idx].qty) + qty;
    } else {
        cart.push({ id: id, name: name, price: price, qty: qty });
    }
    saveCart(cart);
    renderCart();

    Swal.fire({
        icon: 'success',
        title: 'Added to Cart',
        text: `${name} x${qty} added.`,
        showConfirmButton: false,
        timer: 900
    });
});

$(document).on('click', '.remove-cart-item', function() {
    const idx = parseInt($(this).data('index'));
    let cart = loadCart();
    const removed = cart.splice(idx, 1)[0];
    saveCart(cart);
    renderCart();

    Swal.fire({
        icon: 'info',
        title: 'Removed',
        text: `${removed.name} removed from cart.`,
        showConfirmButton: false,
        timer: 900
    });
});

$(document).on('input', '.cart-qty', function() {
    const idx = parseInt($(this).data('index'));
    let newQty = parseInt($(this).val());
    if (isNaN(newQty) || newQty < 1) newQty = 1;
    let cart = loadCart();
    if (cart[idx]) {
        cart[idx].qty = newQty;
        saveCart(cart);
        renderCart();

        Swal.fire({ icon:'info', title:'Quantity Updated', text:`${cart[idx].name} quantity is now ${newQty}`, showConfirmButton:false, timer:700 });
    }
});

$('#clearCartBtn').on('click', function() {
    Swal.fire({
        title: 'Clear cart?',
        text: 'This will remove all items.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, clear it',
        cancelButtonText: 'Cancel'
    }).then(res => {
        if (res.isConfirmed) {
            localStorage.removeItem(STORAGE_KEY);
            renderCart();
            Swal.fire({ icon:'success', title:'Cart Cleared', showConfirmButton:false, timer:900 });
        }
    });
});

// -------------------- PAYMENT FLOW --------------------
let paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));

function openPaymentModal() {
    const cart = loadCart();
    if (!cart || cart.length === 0) return;

    const total = parseFloat($('#cartTotal').text()) || 0;
    $('#paymentTotalDisplay').text(total.toFixed(2));
    $('#paymentCash').val('');
    $('#paymentChangeDisplay').text('0.00');
    $('#paymentMethod').val('Cash'); // Reset to cash
    handlePaymentMethodChange(); // Trigger UI update
    paymentModal.show();
}

function handlePaymentMethodChange() {
    const method = $('#paymentMethod').val();
    const total = parseFloat($('#cartTotal').text()) || 0;
    const cashGiven = parseFloat($('#paymentCash').val()) || 0;

    if (method === 'Cash') {
        $('#cashFields').show();
        $('#paymentHint').show();
        $('#confirmPaymentBtn').prop('disabled', cashGiven < total);
    } else {
        $('#cashFields').hide();
        $('#paymentHint').hide();
        $('#confirmPaymentBtn').prop('disabled', false); // Enable for non-cash
    }
}

// Update change and enable/disable confirm button for cash payments
$('#paymentCash').on('input', function() {
    const cashGiven = parseFloat($(this).val()) || 0;
    const total = parseFloat($('#cartTotal').text()) || 0;
    const change = cashGiven - total;

    $('#paymentChangeDisplay').text(change < 0 ? '0.00' : change.toFixed(2));
    
    if ($('#paymentMethod').val() === 'Cash') {
        $('#confirmPaymentBtn').prop('disabled', cashGiven < total);
    }
});

// Handle payment method changes
$('#paymentMethod').on('change', handlePaymentMethodChange);

// Open payment modal
$('#checkoutBtn').on('click', function(e){
    e.preventDefault();
    openPaymentModal();
});

// Confirm payment -> send checkout to controller
$('#confirmPaymentBtn').on('click', function() {
    const cart = loadCart();
    if (!cart || cart.length === 0) {
        Swal.fire({ icon:'error', title:'Empty cart', text:'Cart is empty.' });
        paymentModal.hide();
        return;
    }

    const paymentMethod = $('#paymentMethod').val();
    const total = parseFloat($('#cartTotal').text()) || 0;
    let amountPaid = total;
    let amountChange = 0;

    if (paymentMethod === 'Cash') {
        amountPaid = parseFloat($('#paymentCash').val()) || 0;
        if (amountPaid < total) {
            Swal.fire({ icon:'error', title:'Insufficient cash', text:'Cash given is less than total.' });
            return;
        }
        amountChange = amountPaid - total;
    }

    $('#confirmPaymentBtn').prop('disabled', true).text('Processing...');

    $.ajax({
        url: 'controller.php',
        method: 'POST',
        dataType: 'json',
        data: {
            action: 'checkout',
            cart: JSON.stringify(cart),
            payment_method: paymentMethod,
            amount_paid: amountPaid,
            amount_change: amountChange
        },
        success: function(resp) {
            $('#confirmPaymentBtn').prop('disabled', false).text('Confirm Payment');
            if (resp.success) {
                localStorage.removeItem(STORAGE_KEY);
                renderCart();
                paymentModal.hide();
                
                let successMsg = `Order #${resp.order_id ?? ''} placed via ${paymentMethod}.<br>Total: ₱${total.toFixed(2)}`;
                if (paymentMethod === 'Cash') {
                    successMsg += `<br>Cash: ₱${amountPaid.toFixed(2)}<br>Change: ₱${amountChange.toFixed(2)}`;
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Transaction Complete',
                    html: successMsg,
                    showConfirmButton: true
                }).then(()=> {
                    location.reload();
                });
            } else {
                Swal.fire({ icon:'error', title:'Error', text: resp.message || 'Checkout failed.' });
            }
        },
        error: function(xhr) {
            $('#confirmPaymentBtn').prop('disabled', false).text('Confirm Payment');
            const msg = xhr.responseText || 'Failed to process order. Try again.';
            Swal.fire({ icon:'error', title:'Error', text: msg });
        }
    });
});

// Cancel payment resets fields
$('#cancelPaymentBtn').on('click', function() {
    $('#paymentCash').val('');
    $('#paymentChangeDisplay').text('0.00');
    $('#confirmPaymentBtn').prop('disabled', true).text('Confirm Payment');
    $('#paymentMethod').val('Cash');
    handlePaymentMethodChange();
});

// -------------------- PRODUCT MANAGEMENT (AJAX uses controller.php endpoints) --------------------
// Add product (cashier or superadmin allowed by controller)
$('#addProductForm').on('submit', function(e) {
    e.preventDefault();
    const payload = $(this).serialize() + '&action=add_product';
    $.ajax({
        url: 'controller.php',
        method: 'POST',
        dataType: 'json',
        data: payload,
        success: function(resp) {
            if (resp.success) {
                Swal.fire({ icon:'success', title:'Product Added', text: resp.message || 'Saved', showConfirmButton:false, timer:1100 })
                .then(()=> location.reload());
            } else {
                Swal.fire({ icon:'error', title:'Error', text: resp.message || 'Failed to add product.' });
            }
        },
        error: function(xhr) {
            Swal.fire({ icon:'error', title:'Error', text: xhr.responseText || 'Failed to add product.' });
        }
    });
});

// Edit product: open modal prefilled (superadmin only)
$(document).on('click', '.edit-product-btn', function() {
    $('#editProductId').val($(this).data('id'));
    $('#editProductName').val($(this).data('name'));
    $('#editProductPrice').val($(this).data('price'));
    $('#editProductImage').val($(this).data('image'));
    new bootstrap.Modal(document.getElementById('editProductModal')).show();
});

// Update product
$('#editProductForm').on('submit', function(e) {
    e.preventDefault();
    const payload = $(this).serialize() + '&action=edit_product';
    $.ajax({
        url: 'controller.php',
        method: 'POST',
        dataType: 'json',
        data: payload,
        success: function(resp) {
            if (resp.success) {
                Swal.fire({ icon:'success', title:'Product Updated', text: resp.message || 'Updated', showConfirmButton:false, timer:1100 })
                .then(()=> location.reload());
            } else {
                Swal.fire({ icon:'error', title:'Error', text: resp.message || 'Failed to update product.' });
            }
        },
        error: function(xhr) {
            Swal.fire({ icon:'error', title:'Error', text: xhr.responseText || 'Failed to update product.' });
        }
    });
});

// Delete product (superadmin only)
$(document).on('click', '.delete-product-btn', function() {
    const pid = $(this).data('id');
    Swal.fire({
        title: 'Delete product?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel'
    }).then(res => {
        if (res.isConfirmed) {
            $.ajax({
                url: 'controller.php',
                method: 'POST',
                dataType: 'json',
                data: { action: 'delete_product', id: pid },
                success: function(resp) {
                    if (resp.success) {
                        Swal.fire({ icon:'success', title:'Product Deleted', text: resp.message || 'Deleted', showConfirmButton:false, timer:1000 })
                        .then(()=> location.reload());
                    } else {
                        Swal.fire({ icon:'error', title:'Error', text: resp.message || 'Failed to delete product.' });
                    }
                },
                error: function(xhr) {
                    Swal.fire({ icon:'error', title:'Error', text: xhr.responseText || 'Failed to delete product.' });
                }
            });
        }
    });
});

// -------------------- ORDER DETAILS VIEW (from PHP data) --------------------
const ORDERS = <?= json_encode(array_column($orders, null, 'id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const ORDERS_ITEMS = <?= json_encode($orders_with_items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

$(document).on('click', '.view-order-btn', function() {
    const orderId = String($(this).data('order-id'));
    const order = ORDERS[orderId];
    const items = ORDERS_ITEMS[orderId] || [];

    $('#modalOrderId').text(orderId);
    $('#orderItemsContainer').empty();
    let total = 0;

    if (items.length === 0) {
        $('#orderItemsContainer').html('<p class="text-muted">No items recorded for this order.</p>');
        total = parseFloat(order.total) || 0;
    } else {
        const table = $(`<table class="table table-sm table-bordered">
            <thead class="table-light"><tr><th>Product</th><th style="width:70px">Qty</th><th style="width:100px">Price</th><th style="width:100px">Total</th></tr></thead><tbody></tbody></table>`);
        items.forEach(it => {
            const itemTotal = parseFloat(it.price) * parseInt(it.quantity);
            total += itemTotal;
            const name = it.product_name ?? ('Product #' + it.product_id);
            table.find('tbody').append(`<tr>
                <td>${$('<div>').text(name).html()}</td>
                <td class="text-center">${it.quantity}</td>
                <td class="text-end">₱${parseFloat(it.price).toFixed(2)}</td>
                <td class="text-end">₱${itemTotal.toFixed(2)}</td>
            </tr>`);
        });
        $('#orderItemsContainer').append(table);
    }

    // Populate payment details
    let paymentHtml = `
        <div class="row mb-2">
            <div class="col fw-bold">Total:</div>
            <div class="col text-end fw-bold">₱${total.toFixed(2)}</div>
        </div>
        <div class="row mb-2">
            <div class="col">Payment Method:</div>
            <div class="col text-end">${order.payment_method}</div>
        </div>
    `;
    if (order.payment_method === 'Cash') {
        paymentHtml += `
            <div class="row mb-2">
                <div class="col">Cash Given:</div>
                <div class="col text-end">₱${parseFloat(order.amount_paid).toFixed(2)}</div>
            </div>
            <div class="row">
                <div class="col">Change:</div>
                <div class="col text-end">₱${parseFloat(order.amount_change).toFixed(2)}</div>
            </div>
        `;
    }
    $('#orderDetailsPayment').html(paymentHtml);

    const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
    modal.show();
});

// initialize
$(document).ready(function() {
    renderCart();
});
</script>
</body>
</html>
