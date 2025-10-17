<?php
session_start();

/* 1) Reseller session check */
if (!isset($_SESSION['reseller_id'])) {
    die("❌ Unauthorized. Please <a href='resellerLogin.html'>login</a> first.");
}

$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name   = trim($_POST['product_name'] ?? '');
    $product_price  = trim($_POST['product_price'] ?? '');
    $category_id    = (int)($_POST['category_id'] ?? 0);
    $product_qty    = (int)($_POST['product_quantity'] ?? 0);

    if ($product_name === '' || $product_price === '' || $category_id <= 0) {
        $message = "❌ All fields are required, and a valid category must be selected.";
    } elseif (!is_numeric($product_price) || (float)$product_price <= 0) {
        $message = "❌ Invalid product price.";
    } elseif ($product_qty < 0) {
        $message = "❌ Quantity cannot be negative.";
    } else {
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
            $file_tmp  = $_FILES['product_image']['tmp_name'];
            $file_name = basename($_FILES['product_image']['name']);
            $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if ($_FILES['product_image']['size'] > 5*1024*1024) {
                $message = "❌ File too large. Max 5MB.";
            } else {
                $new_name   = uniqid('prod_', true) . '.' . $file_ext;
                $upload_dir = __DIR__ . '/uploads/';
                if (!file_exists($upload_dir)) { mkdir($upload_dir, 0755, true); }
                $upload_path = $upload_dir . $new_name;

                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $conn = new mysqli('localhost', 'root', '', 'm228_db');
                    if ($conn->connect_error) die('Connection failed: ' . $conn->connect_error);

                    /* 2) Ensure products table (with quantity) exists */
                    $conn->query("
                        CREATE TABLE IF NOT EXISTS products (
                            product_id INT AUTO_INCREMENT PRIMARY KEY,
                            reseller_id INT NOT NULL,
                            category_id INT NOT NULL,
                            name VARCHAR(100) NOT NULL,
                            price DECIMAL(10,2) NOT NULL,
                            image_path VARCHAR(255) NOT NULL,
                            quantity INT NOT NULL DEFAULT 0,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (reseller_id) REFERENCES resellers(reseller_id) ON DELETE CASCADE,
                            FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                    ");

                    /* 3) Backfill migration if older table lacks `quantity` */
                    $colRes = $conn->query("SHOW COLUMNS FROM products LIKE 'quantity'");
                    if ($colRes && $colRes->num_rows === 0) {
                        $conn->query("ALTER TABLE products ADD COLUMN quantity INT NOT NULL DEFAULT 0 AFTER image_path");
                    }

                    $image_path = 'uploads/' . $new_name;

                    /* 4) Insert product with quantity */
                    $stmt = $conn->prepare("
                        INSERT INTO products (reseller_id, category_id, name, price, image_path, quantity)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param(
                        "iisdsi",
                        $_SESSION['reseller_id'],
                        $category_id,
                        $product_name,
                        $product_price,
                        $image_path,
                        $product_qty
                    );

                    if ($stmt->execute()) {
                        $message = "✅ Product uploaded successfully!";
                    } else {
                        $message = "❌ Database error: " . $stmt->error;
                    }

                    $stmt->close();
                    $conn->close();
                } else {
                    $message = "❌ Failed to upload image.";
                }
            }
        } else {
            $message = "❌ No image uploaded or an error occurred.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reseller Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');
    *{margin:0;padding:0;box-sizing:border-box;font-family:"Poppins",sans-serif}
    body{display:flex;background:#f4f7fa;min-height:100vh}
    .sidebar{width:220px;background:#007bff;color:#fff;padding:20px;flex-shrink:0;display:flex;flex-direction:column;height:100vh}
    .sidebar h2{text-align:center;margin-bottom:20px}
    .sidebar a{color:#fff;padding:12px;margin:5px 0;border-radius:6px;text-decoration:none}
    .sidebar a:hover{background:#0056b3}
    .sidebar a.logout{margin-top:auto;background:#dc3545}.sidebar a.logout:hover{background:#a71d2a}
    .main{flex-grow:1;padding:30px}
    h1{text-align:center;color:#007bff;margin-bottom:30px}
    .upload-form{max-width:520px;margin:auto;background:#fff;padding:25px;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,0.1);display:flex;flex-direction:column;gap:15px}
    .upload-form label{font-weight:500}
    .upload-form input,.upload-form select{padding:10px;border:1px solid #ccc;border-radius:6px;width:100%}
    .upload-form button{background:#007bff;color:#fff;padding:10px;border:none;border-radius:6px;cursor:pointer;font-weight:bold;transition:background .3s}
    .upload-form button:hover{background:#0056b3}
    .message{text-align:center;margin-bottom:15px;font-weight:bold}
  </style>
</head>
<body>
  <div class="sidebar">
      <h2>Reseller Panel</h2>
      <a href="reseller.php"><i class="fa-solid fa-box"></i> Reseller Dashboard</a>
      <a href="upload.php"><i class="fa-solid fa-box"></i> Upload Products</a>
      <a href="orders.php"><i class="fa-solid fa-cart-shopping"></i> Orders</a>
      <a href="reseller-analytics.php"><i class="fa-solid fa-chart-line"></i> Analytics</a>
      <a href="logout.php" class="logout"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
  </div>

  <div class="main">
      <h1>📦 Upload a New Product</h1>
      <?php if (!empty($message)): ?><p class="message"><?= htmlspecialchars($message) ?></p><?php endif; ?>

      <form method="POST" enctype="multipart/form-data" class="upload-form">
          <label>Product Name:</label>
          <input type="text" name="product_name" required>

          <label>Price (R):</label>
          <input type="number" name="product_price" step="0.01" min="0.01" required>

          <label>Category:</label>
          <select name="category_id" required>
              <option value="">-- Select Category --</option>
              <?php
              $conn = new mysqli('localhost', 'root', '', 'm228_db');
              $cats = $conn->query("SELECT category_id, name FROM categories ORDER BY name ASC");
              while ($row = $cats->fetch_assoc()) {
                  echo "<option value='{$row['category_id']}'>" . htmlspecialchars($row['name']) . "</option>";
              }
              $conn->close();
              ?>
          </select>

          <label>Product Image:</label>
          <input type="file" name="product_image" accept="image/*" required>

          <label>Product Quantity (stock):</label>
          <input type="number" name="product_quantity" min="0" step="1" required>

          <button type="submit">Upload Product</button>
      </form>
  </div>
</body>
</html>