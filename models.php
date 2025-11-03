<?php
// models.php
require_once 'dbconfig.php';

/**
 * USER MANAGEMENT
 */

// Fetch user by username
function getUserByUsername($username) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch all cashiers
function getAllCashiers() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'cashier' ORDER BY date_added DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Add cashier
function addCashier($username, $password) {
    global $pdo;
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, status, date_added) VALUES (?, ?, 'cashier', 'active', NOW())");
    return $stmt->execute([$username, $hashed]);
}

// Toggle cashier status
function toggleCashierStatus($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) return false;

    $newStatus = ($user['status'] === 'active') ? 'suspended' : 'active';
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $id]);
    return $newStatus;
}

/**
 * PRODUCT MANAGEMENT
 */

// Fetch all products
function getAllProducts() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM products ORDER BY date_added DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Add new product
function addProduct($name, $price, $image, $added_by) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO products (name, price, image, added_by) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$name, $price, $image, $added_by]);
}

// Delete product
function deleteProduct($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * ORDER MANAGEMENT
 */

// Add new order and its items
function createOrder($cashier_id, $cart) {
    global $pdo;

    try {
        $pdo->beginTransaction();

        $total = 0;
        foreach ($cart as $item) {
            $total += $item['price'] * $item['qty'];
        }

        $stmt = $pdo->prepare("INSERT INTO orders (cashier_id, total, date_added) VALUES (?, ?, NOW())");
        $stmt->execute([$cashier_id, $total]);
        $order_id = $pdo->lastInsertId();

        $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        foreach ($cart as $item) {
            $stmtItem->execute([$order_id, $item['id'], $item['qty'], $item['price']]);
        }

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

// Fetch cashier's order history
function getOrdersByCashier($cashier_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE cashier_id = ? ORDER BY date_added DESC");
    $stmt->execute([$cashier_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch all orders (for reports)
function getAllOrders($from = null, $to = null) {
    global $pdo;
    $query = "SELECT o.*, u.username AS cashier_name 
              FROM orders o 
              LEFT JOIN users u ON o.cashier_id = u.id 
              WHERE 1";

    $params = [];
    if ($from && $to) {
        $query .= " AND DATE(o.date_added) BETWEEN ? AND ?";
        $params = [$from, $to];
    }

    $query .= " ORDER BY o.date_added DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getOrderDetails($order_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT o.*, u.username as cashier_name FROM orders o LEFT JOIN users u ON o.cashier_id = u.id WHERE o.id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) return false;

    $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $order;
}
?>
