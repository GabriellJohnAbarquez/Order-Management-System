<?php
session_start();
require_once 'models.php';
require 'dbconfig.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;
if (!$action) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit();
}

/**
 * --------------------
 * CASHIER MANAGEMENT
 * --------------------
 */
if ($action === 'add_cashier') {
    if ($_SESSION['role'] !== 'superadmin') {
        echo json_encode(['success'=>false,'message'=>'Unauthorized']);
        exit();
    }

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
        exit();
    }

    if (getUserByUsername($username)) {
        echo json_encode(['success' => false, 'message' => 'Username already exists.']);
        exit();
    }

    if (addCashier($username, $password)) {
        echo json_encode(['success' => true, 'message' => 'Cashier account created successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create cashier.']);
    }
    exit();
}

/**
 * --------------------
 * PRODUCT MANAGEMENT
 * --------------------
 */
if ($action === 'add_product') {
    if (!in_array($_SESSION['role'], ['superadmin', 'cashier'])) {
        echo json_encode(['success'=>false,'message'=>'Unauthorized']);
        exit();
    }

    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $image = trim($_POST['image_url']); // URL

    if (empty($name) || $price <= 0) {
        echo json_encode(['success'=>false,'message'=>'Please provide valid name and price.']);
        exit();
    }

    $stmt = $pdo->prepare("INSERT INTO products (name, price, image, added_by, date_added) VALUES (?, ?, ?, ?, NOW())");
    if ($stmt->execute([$name, $price, $image, $_SESSION['user_id']])) {
        echo json_encode(['success'=>true,'message'=>'Product added successfully!']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Failed to add product.']);
    }
    exit();
}

if ($action === 'edit_product') {
    if ($_SESSION['role'] !== 'superadmin') {
        echo json_encode(['success'=>false,'message'=>'Unauthorized']);
        exit();
    }

    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $image = trim($_POST['image']);

    if (empty($name) || $price <= 0) {
        echo json_encode(['success'=>false,'message'=>'Please provide valid name and price.']);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE products SET name=?, price=?, image=? WHERE id=?");
    if ($stmt->execute([$name, $price, $image, $id])) {
        echo json_encode(['success'=>true,'message'=>'Product updated successfully!']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Failed to update product.']);
    }
    exit();
}

if ($action === 'delete_product') {
    if ($_SESSION['role'] !== 'superadmin') {
        echo json_encode(['success'=>false,'message'=>'Unauthorized']);
        exit();
    }

    $id = intval($_POST['id']);
    $stmt = $pdo->prepare("DELETE FROM products WHERE id=?");
    if ($stmt->execute([$id])) {
        echo json_encode(['success'=>true,'message'=>'Product deleted successfully!']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Failed to delete product.']);
    }
    exit();
}

/**
 * --------------------
 * CHECKOUT / ORDER MANAGEMENT
 * --------------------
 */
if ($action === 'checkout') {
    $cart = $_POST['cart'] ?? null;
    $payment_method = $_POST['payment_method'] ?? 'Cash';
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $amount_change = floatval($_POST['amount_change'] ?? 0);

    if (!$cart) {
        echo json_encode(['success'=>false,'message'=>'Cart is empty.']);
        exit();
    }

    $cart = json_decode($cart, true);
    if (!$cart || !is_array($cart)) {
        echo json_encode(['success'=>false,'message'=>'Invalid cart data.']);
        exit();
    }

    $cashier_id = $_SESSION['user_id'];

    $pdo->beginTransaction();
    try {
        $total = array_reduce($cart, fn($sum,$item)=>$sum+$item['price']*$item['qty'], 0);
        $stmt = $pdo->prepare("
            INSERT INTO orders (cashier_id, total, amount_paid, amount_change, payment_method, date_added)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$cashier_id, $total, $amount_paid, $amount_change, $payment_method]);
        $order_id = $pdo->lastInsertId();

        $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        foreach ($cart as $item) {
            $stmtItem->execute([$order_id, $item['id'], $item['qty'], $item['price']]);
        }

        $pdo->commit();
        echo json_encode([
            'success'=>true,
            'message'=>'Order processed successfully!',
            'order_id'=>$order_id,
            'total'=>$total,
            'amount_paid'=>$amount_paid,
            'amount_change'=>$amount_change,
            'payment_method'=>$payment_method,
            'cart' => $cart
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>'Failed to process order: '.$e->getMessage()]);
    }
    exit();
}

if ($action === 'get_order_details') {
    $order_id = $_GET['order_id'] ?? null;
    if (!$order_id) {
        echo json_encode(['success' => false, 'message' => 'Order ID not specified.']);
        exit();
    }

    $order = getOrderDetails($order_id);

    if ($order) {
        echo json_encode(['success' => true, 'order' => $order]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
    }
    exit();
}


/**
 * --------------------
 * FALLBACK / UNKNOWN ACTION
 * --------------------
 */
echo json_encode(['success'=>false,'message'=>'Unknown action.']);
exit();
?>
