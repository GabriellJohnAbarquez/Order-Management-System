<?php
session_start();
require_once 'dbconfig.php';

// Check if user is logged in and a superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: index.php");
    exit();
}

// Handle date filters
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Base query
$query = "SELECT o.id, u.username AS cashier, o.total, o.payment_method, o.amount_paid, o.amount_change, o.date_added 
          FROM orders o 
          LEFT JOIN users u ON o.cashier_id = u.id
          WHERE 1=1";
$params = [];

if (!empty($start_date) && !empty($end_date)) {
    $query .= " AND DATE(o.date_added) BETWEEN ? AND ?";
    $params = [$start_date, $end_date];
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compute total
$total_query = "SELECT SUM(total) as total FROM orders WHERE 1=1";
$total_params = [];
if (!empty($start_date) && !empty($end_date)) {
    $total_query .= " AND DATE(date_added) BETWEEN ? AND ?";
    $total_params = [$start_date, $end_date];
}
$total_stmt = $pdo->prepare($total_query);
$total_stmt->execute($total_params);
$total = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <meta charset="UTF-8">
    <title>Sales Reports | Among Us OMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Turret+Road:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #235685;
            font-family: 'Turret Road', sans-serif;
            color: white;
        }
        .card {
            background-color: rgba(117, 219, 244, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }
        .table {
            color: white;
        }
        .table-bordered {
            border-color: rgba(255, 255, 255, 0.2);
        }
        .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: rgba(117, 219, 244, 0.05);
        }
        .table-dark th {
            background-color: rgba(0, 0, 0, 0.2) !important;
        }
        .form-control {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
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
        /* Print styles */
        @media print {
            .no-print { display: none; }  /* hide buttons, filters */
            body { background: #fff; color: black; }
            .card { box-shadow: none; border: 1px solid #dee2e6; }
            table { page-break-inside: auto; width: 100%; color: black; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            .table-striped > tbody > tr:nth-of-type(odd) {
                background-color: #f2f2f2 !important;
            }
        }
        .modal-content {
            background-color: #235685;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }
        .modal-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        .modal-title {
            color: white;
        }
        .table-light th {
            color: black;
        }
    </style>
</head>
<body>
<div class="container mt-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Sales Reports</h3>
        <div>
            <a href="superadmin_dashboard.php" class="btn btn-secondary me-2 no-print">← Back to Dashboard</a>
            <button id="printReport" class="btn btn-danger no-print">🖨️ Print Report</button>
        </div>
    </div>

    <form class="row mb-4 no-print" method="GET">
        <div class="col-md-4">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
    </form>

    <div class="card shadow-sm" id="printableArea">
        <div class="card-body">
            <div class="text-center mb-3">
                <h4>impostor OMS Transaction History</h4>
                <?php if (!empty($start_date) && !empty($end_date)): ?>
                    <p><strong>From:</strong> <?= htmlspecialchars($start_date) ?> <strong>To:</strong> <?= htmlspecialchars($end_date) ?></p>
                <?php else: ?>
                    <p><em>Showing all transactions</em></p>
                <?php endif; ?>
            </div>

            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Order ID</th>
                        <th>Cashier</th>
                        <th>Total Amount (₱)</th>
                        <th>Payment Method</th>
                        <th>Cash Given</th>
                        <th>Change</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($orders): ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?= htmlspecialchars($order['id']) ?></td>
                                <td><?= htmlspecialchars($order['cashier'] ?? 'Deleted') ?></td>
                                <td><?= number_format($order['total'], 2) ?></td>
                                <td><?= htmlspecialchars($order['payment_method']) ?></td>
                                <td><?= number_format($order['amount_paid'], 2) ?></td>
                                <td><?= number_format($order['amount_change'], 2) ?></td>
                                <td><?= htmlspecialchars($order['date_added']) ?></td>
                                <td><button class="btn btn-sm btn-info view-receipt-btn" data-order-id="<?= $order['id'] ?>">View Receipt</button></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="fw-bold bg-light text-dark">
                            <td colspan="2" class="text-end">TOTAL:</td>
                            <td>₱<?= number_format($total, 2) ?></td>
                            <td colspan="5"></td>
                        </tr>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center">No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <p class="text-end mt-4">
                <em>Generated on <?= date('Y-m-d H:i:s') ?></em>
            </p>
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const reportOrders = <?= json_encode($orders) ?>;
const reportTotal = <?= json_encode($total) ?>;
document.getElementById('printReport').addEventListener('click', function() {
    Swal.fire({
        title: 'Print Report?',
        text: 'Do you want to generate a printable sales report?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, generate it!',
        cancelButtonText: 'Cancel',
    }).then((result) => {
        if (result.isConfirmed) {
            let reportHtml = `
                <html>
                <head>
                    <title>Sales Report</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
                    <style>
                        body { font-family: sans-serif; }
                        .report-header { text-align: center; margin-bottom: 2rem; }
                        .report-header h4 { margin-bottom: 0; }
                        .report-header p { margin-top: 0; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #dee2e6; padding: 0.5rem; }
                        th { background-color: #f8f9fa; }
                        .text-end { text-align: right; }
                        .fw-bold { font-weight: bold; }
                    </style>
                </head>
                <body>
                    <div class="container py-4">
                        <div class="report-header">
                            <h4>POS System Transaction History</h4>
                            <?php if (!empty($start_date) && !empty($end_date)): ?>
                                <p><strong>From:</strong> <?= htmlspecialchars($start_date) ?> <strong>To:</strong> <?= htmlspecialchars($end_date) ?></p>
                            <?php else: ?>
                                <p><em>All transactions</em></p>
                            <?php endif; ?>
                        </div>
                        <table>
                            <thead class="table-dark">
                                <tr>
                                    <th>Order ID</th>
                                    <th>Cashier</th>
                                    <th>Total Amount (₱)</th>
                                    <th>Payment Method</th>
                                    <th>Cash Given</th>
                                    <th>Change</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>`;

            if (reportOrders && reportOrders.length > 0) {
                reportOrders.forEach(order => {
                    reportHtml += `
                        <tr>
                            <td>${order.id}</td>
                            <td>${order.cashier || 'Deleted'}</td>
                            <td class="text-end">${parseFloat(order.total).toFixed(2)}</td>
                            <td>${order.payment_method}</td>
                            <td class="text-end">${parseFloat(order.amount_paid).toFixed(2)}</td>
                            <td class="text-end">₱${parseFloat(order.amount_change).toFixed(2)}</td>
                            <td>${order.date_added}</td>
                        </tr>`;
                });

                reportHtml += `
                    <tr class="fw-bold bg-light">
                        <td colspan="6" class="text-end">TOTAL:</td>
                        <td class="text-end">₱${parseFloat(reportTotal).toFixed(2)}</td>
                    </tr>`;

            } else {
                reportHtml += '<tr><td colspan="7" class="text-center">No records found.</td></tr>';
            }

            reportHtml += `
                            </tbody>
                        </table>
                        <p class="text-end mt-4 fst-italic">
                            <em>Generated on ${new Date().toLocaleString()}</em>
                        </p>
                    </div>
                </body>
                </html>`;

            const printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write(reportHtml);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 500);
        }
    });
});

$(document).on('click', '.view-receipt-btn', function() {
    const orderId = $(this).data('order-id');
    $.ajax({
        url: 'controller.php',
        method: 'GET',
        dataType: 'json',
        data: { action: 'get_order_details', order_id: orderId },
        success: function(resp) {
            if (resp.success) {
                showReceipt(resp.order);
            } else {
                Swal.fire({ icon:'error', title:'Error', text: resp.message || 'Failed to fetch order details.' });
            }
        },
        error: function(xhr) {
            Swal.fire({ icon:'error', title:'Error', text: xhr.responseText || 'Failed to fetch order details.' });
        }
    });
});

function showReceipt(data) {
    const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
    const receiptContent = $('#receiptContent');
    const orderDate = new Date(data.date_added);

    let itemsHtml = '';
    data.items.forEach(item => {
        itemsHtml += `
            <tr>
                <td>${item.product_id}</td>
                <td class="text-center">${item.quantity}</td>
                <td class="text-end">₱${parseFloat(item.price).toFixed(2)}</td>
                <td class="text-end">₱${(item.quantity * item.price).toFixed(2)}</td>
            </tr>
        `;
    });

    const content = `
        <div class="text-center">
            <h4>Order #${data.id}</h4>
            <p class="mb-0">Date: ${orderDate.toLocaleDateString()} ${orderDate.toLocaleTimeString()}</p>
            <p>Cashier: ${data.cashier_name || 'N/A'}</p>
        </div>
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="text-center">Qty</th>
                    <th class="text-end">Price</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                ${itemsHtml}
            </tbody>
        </table>
        <hr>
        <div class="row">
            <div class="col">Total:</div>
            <div class="col text-end fw-bold">₱${parseFloat(data.total).toFixed(2)}</div>
        </div>
        <div class="row">
            <div class="col">Cash Given:</div>
            <div class="col text-end">₱${parseFloat(data.amount_paid).toFixed(2)}</div>
        </div>
        <div class="row">
            <div class="col">Change:</div>
            <div class="col text-end">₱${parseFloat(data.amount_change).toFixed(2)}</div>
        </div>
    `;

    receiptContent.html(content);
    receiptModal.show();
}

$('#printReceiptBtn').on('click', function() {
    const content = document.getElementById('receiptContent').innerHTML;
    const printWindow = window.open('', '', 'height=500,width=500');
    printWindow.document.write('<html><head><title>Print Receipt</title>');
    printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
    printWindow.document.write('</head><body>');
    printWindow.document.write(content);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
});
</script>
</body>
</html>
