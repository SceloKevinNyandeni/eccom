<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    die("❌ Unauthorized. Please <a href='user-login.php'>login</a> first.");
}
$conn = new mysqli('localhost', 'root', '', 'm228_db');
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// 🧠 Handle Add to Cart (AJAX or normal POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    $productId   = intval($_POST['product_id']);
    $productName = $_POST['product_name'];
    $productPrice= floatval($_POST['product_price']);
    $productImage= $_POST['product_image'];

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]['quantity'] += 1;
        $msg = "Another $productName added to your cart.";
    } else {
        $_SESSION['cart'][$productId] = [
            'id' => $productId,
            'name' => $productName,
            'price' => $productPrice,
            'image' => $productImage,
            'quantity' => 1
        ];
        $msg = "$productName added to cart successfully!";
    }

    $totalItems = array_sum(array_column($_SESSION['cart'], 'quantity'));

    // Return JSON if request is AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        echo json_encode([
            'success' => true,
            'message' => $msg,
            'cartCount' => $totalItems
        ]);
        exit;
    }
}

// Filters
$categoryFilter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Query
$query = "SELECT p.product_id, p.name, p.price, p.image_path, c.name AS category, p.created_at
          FROM products p
          JOIN categories c ON p.category_id = c.category_id";

if ($categoryFilter > 0) {
    $query .= " WHERE p.category_id = $categoryFilter";
}

switch ($sort) {
    case 'oldest':     $query .= " ORDER BY p.created_at ASC"; break;
    case 'price_low':  $query .= " ORDER BY p.price ASC"; break;
    case 'price_high': $query .= " ORDER BY p.price DESC"; break;
    case 'newest':
    default:           $query .= " ORDER BY p.created_at DESC"; break;
}

$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Mashobane and Co</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="store.css">
  <style>
    body { font-family: 'Poppins', sans-serif; }

    .message-banner {
      position: fixed;
      top: -60px;
      left: 50%;
      transform: translateX(-50%);
      padding: 12px 25px;
      border-radius: 8px;
      font-weight: 500;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      z-index: 9999;
      opacity: 0;
      transition: top .6s ease, opacity .6s ease;
    }
    .message-banner.show { top: 20px; opacity: 1; }
    .message-success { background: #28a745; color: #fff; }
    .message-error   { background: #dc3545; color: #fff; }

    .cart-badge {
      background: #ff3b3b;
      color: white;
      border-radius: 50%;
      padding: 3px 8px;
      font-size: 13px;
      position: relative;
      top: -12px;
      right: 10px;
    }
  </style>
</head>
<body>

<div class="header">
    <img src="index/logo.png" alt="Logo" height="120px" width="130px">
    <div class="navigation">
        <nav>
            <ul>
                <li><a href="#">Shop Now</a></li>
                <li><a href="#">Delivery</a></li>
                <li><a href="#">Account</a></li>
                
            </ul>
        </nav>
    </div>
    <div class="cart">
      <a href="#"><i class="fa-regular fa-user"></i></a>
<a href="cart.php"><i class="bi bi-bag" aria-hidden="true"></i><span class="cart-badge"><?= isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0 ?></span></a>
    </div>
</div>

<!-- Hero Banner -->
<div class="hero">
  <div class="hero-content">
    <div class="hero-text">
      <h1>Simple is <span>More</span></h1>
      <p>Discover timeless fashion with Mashobane and Co</p>
    </div>
    <div class="hero-buttons">
      <button href="#" class="btn">Shop Now</button>
      <button onclick="location.href='about.html'" class="btn btn-light">Learn More</button>
    </div>
  </div>
</div>

<h1 class="section-title">Shop the Latest Trends</h1>

<div class="container">
  <!-- Sidebar Filters -->
  <aside class="sidebar">
    <h3>Filter</h3>
    <form method="GET" action="">
      <div class="filter-group">
        <select name="category">
          <option value="0" <?= $categoryFilter == 0 ? 'selected' : '' ?>>All</option>
          <option value="1" <?= $categoryFilter == 1 ? 'selected' : '' ?>>Males</option>
          <option value="2" <?= $categoryFilter == 2 ? 'selected' : '' ?>>Females</option>
          <option value="3" <?= $categoryFilter == 3 ? 'selected' : '' ?>>Kids</option>
        </select>
      </div>
      <div class="filter-group">
        <select name="sort">
          <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
          <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
          <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
          <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
        </select>
      </div>
      <button type="submit" class="apply-btn">Apply</button>
    </form>
  </aside>

  <!-- Products Grid -->
  <main class="products">
    <div class="top-bar">
      <p><?= $result ? $result->num_rows : 0 ?> results for clothes</p>
    </div>
    <div class="grid">
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <div class="card">
            <img src="<?= htmlspecialchars($row['image_path']) ?>" alt="<?= htmlspecialchars($row['name']) ?>">
            <div class="info">
              <h4><?= htmlspecialchars($row['name']) ?></h4>
              <p class="price">R <?= number_format($row['price'], 2) ?></p>
              <form class="add-to-cart-form">
                <input type="hidden" name="action" value="add_to_cart">
                <input type="hidden" name="product_id" value="<?= $row['product_id'] ?>">
                <input type="hidden" name="product_name" value="<?= htmlspecialchars($row['name']) ?>">
                <input type="hidden" name="product_price" value="<?= $row['price'] ?>">
                <input type="hidden" name="product_image" value="<?= htmlspecialchars($row['image_path']) ?>">
                <button type="submit" class="btn-cart">Add to Cart</button>
              </form>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p>No products found.</p>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- 🧠 AJAX Add-to-Cart Logic -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  const forms = document.querySelectorAll('.add-to-cart-form');
  const cartBadge = document.querySelector('.cart-badge');

  forms.forEach(form => {
    form.addEventListener('submit', async e => {
      e.preventDefault();

      const formData = new FormData(form);

      try {
        const response = await fetch('index.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: formData
        });

        const data = await response.json();

        if (data.success) {
          cartBadge.textContent = data.cartCount;
          showMessage(data.message, true);
        } else {
          showMessage('Failed to add item. Please try again.', false);
        }
      } catch {
        showMessage('Server error. Please try again later.', false);
      }
    });
  });

  function showMessage(message, success = true) {
    const msg = document.createElement('div');
    msg.className = 'message-banner ' + (success ? 'message-success' : 'message-error');
    msg.textContent = message;
    document.body.appendChild(msg);
    setTimeout(() => msg.classList.add('show'), 100);
    setTimeout(() => {
      msg.classList.remove('show');
      setTimeout(() => msg.remove(), 600);
    }, 3000);
  }
});
</script>

</body>
</html>
<?php $conn->close(); ?>
