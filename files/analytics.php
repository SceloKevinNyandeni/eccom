<?php
session_start();

// 🔒 Admin check
if (!isset($_SESSION['admin_id'])) {
    die("❌ You are not authorized to access this page.");
}

$conn = new mysqli('localhost', 'root', '', 'm228_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 1️⃣ Total resellers
$totalResellers = $conn->query("SELECT COUNT(*) AS total FROM resellers")->fetch_assoc()['total'];

// 2️⃣ New resellers this month
$newThisMonth = $conn->query("
    SELECT COUNT(*) AS total 
    FROM resellers 
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
      AND YEAR(created_at) = YEAR(CURRENT_DATE())
")->fetch_assoc()['total'];

// 3️⃣ Total revenue
$totalRevenue = $conn->query("SELECT SUM(total_amount) AS revenue FROM orders")->fetch_assoc()['revenue'] ?? 0;

// 4️⃣ Top reseller by revenue
$topReseller = $conn->query("
    SELECT r.full_name, SUM(o.total_amount) AS revenue
    FROM resellers r
    JOIN orders o ON r.reseller_id = o.reseller_id
    GROUP BY r.reseller_id
    ORDER BY revenue DESC
    LIMIT 1
")->fetch_assoc();

// 5️⃣ Revenue per reseller (bar chart)
$res = $conn->query("
    SELECT r.full_name, SUM(o.total_amount) AS revenue
    FROM resellers r
    LEFT JOIN orders o ON r.reseller_id = o.reseller_id
    GROUP BY r.reseller_id
    ORDER BY revenue DESC
");
$resellerNames = [];
$resellerRevenue = [];
while ($row = $res->fetch_assoc()) {
    $resellerNames[] = $row['full_name'];
    $resellerRevenue[] = $row['revenue'] ?? 0;
}

// 6️⃣ Top 5 resellers by revenue (table)
$topResellers = $conn->query("
    SELECT r.full_name, SUM(o.total_amount) AS revenue
    FROM resellers r
    LEFT JOIN orders o ON r.reseller_id = o.reseller_id
    GROUP BY r.reseller_id
    ORDER BY revenue DESC
    LIMIT 5
");

// 7️⃣ Revenue over time (line chart)
$monthly = $conn->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, SUM(total_amount) AS revenue
    FROM orders
    GROUP BY month
    ORDER BY month ASC
");
$months = [];
$revenues = [];
while ($row = $monthly->fetch_assoc()) {
    $months[] = $row['month'];
    $revenues[] = $row['revenue'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reseller Analytics</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
         @import url('https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');

*{
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: "Poppins", Arial, Helvetica, sans-serif;
}
        body { font-family: 'Segoe UI', sans-serif; margin: 0; display: flex; background: #f4f7fa; }
        .sidebar {
            width: 220px; background: #007bff; color: white; padding: 20px;
            flex-shrink: 0; display: flex; flex-direction: column; height: 100vh; position: sticky; top: 0; align-self: flex-start;
        }
        .sidebar h2 { text-align: center; margin-bottom: 20px; }
        .sidebar a { color: white; padding: 12px; margin: 5px 0; border-radius: 6px; text-decoration: none; }
        .sidebar a:hover { background: #0056b3; }
        .sidebar a.logout { margin-top: auto; background: #dc3545; }
        .sidebar a.logout:hover { background: #a71d2a; }

        .main { flex-grow: 1; padding: 30px; }
        h1 { text-align: center; color: #007bff; margin-bottom: 20px; }

        .cards { display: flex; gap: 20px; justify-content: center; margin-bottom: 30px; flex-wrap: wrap; }
        .card {
            background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            flex: 1; min-width: 200px; text-align: center; font-size: 1.2rem;
        }
        .card h2 { margin: 0; color: #333; font-size: 1rem; margin-top: 10px; }
        .card p { margin: 5px 0 0; font-weight: bold; color: #007bff; font-size: 1.4rem; }

        /* Charts row */
        .charts-row {
            display: flex;
            gap: 20px;
            justify-content: center;
            align-items: flex-start;
            flex-wrap: wrap; /* stack on small screens */
            margin: 30px 0;
        }
        .chart-box {
            flex: 1;
            min-width: 400px;
            max-width: 600px;
            height: 500px;
            background: #fff;
            padding: 15px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .chart-box h2 {
            text-align: center;
            margin-bottom: 10px;
        }
        .chart-box canvas {
            width: 100% !important;
            height: 100% !important;
        }

        table { width: 100%; max-width: 600px; margin: 30px auto; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        th { background: #007bff; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
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

    <!-- Main content -->
    <div class="main">
        <h1>Reseller Analytics</h1>

        <!-- Summary Cards -->
        <div class="cards">
            <div class="card"><h2>Total Resellers</h2><p><?= $totalResellers ?></p></div>
            <div class="card"><h2>New This Month</h2><p><?= $newThisMonth ?></p></div>
            <div class="card"><h2>Total Revenue</h2><p>R <?= number_format($totalRevenue, 2) ?></p></div>
            <div class="card"><h2>Top Reseller</h2><p><?= $topReseller['full_name'] ?? 'N/A' ?><br>R <?= number_format($topReseller['revenue'] ?? 0, 2) ?></p></div>
        </div>

        <!-- Charts Row -->
        <div class="charts-row">
            <!-- Revenue Per Reseller -->
            <div class="chart-box">
                <h2>📊 Revenue Per Reseller</h2>
                <canvas id="revenueChart"></canvas>
            </div>

            <!-- Revenue Over Time -->
            <div class="chart-box">
                <h2>📈 Revenue Over Time</h2>
                <canvas id="revenueLine"></canvas>
            </div>
        </div>

        <script>
        // Bar Chart: Revenue Per Reseller
        const ctxBar = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: <?= json_encode($resellerNames) ?>,
                datasets: [{
                    label: 'Revenue (R)',
                    data: <?= json_encode($resellerRevenue) ?>,
                    backgroundColor: '#28a745'
                }]
            },
             options: {
                 responsive: true,
                 maintainAspectRatio: false,
                 layout: {
             padding: {
                 top: 10,
                 right: 20,
                 bottom: 30,   // ✅ extra space for labels
                 left: 20
    }
  },
  scales: {
    x: {
      ticks: {
        autoSkip: false,   // ✅ show all names
        maxRotation: 45,   // ✅ rotate if too long
        minRotation: 30
      }
    },
    y: {
      beginAtZero: true
    }
  },
  plugins: {
    legend: { display: false }
  }
}
        });

        // Line Chart: Revenue Over Time
        const ctxLine = document.getElementById('revenueLine').getContext('2d');
        new Chart(ctxLine, {
            type: 'line',
            data: {
                labels: <?= json_encode($months) ?>,
                datasets: [{
                    label: 'Monthly Revenue (R)',
                    data: <?= json_encode($revenues) ?>,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0,123,255,0.2)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                 responsive: true,
                 maintainAspectRatio: false,
                 layout: {
             padding: {
                 top: 10,
                 right: 20,
                 bottom: 30,   // ✅ extra space for labels
                 left: 20
    }
  },
  scales: {
    x: {
      ticks: {
        autoSkip: false,   // ✅ show all names
        maxRotation: 45,   // ✅ rotate if too long
        minRotation: 30
      }
    },
    y: {
      beginAtZero: true
    }
  },
  plugins: {
    legend: { display: false }
  }
}
        });
        </script>

        <!-- Top 5 Resellers by Revenue -->
        <h2 style="text-align:center; margin-top:2.5em;">👑 Top Resellers by Revenue</h2>
        <table>
            <tr><th>Name</th><th>Revenue</th></tr>
            <?php while ($row = $topResellers->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td>R <?= number_format($row['revenue'] ?? 0, 2) ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>

<?php $conn->close(); ?>
