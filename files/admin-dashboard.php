<?php
session_start();

// 🔒 Admin check
if (!isset($_SESSION['admin_id'])) {
    die('<i class="fa-solid fa-triangle-exclamation" style="color:red;"></i> You are not authorized to access this page.');
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'm228_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$adminId = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT full_name FROM admins WHERE admin_id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$result = $stmt->get_result();
$adminRow = $result->fetch_assoc();

$adminName = $adminRow['full_name'] ?? "Admin";

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard</title>
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
.sidebar a { 
  color: white; 
  padding: 12px; 
  margin: 5px 0; 
  border-radius: 6px; 
  text-decoration: none; 
  display: flex; 
  align-items: center; 
  gap: 10px;
}
.sidebar a:hover { background: #0056b3; }
.sidebar a.logout { margin-top: auto; background: #dc3545; }
.sidebar a.logout:hover { background: #a71d2a; }

/* Main content */
.main { flex-grow: 1; padding: 30px; }
h1 { 
  text-align: center; 
  color: #007bff; 
  margin-bottom: 30px; 
  display: flex; 
  justify-content: center; 
  align-items: center; 
  gap: 10px; 
}

/* Dashboard Cards */
.dashboard-cards {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
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
    <h2><i class="fa-solid fa-user-shield"></i> M228 Admin</h2>
    <a href="admin-dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
    <a href="categories.php"><i class="fa-solid fa-folder-tree"></i> Categories</a>
    <!--<a href="products.php"><i class="fa-solid fa-box"></i> Products</a>-->
    <a href="resellers.php"><i class="fa-solid fa-users"></i> Resellers</a>
    <a href="analytics.php"><i class="fa-solid fa-chart-line"></i> Analytics</a>
    <a href="logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </div>

  <!-- Main -->
  <div class="main">
    <h1><i class="fa-solid fa-user-tie"></i> Welcome back, <?= htmlspecialchars($adminName) ?></h1>

    <div class="dashboard-cards">
      <!-- Categories Card -->
      <div class="card">
        <i class="fa-solid fa-folder-open"></i>
        <h3>Manage Categories</h3>
        <p>Create, update, or delete product categories.</p>
        <a href="categories.php">Go to Categories</a>
      </div>

      <!-- Products Card -->
      <div class="card">
        <i class="fa-solid fa-box"></i>
        <h3>Manage Products</h3>
        <p>View and manage product listings.</p>
        <a href="index/index.html">Go to Products</a>
      </div>

      <!-- Users Card -->
      <div class="card">
        <i class="fa-solid fa-user-gear"></i>
        <h3>Reseller Accounts</h3>
        <p>View all reseller accounts and details.</p>
        <a href="resellers.php">Go to Users</a>
      </div>

      <!-- Analytics Card -->
      <div class="card">
        <i class="fa-solid fa-chart-pie"></i>
        <h3>Analytics</h3>
        <p>Check system insights and revenue stats.</p>
        <a href="analytics.php">View Analytics</a>
      </div>
    </div>
  </div>
</body>
</html>
