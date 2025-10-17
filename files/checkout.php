<?php
session_start();

// 🧠 Require user login
if (!isset($_SESSION['user_id'])) {
    die("❌ Unauthorized. Please <a href='user-login.php'>login</a> first.");
}

// 🧠 Ensure cart is not empty
if (empty($_SESSION['cart'])) {
    die("🛒 Your cart is empty. <a href='index.php'>Go back to shop</a>");
}

// Connect to DB
$conn = new mysqli('localhost', 'root', '', 'm228_db');
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Compute totals
$totalItems = array_sum(array_column($_SESSION['cart'], 'quantity'));
$totalPrice = 0;
foreach ($_SESSION['cart'] as $it) {
    $totalPrice += $it['price'] * $it['quantity'];
}

$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name  = trim($_POST['full_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $city       = trim($_POST['city'] ?? '');
    $payment    = trim($_POST['payment_method'] ?? '');

    if ($full_name === '' || $email === '' || $address === '' || $city === '') {
        $message = "❌ Please fill in all required fields.";
    } else {
        // ✅ Create `orders` table with user_id and reseller_id FKs
        $conn->query("
            CREATE TABLE IF NOT EXISTS orders (
                order_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                reseller_id INT NOT NULL,
                customer_name VARCHAR(100),
                customer_email VARCHAR(100),
                address VARCHAR(255),
                city VARCHAR(100),
                payment_method VARCHAR(50),
                total_amount DECIMAL(10,2),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                FOREIGN KEY (reseller_id) REFERENCES resellers(reseller_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS order_items (
                item_id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                product_id INT NOT NULL,
                name VARCHAR(100),
                price DECIMAL(10,2),
                quantity INT,
                subtotal DECIMAL(10,2),
                FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // 🔍 Determine reseller_id (via product relationship)
        // Assumes all items in cart belong to the same reseller
        $firstProductId = array_key_first($_SESSION['cart']);
        $stmtRes = $conn->prepare("SELECT reseller_id FROM products WHERE product_id = ?");
        $stmtRes->bind_param("i", $firstProductId);
        $stmtRes->execute();
        $res = $stmtRes->get_result();
        $reseller_id = ($res->num_rows > 0) ? (int)$res->fetch_assoc()['reseller_id'] : 1; // fallback ID
        $stmtRes->close();

        // 🧾 Insert main order
        $stmt = $conn->prepare("
            INSERT INTO orders (user_id, reseller_id, customer_name, customer_email, address, city, payment_method, total_amount)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "iisssssd",
            $_SESSION['user_id'],
            $reseller_id,
            $full_name,
            $email,
            $address,
            $city,
            $payment,
            $totalPrice
        );
        $stmt->execute();
        $order_id = $stmt->insert_id;

        // 🛍 Insert order items
        $itemStmt = $conn->prepare("
            INSERT INTO order_items (order_id, product_id, name, price, quantity, subtotal)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($_SESSION['cart'] as $it) {
            $subtotal = $it['price'] * $it['quantity'];
            $itemStmt->bind_param("iisddi", $order_id, $it['id'], $it['name'], $it['price'], $it['quantity'], $subtotal);
            $itemStmt->execute();
        }
        $itemStmt->close();

        // ✅ Clear cart
        $_SESSION['cart'] = [];
        $message = "✅ Order placed successfully! Thank you for shopping with Mashobane & Co.";
        header("Location: delivery.php?order_id=" . $order_id);
exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Checkout | Mashobane and Co</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body {font-family:'Inter',sans-serif;background:#fafafa;margin:0;padding:0;color:#111;}
.container{max-width:1000px;margin:40px auto;padding:20px;background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);}
h1{text-align:center;margin-bottom:25px;}
form{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
input,select,textarea{padding:10px;border:1px solid #ddd;border-radius:8px;width:70%;}
textarea{grid-column:1/3;}
button{grid-column:1/3;padding:12px;background:#1b0066ff;color:white;font-weight:800;border:0;border-radius:8px;cursor:pointer;}
button:hover{filter:brightness(.95);}
.summary{margin-top:30px;border-top:1px solid #eee;padding-top:20px;}
.row{display:flex;justify-content:space-between;margin:8px 0;}
.total{font-weight:800;font-size:18px;}
.message{text-align:center;font-weight:700;margin-bottom:15px;}
a.back{display:inline-block;margin-bottom:15px;text-decoration:none;color:#007bff;}
</style>
</head>
<body>
<div class="container">
  <a href="cart.php" class="back"><i class="fa-solid fa-arrow-left"></i> Back to Cart</a>
  <h1>Checkout</h1>
  <?php if ($message): ?><div class="message"><?= htmlspecialchars($message) ?></div><?php endif; ?>

  <?php if (empty($message)): ?>
  <form method="POST">
    <input type="text" name="full_name" placeholder="Full Name" required>
    <input type="email" name="email" placeholder="Email" required>
    <input type="text" name="address" placeholder="Street Address" required>
    <input type="text" name="city" placeholder="City" required>

    <select name="payment_method" required>
      <option value="">-- Select Payment Method --</option>
      <option value="Cash on Delivery">Cash on Delivery</option>
      <option value="Card Payment">Card Payment</option>
      <option value="EFT / Bank Transfer">EFT / Bank Transfer</option>
    </select>

    <textarea name="notes" placeholder="Additional notes (optional)"></textarea>
    <button type="submit">Place Order</button>
  </form>

  <div class="summary">
    <h3>Order Summary</h3>
    <?php foreach ($_SESSION['cart'] as $it): ?>
      <div class="row"><span><?= htmlspecialchars($it['name']) ?> × <?= $it['quantity'] ?></span><span>R <?= number_format($it['price'] * $it['quantity'], 2) ?></span></div>
    <?php endforeach; ?>
    <div class="row total"><span>Total</span><span>R <?= number_format($totalPrice, 2) ?></span></div>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
