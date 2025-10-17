<?php
session_start();

// 🔒 Reseller session check
if (!isset($_SESSION['reseller_id'])) {
    die("❌ Unauthorized. Please <a href='resellerLogin.html'>login</a> first.");
}

$resellerId = $_SESSION['reseller_id'];

// DB connection
$conn = new mysqli('localhost', 'root', '', 'm228_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1️⃣ Total revenue
$totalRevenue = 0;
$res = $conn->query("SELECT SUM(total_amount) AS revenue FROM orders WHERE reseller_id = $resellerId");
if ($row = $res->fetch_assoc()) {
    $totalRevenue = $row['revenue'] ?? 0;
}

// 2️⃣ User with most spending
$topBuyer = $conn->query("
    SELECT u.full_name, SUM(o.total_amount) AS spent
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    WHERE o.reseller_id = $resellerId
    GROUP BY o.user_id
    ORDER BY spent DESC
    LIMIT 1
")->fetch_assoc();

// 3️⃣ Month with highest sales
$bestMonth = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, SUM(total_amount) AS total
    FROM orders
    WHERE reseller_id = $resellerId
    GROUP BY month
    ORDER BY total DESC
    LIMIT 1
")->fetch_assoc();

// 4️⃣ Revenue per month (Line Chart)
$monthly = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, SUM(total_amount) AS revenue
    FROM orders
    WHERE reseller_id = $resellerId
    GROUP BY month
    ORDER BY month ASC
");
$months = [];
$revenues = [];
while ($row = $monthly->fetch_assoc()) {
    $months[] = $row['month'];
    $revenues[] = $row['revenue'];
}

// 5️⃣ Revenue per user (Pie Chart)
$userRevenue = $conn->query("
    SELECT u.full_name, SUM(o.total_amount) AS revenue
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    WHERE o.reseller_id = $resellerId
    GROUP BY u.user_id
    ORDER BY revenue DESC
");
$userNames = [];
$userRevenues = [];
while ($row = $userRevenue->fetch_assoc()) {
    $userNames[] = $row['full_name'];
    $userRevenues[] = $row['revenue'];
}

// 6️⃣ Orders per month (Bar Chart)
$orderMonthly = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(order_id) AS orders
    FROM orders
    WHERE reseller_id = $resellerId
    GROUP BY month
    ORDER BY month ASC
");
$orderMonths = [];
$orderCounts = [];
while ($row = $orderMonthly->fetch_assoc()) {
    $orderMonths[] = $row['month'];
    $orderCounts[] = $row['orders'];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reseller Analytics</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Poppins", sans-serif; }
        body { display: flex; background: #f4f7fa; min-height: 100vh; }

        /* Sidebar */
        .sidebar {
            width: 220px; background: #3e0fbeff; color: white; padding: 20px;
            flex-shrink: 0; display: flex; flex-direction: column; height: 100vh;
            position: sticky; top: 0; /* ✅ Sticky sidebar */
        }
        .sidebar h2 { text-align: center; margin-bottom: 20px; }
        .sidebar a { color: white; padding: 12px; margin: 5px 0; border-radius: 6px; text-decoration: none; }
        .sidebar a:hover { background: #1b0066ff; }
        .sidebar a.logout { margin-top: auto; background: #dc3545; }
        .sidebar a.logout:hover { background: #a71d2a; }

        /* Main */
        .main { flex-grow: 1; padding: 30px; overflow-y: auto; }
        h1 { text-align: center; color: #3e0fbeff; margin-bottom: 30px; }

        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr); /* ✅ 3-column layout */
            gap: 25px;
            margin: auto;
        }
        .card {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        .card i {
            font-size: 40px;
            color: #3e0fbeff;
            margin-bottom: 15px;
        }
        .card h3 { margin-bottom: 10px; color: #333; }
        .card p { font-size: 1.1rem; font-weight: bold; color: #3e0fbeff; }

        /* Chart */
        .chart-container {
            margin: 40px auto;
            max-width: 900px;
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .chart-container h2 {
            text-align: center; 
            margin-bottom: 20px; 
            color: #3e0fbeff;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Reseller Panel</h2>
        <a href="reseller.php"><i class="fa-solid fa-box"></i> Reseller Dashboard</a>
        <a href="upload.php"><i class="fa-solid fa-upload"></i> Upload Products</a>
        <a href="orders.php"><i class="fa-solid fa-cart-shopping"></i> Orders</a>
        <a href="reseller-analytics.php"><i class="fa-solid fa-chart-line"></i> Analytics</a>
        <a href="logout.php" class="logout"><i class="fa-solid fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main -->
    <div class="main">
        <h1>📊 Reseller Analytics</h1>

        <!-- Summary Cards -->
        <div class="dashboard-cards">
            <div class="card">
                <i class="fa-solid fa-coins"></i>
                <h3>Total Revenue</h3>
                <p>R <?= number_format($totalRevenue, 2) ?></p>
            </div>
            <div class="card">
                <i class="fa-solid fa-user"></i>
                <h3>Top Buyer</h3>
                <p><?= $topBuyer['full_name'] ?? 'N/A' ?><br>
                   R <?= number_format($topBuyer['spent'] ?? 0, 2) ?></p>
            </div>
            <div class="card">
                <i class="fa-solid fa-calendar"></i>
                <h3>Best Month</h3>
                <p><?= $bestMonth['month'] ?? 'N/A' ?><br>
                   R <?= number_format($bestMonth['total'] ?? 0, 2) ?></p>
            </div>
        </div>

        <!-- Line Chart -->
        <div class="chart-container">
            <h2>📈 Revenue Over Months</h2>
            <canvas id="lineChart"></canvas>
        </div>

        <!-- Pie Chart -->
        <div class="chart-container">
            <h2>🥧 Revenue per User</h2>
            <canvas id="pieChart"></canvas>
        </div>

        <!-- Bar Chart -->
        <div class="chart-container">
            <h2>📊 Orders per Month</h2>
            <canvas id="barChart"></canvas>
        </div>
    </div>

    <script>
        // 📈 Line Chart
        new Chart(document.getElementById('lineChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode($months) ?>,
                datasets: [{
                    label: 'Revenue (R)',
                    data: <?= json_encode($revenues) ?>,
                    borderColor: '#3e0fbeff',
                    backgroundColor: 'rgba(62,15,190,0.2)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: { responsive: true, scales: { y: { beginAtZero: true } } }
        });

        // 🥧 Pie Chart
        new Chart(document.getElementById('pieChart').getContext('2d'), {
            type: 'pie',
            data: {
                labels: <?= json_encode($userNames) ?>,
                datasets: [{
                    data: <?= json_encode($userRevenues) ?>,
                    backgroundColor: ['#3e0fbe','#28a745','#ffc107','#dc3545','#17a2b8','#6f42c1','#fd7e14','#20c997']
                }]
            },
            options: { responsive: true }
        });

        // 📊 Bar Chart
        new Chart(document.getElementById('barChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($orderMonths) ?>,
                datasets: [{
                    label: 'Orders',
                    data: <?= json_encode($orderCounts) ?>,
                    backgroundColor: '#28a745'
                }]
            },
            options: { responsive: true, scales: { y: { beginAtZero: true } } }
        });
    </script>
</body>
</html>
