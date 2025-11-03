CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) UNIQUE,
  password VARCHAR(255),
  role ENUM('cashier', 'superadmin') NOT NULL,
  status ENUM('active', 'suspended') DEFAULT 'active',
  date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  image VARCHAR(255),
  added_by INT,
  date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cashier_id INT,
  total DECIMAL(10,2) NOT NULL,
  amount_paid DECIMAL(10,2) DEFAULT 0.00 AFTER 'total',
  amount_change DECIMAL(10,2) DEFAULT 0.00 AFTER 'amount_paid',
  payment_method ENUM('Cash', 'Card', 'GCash', 'Other') DEFAULT 'Cash' AFTER 'amount_change',
  date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT,
  product_id INT,
  quantity INT NOT NULL DEFAULT 1,
  price DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);
