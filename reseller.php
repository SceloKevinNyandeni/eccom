<?php
session_start();

// 🔒 Reseller check
if (!isset($_SESSION['reseller_id'])) {
    die("❌ You are not authorized to access this page.");
}

// DB connection
$conn = new mysqli('localhost', 'root', '', 'm228_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$resellerId = $_SESSION['reseller_id'];

// Fetch reseller name
$stmt = $conn->prepare("SELECT full_name FROM resellers WHERE reseller_id = ?");
$stmt->bind_param("i", $resellerId);
$stmt->execute();
$res = $stmt->get_result();
$resellerRow = $res->fetch_assoc();
$resellerName = $resellerRow['full_name'] ?? "Reseller";
$stmt->close();

// Fetch stats
$totalProducts = $conn->query("SELECT COUNT(*) AS total FROM products WHERE reseller_id = $resellerId")->fetch_assoc()['total'];
$totalOrders   = $conn->query("SELECT COUNT(*) AS total FROM orders WHERE reseller_id = $resellerId")->fetch_assoc()['total'];
$totalRevenue  = $conn->query("SELECT SUM(total_amount) AS revenue FROM orders WHERE reseller_id = $resellerId")->fetch_assoc()['revenue'] ?? 0;

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reseller Dashboard</title>
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
  <style>
       @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: "Poppins", Arial, sans-serif;
}

body { display: flex; background: #f4f7fa; min-height: 100vh; }

/* Sidebar */
.sidebar {
  width: 220px; background: #007bff; color: white; padding: 20px;
  flex-shrink: 0; display: flex; flex-direction: column; height: 100vh;
}
.sidebar h2 { text-align: center; margin-bottom: 20px; }
.sidebar a { color: white; padding: 12px; margin: 5px 0; border-radius: 6px; text-decoration: none; }
.sidebar a:hover { background: #0056b3; }
.sidebar a.logout { margin-top: auto; background: #dc3545; }
.sidebar a.logout:hover { background: #a71d2a; }

/* Main content */
.main { flex-grow: 1; padding: 30px; }
h1 { text-align: center; color: #007bff; margin-bottom: 30px; }

/* Dashboard Cards */
.dashboard-cards {
  display: grid;
  grid-template-columns: repeat(2, 1fr); /* 2 per row */
  gap: 25px;
  max-width: 900px;
  margin: auto;
}

.card {
  background: #fff;
  padding: 25px;
  border-radius: 12px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  text-align: center;
  transition: transform 0.2s;
}
.card:hover { transform: translateY(-5px); }

.card i {
  font-size: 40px;
  color: #007bff;
  margin-bottom: 15px;
}
.card h3 { margin-bottom: 10px; color: #333; }
.card p { font-size: 0.9rem; margin-bottom: 15px; color: #555; }
.card a {
  display: inline-block;
  padding: 8px 15px;
  background: #007bff;
  color: white;
  border-radius: 6px;
  text-decoration: none;
  transition: background 0.3s;
}
.card a:hover { background: #0056b3; }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <h2>M228 Shopping</h2>
      <a href="reseller.php"><i class="fa-solid fa-box"></i> Reseller Dashboard</a>
      <a href="upload.php"><i class="fa-solid fa-box"></i> Upload Products</a>
      <a href="orders.php"><i class="fa-solid fa-cart-shopping"></i> Orders</a>
      <a href="reseller-analytics.php"><i class="fa-solid fa-chart-line"></i> Analytics</a>
      <a href="logout.php" class="logout"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
  </div>

  <!-- Main -->
  <div class="main">
    <h1>Welcome back, <?= htmlspecialchars($resellerName) ?> 🎉</h1>

    <div class="dashboard-cards">
      <!-- Products Card -->
      <div class="card">
        <i class="fa-solid fa-box"></i>
        <h3>My Products</h3>
        <p>Upload and manage the products you sell.</p>
        <a href="my-products.php">Manage Products</a>
      </div>

      <!-- Orders Card -->
      <div class="card">
        <i class="fa-solid fa-receipt"></i>
        <h3>Orders Received</h3>
        <p>View and update orders from buyers.</p>
        <a href="orders.php">View Orders</a>
      </div>

      <!-- Revenue Card -->
      <div class="card">
        <i class="fa-solid fa-sack-dollar"></i>
        <h3>Total Revenue</h3>
        <p>You have earned: <br><strong>R <?= number_format($totalRevenue, 2) ?></strong></p>
        <a href="reseller-analytics.php">View Analytics</a>
      </div>

      <!-- Profile Card -->
      <div class="card">
        <i class="fa-solid fa-user"></i>
        <h3>My Profile</h3>
        <p>Update your details and account settings.</p>
        <a href="reseller-profile.php">Edit Profile</a>
      </div>
    </div>
  </div>
</body>
</html>
