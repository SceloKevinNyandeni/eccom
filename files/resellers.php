<?php
session_start();

// 🔒 Admin check
if (!isset($_SESSION['admin_id'])) {
    die("❌ You are not authorized to access this page.");
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'm228_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


// Feedback message
$msg = "";


// ❌ Handle category deletion
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM categories WHERE reseller_id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $msg = "<div class='msg success'>✅ Reseller deleted successfully!</div>";
    }
    $stmt->close();
}

// Fetch all categories
$result = $conn->query("SELECT * FROM resellers ORDER BY full_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Categories</title>
    <style>
         @import url('https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');

*{
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: "Poppins", Arial, Helvetica, sans-serif;
}

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            display: flex;
            background: #c9c9c9ff;
            min-height: 100vh;
        }

        /* Sidebar */
               .sidebar {
            width: 220px; background: #007bff; color: white; padding: 20px;
            flex-shrink: 0; display: flex; flex-direction: column; height: 100vh; position: sticky; top: 0; align-self: flex-start;
        }
        .sidebar h2 { text-align: center; margin-bottom: 20px; }
        .sidebar a { color: white; padding: 12px; margin: 5px 0; border-radius: 6px; text-decoration: none; }
        .sidebar a:hover { background: #0056b3; }
        .sidebar a.logout { margin-top: auto; background: #dc3545; }
        .sidebar a.logout:hover { background: #a71d2a; }

        /* Main content */
        .main {
            flex-grow: 1;
            padding: 30px;
        }

        .container {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            max-width: 900px;
            margin: auto;
        }

        h1 {
            text-align: center;
            margin-bottom: 20px;
            color: hsl(216, 98%, 55%);
        }

        .msg {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
            text-align: center;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        form {
            display: flex;
            justify-content: center;
            margin-bottom: 25px;
            gap: 10px;
        }

        input[type="text"] {
            padding: 10px;
            width: 60%;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1rem;
        }

        button {
            background: hsl(216, 98%, 55%);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s;
        }

        button:hover {
            background: #0056b3;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 10px;
            overflow: hidden;
        }

        th {
            background: hsla(216, 100%, 76%, 1.00);
            color: white;
            padding: 12px;
            text-align: left;
        }

        td {
            border: 1px solid #ddd;
            padding: 10px;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        a.delete-link {
            font-weight: bold;
            text-decoration: none;
            color: #000000ff;
        }

        a.delete-link:hover {
            text-decoration: underline;
            color: #dc3545;
        }
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

    <!-- Main Content -->
    <div class="main">
        <div class="container">
            <h1>Manage Resellers</h1>
            <?= $msg ?>

            <!-- Categories Table -->
            <table>
                <tr>
                    <th>Reseller ID</th>
                    <th>Reseller Name</th>
                    <th>Action</th>
                </tr>
                <?php while ($row = $result->fetch_assoc()) : ?>
                <tr>
                    <td><?= $row['reseller_id'] ?></td>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td>
                        <a class="delete-link" href="?delete_id=<?= $row['reseller_id'] ?>" onclick="return confirm('Delete this Reseller?')">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
