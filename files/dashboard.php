<?php
session_start();

// DB connection
$conn = new mysqli('localhost', 'root', '', 'm228_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 🟩 1️⃣ Handle Add to Cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $productId = intval($_POST['product_id']);
    $productName = $_POST['product_name'];
    $productPrice = floatval($_POST['product_price']);
    $productImage = $_POST['product_image'];

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // If product already in cart, increase quantity
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]['quantity'] += 1;
    } else {
        $_SESSION['cart'][$productId] = [
            'id' => $productId,
            'name' => $productName,
            'price' => $productPrice,
            'image' => $productImage,
            'quantity' => 1
        ];
    }

    // 🟨 Store a short success message in session
    $_SESSION['success_message'] = "$productName added to cart successfully!";

    // Redirect to same page (prevents form re-submission)
    header("Location: " . $_SERVER['PHP_SELF'] . "?category=" . ($_GET['category'] ?? 0));
    exit;
}

// 🟧 2️⃣ Handle filter
$categoryFilter = isset($_GET['category']) ? intval($_GET['category']) : 0;

$query = "SELECT p.product_id, p.name, p.price, p.image_path, c.name AS category 
          FROM products p
          JOIN categories c ON p.category_id = c.category_id";

if ($categoryFilter > 0) {
    $query .= " WHERE p.category_id = $categoryFilter";
}

$query .= " ORDER BY p.created_at DESC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mashobane and Co</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/boxicons/2.1.4/css/boxicons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="index/styles.css">

  <style>
    .success-message {
      background: #4CAF50;
      color: white;
      padding: 10px 15px;
      border-radius: 8px;
      text-align: center;
      width: fit-content;
      margin: 10px auto;
      font-weight: 500;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      animation: fadeout 3s forwards;
    }

    @keyframes fadeout {
      0% {opacity: 1;}
      70% {opacity: 1;}
      100% {opacity: 0; display:none;}
    }

    .cart-icon {
      font-size: 22px;
      margin-right: 5px;
      vertical-align: middle;
    }
  </style>
</head>
<body>

<!-- ✅ Success Message -->
<?php if (isset($_SESSION['success_message'])): ?>
  <div class="success-message">
    <?= htmlspecialchars($_SESSION['success_message']) ?>
  </div>
  <?php unset($_SESSION['success_message']); // Clear message after showing ?>
<?php endif; ?>

<!-- Header -->
<div class="header">
  <img src="logo.png" alt="Logo" height="90px" width="100px">

  <div class="navigation">
    <nav>
      <ul>
        <li><a href="index.php">Shop Now</a></li>
        <li><a href="#">Delivery</a></li>
        <li><a href="#">Account</a></li>
        <li>
          <a href="cart.php">
            <i class='bx bx-cart cart-icon'></i>
            Cart (<?= isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0 ?>)
          </a>
        </li>
      </ul>
    </nav>
  </div>

  <div class="filter">
    <div class="filterOptions">
      <form method="GET" action="index.php">
        <select id="filter" name="category">
          <option value="0" <?= $categoryFilter == 0 ? 'selected' : '' ?>>All Departments</option>
          <option value="1" <?= $categoryFilter == 1 ? 'selected' : '' ?>>Males</option>
          <option value="2" <?= $categoryFilter == 2 ? 'selected' : '' ?>>Females</option>
          <option value="3" <?= $categoryFilter == 3 ? 'selected' : '' ?>>Kids</option>
        </select>
        <button class="filterButton" type="submit">
          <i class='bx bx-slider-alt'></i>
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Hero -->
<div class="sale">
  <h1>THE <span>SALE</span> IS <br>ON NOW !</h1>
  <p>Our biggest Sale yet since we came to life as 
    <span class="msc">Mashobane and Co,</span><br>
    get up to <span>75%</span> off on selected items only
  </p>
  <button class="btnShop">Shop now</button>
</div>

<!-- Products -->
<h2>Shop Now</h2>
<section class="shopping">
  <div class="shop-container">
    <?php if ($result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <div class="shop">
          <img src="<?= htmlspecialchars($row['image_path']) ?>" alt="<?= htmlspecialchars($row['name']) ?>">
          <div class="desc">
            <p class="descP"><?= htmlspecialchars($row['name']) ?></p>
            <p class="price">R <?= number_format($row['price'], 2) ?></p>
          </div>
          <div class="cart">
            <form method="POST" action="">
              <input type="hidden" name="product_id" value="<?= $row['product_id'] ?>">
              <input type="hidden" name="product_name" value="<?= htmlspecialchars($row['name']) ?>">
              <input type="hidden" name="product_price" value="<?= $row['price'] ?>">
              <input type="hidden" name="product_image" value="<?= htmlspecialchars($row['image_path']) ?>">
              <button type="submit" name="add_to_cart" class="btnCart">ADD TO CART</button>
            </form>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p>No products found.</p>
    <?php endif; ?>
  </div>
</section>

<!-- Contact Section -->
<h3 class="hContacts">Get In Touch</h3>
<div class="contacts">
  <div class="contact-container">
    <h4>Address</h4>
    <div class="cards">
      <p>Address: 123 Main Foreshore, Cape Town, South Africa</p>
      <p>Phone: 012 781 3213</p>
      <p>Email: info@mashobaneandco.com</p>
    </div>
  </div>
  <div class="contact-container">
    <h4>Support</h4>
    <div class="cards">
      <p>Customer Support</p>
      <p>Delivery Details</p>
      <p>Privacy Policy</p>
    </div>
  </div>
  <div class="contact-container">
    <h4>FAQ</h4>
    <div class="cards">
      <p>Account</p>
      <p>Orders</p>
      <p>Payments</p>
    </div>
  </div>
</div>

</body>
</html>
<?php $conn->close(); ?>
