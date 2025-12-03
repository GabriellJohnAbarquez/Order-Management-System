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


<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('posApp', () => ({
        STORAGE_KEY: 'oms_cart',
        cart: [],
        paymentMethod: 'Cash',
        paymentCash: '',
        confirmBtnDisabled: true,
        paymentModal: null,
        total: 0,
        orders: <?= json_encode(array_column($orders, null, 'id'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
        ordersItems: <?= json_encode($orders_with_items, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
        selectedOrder: null,
        orderItemsForModal: [],

        init() {
            this.loadCart();
            this.renderCart();
            this.paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
        },

        loadCart() {
            const raw = localStorage.getItem(this.STORAGE_KEY);
            if (!raw) { this.cart = []; return; }
            try { this.cart = JSON.parse(raw); } catch(e) { console.error('Cart parse error', e); this.cart = []; }
        },

        saveCart() {
            localStorage.setItem(this.STORAGE_KEY, JSON.stringify(this.cart));
        },

        findCartItemIndex(id) {
            return this.cart.findIndex(it => String(it.id) === String(id));
        },

        addToCart(product) {
            const idx = this.findCartItemIndex(product.id);
            if (idx >= 0) {
                this.cart[idx].qty += product.qty;
            } else {
                this.cart.push(product);
            }
            this.saveCart();
            this.renderCart();

            Swal.fire({
                icon: 'success',
                title: 'Added to Cart',
                text: `${product.name} x${product.qty} added.`,
                showConfirmButton: false,
                timer: 900
            });
        },

        removeCartItem(idx) {
            const removed = this.cart.splice(idx, 1)[0];
            this.saveCart();
            this.renderCart();
            Swal.fire({
                icon: 'info',
                title: 'Removed',
                text: `${removed.name} removed from cart.`,
                showConfirmButton: false,
                timer: 900
            });
        },

        updateQty(idx, newQty) {
            if (isNaN(newQty) || newQty < 1) newQty = 1;
            if (this.cart[idx]) {
                this.cart[idx].qty = newQty;
                this.saveCart();
                this.renderCart();
                Swal.fire({ icon:'info', title:'Quantity Updated', text:`${this.cart[idx].name} quantity is now ${newQty}`, showConfirmButton:false, timer:700 });
            }
        },

        clearCart() {
            Swal.fire({
                title: 'Clear cart?',
                text: 'This will remove all items.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, clear it',
                cancelButtonText: 'Cancel'
            }).then(res => {
                if (res.isConfirmed) {
                    this.cart = [];
                    localStorage.removeItem(this.STORAGE_KEY);
                    this.renderCart();
                    Swal.fire({ icon:'success', title:'Cart Cleared', showConfirmButton:false, timer:900 });
                }
            });
        },

        renderCart() {
            this.total = this.cart.reduce((sum, it) => sum + it.price * it.qty, 0);
            this.confirmBtnDisabled = this.cart.length === 0;
        },

        openPaymentModal() {
            if (!this.cart.length) return;
            this.paymentCash = '';
            this.paymentMethod = 'Cash';
            this.confirmBtnDisabled = true;
            this.paymentModal.show();
        },

        handlePaymentMethodChange() {
            if (this.paymentMethod === 'Cash') {
                this.confirmBtnDisabled = parseFloat(this.paymentCash || 0) < this.total;
            } else {
                this.confirmBtnDisabled = false;
            }
        },

        updateCashInput() {
            if (this.paymentMethod === 'Cash') {
                this.confirmBtnDisabled = parseFloat(this.paymentCash || 0) < this.total;
            }
        },

        confirmPayment() {
            if (!this.cart.length) {
                Swal.fire({ icon:'error', title:'Empty cart', text:'Cart is empty.' });
                this.paymentModal.hide();
                return;
            }

            let amountPaid = this.total;
            let amountChange = 0;

            if (this.paymentMethod === 'Cash') {
                amountPaid = parseFloat(this.paymentCash) || 0;
                if (amountPaid < this.total) {
                    Swal.fire({ icon:'error', title:'Insufficient cash', text:'Cash given is less than total.' });
                    return;
                }
                amountChange = amountPaid - this.total;
            }

            this.confirmBtnDisabled = true;

            fetch('controller.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'checkout',
                    cart: JSON.stringify(this.cart),
                    payment_method: this.paymentMethod,
                    amount_paid: amountPaid,
                    amount_change: amountChange
                })
            })
            .then(res => res.json())
            .then(resp => {
                this.confirmBtnDisabled = false;
                if (resp.success) {
                    this.cart = [];
                    localStorage.removeItem(this.STORAGE_KEY);
                    this.renderCart();
                    this.paymentModal.hide();

                    let msg = `Order #${resp.order_id ?? ''} placed via ${this.paymentMethod}.<br>Total: ₱${this.total.toFixed(2)}`;
                    if (this.paymentMethod === 'Cash') msg += `<br>Cash: ₱${amountPaid.toFixed(2)}<br>Change: ₱${amountChange.toFixed(2)}`;

                    Swal.fire({ icon:'success', title:'Transaction Complete', html: msg, showConfirmButton:true })
                        .then(()=> location.reload());
                } else {
                    Swal.fire({ icon:'error', title:'Error', text: resp.message || 'Checkout failed.' });
                }
            })
            .catch(err => {
                this.confirmBtnDisabled = false;
                Swal.fire({ icon:'error', title:'Error', text: err.message || 'Failed to process order.' });
            });
        },

        viewOrder(orderId) {
            this.selectedOrder = this.orders[orderId];
            this.orderItemsForModal = this.ordersItems[orderId] || [];
            new bootstrap.Modal(document.getElementById('orderDetailsModal')).show();
        }
    }));
});
</script>

</body>
</html>
