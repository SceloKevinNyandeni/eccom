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

// Ensure categories table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS categories (
        category_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// Feedback message
$msg = "";

// ➕ Handle category creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['category_name'])) {
    $category_name = trim($_POST['category_name']);
    if (!empty($category_name)) {
        $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->bind_param("s", $category_name);
        if ($stmt->execute()) {
            $msg = "<div class='msg success'>✅ Category created successfully!</div>";
        } else if ($conn->errno == 1062) {
            $msg = "<div class='msg error'>❌ Category already exists!</div>";
        } else {
            $msg = "<div class='msg error'>❌ Error: " . htmlspecialchars($conn->error) . "</div>";
        }
        $stmt->close();
    } else {
        $msg = "<div class='msg error'>❌ Category name cannot be empty!</div>";
    }
}

// ❌ Handle category deletion
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $msg = "<div class='msg success'>✅ Category deleted successfully!</div>";
    }
    $stmt->close();
}

// Fetch all categories
$result = $conn->query("SELECT * FROM categories ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" 
      integrity="sha512-KP1Z9g3X+sxTQn5oYk0iX8u8FxJw2U8VkvNpgG0VAp2K9fF4GczXr4Xv9pjY4BtkK0Hw0bFiC+0Th+0R0Ok2ig==" 
      crossorigin="anonymous" referrerpolicy="no-referrer" />

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
            color: #dc3545;
            font-weight: bold;
            text-decoration: none;
        }

        a.delete-link:hover {
            text-decoration: underline;
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
            <h1>Manage Categories</h1>
            <?= $msg ?>

            <!-- Create Category Form -->
            <form method="post" action="">
                <input type="text" name="category_name" placeholder="New category name" required>
                <button type="submit">Create Category</button>
            </form>

            <!-- Categories Table -->
            <table>
                <tr>
                    <th>ID</th>
                    <th>Category Name</th>
                    <th>Action</th>
                </tr>
                <?php while ($row = $result->fetch_assoc()) : ?>
                <tr>
                    <td><?= $row['category_id'] ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td>
                        <a class="delete-link" href="?delete_id=<?= $row['category_id'] ?>" onclick="return confirm('Delete this category?')">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
