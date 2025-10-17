<?php
session_start();

// 🔒 Admin check
if (!isset($_SESSION['admin_id'])) {
    die("❌ You are not authorized to access this page.");
}

// Database connection (always after session check)
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" 
          integrity="sha512-KP1Z9g3X+sxTQn5oYk0iX8u8FxJw2U8VkvNpgG0VAp2K9fF4GczXr4Xv9pjY4BtkK0Hw0bFiC+0Th+0R0Ok2ig==" 
          crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
       @import url('https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');

*{
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: "Poppins", Arial, Helvetica, sans-serif;
}

        body { font-family: 'Segoe UI', sans-serif; display: flex; background: #f4f7fa; min-height: 100vh; }

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
    grid-template-columns: repeat(2, 1fr); /* 2 cards per row */
    gap: 25px;
    max-width: 900px; /* keeps layout neat */
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
        <a href="admin-dashboard.php">🏠 Dashboard</a>
        <a href="categories.php">📂 Categories</a>
        <!--<a href="products.php">📦 Products</a>-->
        <a href="resellers.php">👥 Resellers</a>
        <a href="analytics.php">📊 Analytics</a>
        <a href="logout.php" class="logout">🚪 Logout</a>
    </div>

    <!-- Main -->
    <div class="main">
        <h1>Welcome back, <?= htmlspecialchars($adminName) ?></h1>

        <div class="dashboard-cards">
            <!-- Categories Card -->
            <div class="card">
                <i class="fa-solid fa-folder"></i>
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
                <i class="fa-solid fa-user"></i>
                <h3>Reseller Accounts</h3>
                <p>View all reseller accounts and details.</p>
                <a href="resellers.php">Go to Users</a>
            </div>

            <!-- Analytics Card -->
            <div class="card">
                <i class="fa-solid fa-chart-line"></i>
                <h3>Analytics</h3>
                <p>Check system insights and revenue stats.</p>
                <a href="analytics.php">View Analytics</a>
            </div>
        </div>
    </div>
</body>
</html>
