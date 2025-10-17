<?php
session_start();
$msg = "";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $password  = trim($_POST['password']);

    // ✅ Basic validation
    if (empty($full_name) || empty($email) || empty($password)) {
        die("❌ All fields are required.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("❌ Invalid email format.");
    }

    if (strlen($password) < 8) {
        die("❌ Password must be at least 8 characters.");
    }

    // ✅ Hash password securely
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // ✅ Connect to database
    $conn = new mysqli('localhost', 'root', '', 'm228_db');
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // ✅ Create users table if it doesn’t exist
    $conn->query("
        CREATE TABLE IF NOT EXISTS users (
            user_id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // ✅ Insert new user record
    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $full_name, $email, $hashed_password);

    if ($stmt->execute()) {
        // ✅ Create session for this new user
        $_SESSION['user_id']    = $conn->insert_id;
        $_SESSION['user_name']  = $full_name;
        $_SESSION['user_email'] = $email;

        // ✅ Redirect to user dashboard
        header("Location: ../index.php");
        exit;
    } else {
        // ✅ Handle duplicate or general errors
        if ($conn->errno == 1062) {
            echo "❌ Email already registered!";
        } else {
            echo "❌ Error: " . $conn->error;
        }
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>User Signup | Mashobane & Co</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<style>
body {
    font-family: "Poppins", sans-serif;
    background: url('logo.png');
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
}
.signup-box {
    background: white;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    width: 350px;
    text-align: center;
}
h2 { color: #3e0fbeff; margin-bottom: 20px; }
input {
    width: 93%; padding: 12px 10px; margin: 10px 0;
    border-radius: 6px; border: 1px solid #ccc;
}
button {
    width: 100%; padding: 12px;
    background: #3e0fbeff; color: white;
    border: none; border-radius: 6px;
    cursor: pointer; font-weight: bold;
}
button:hover { background: #1b0066ff; }
p { margin-top: 15px; font-size: 14px; }
.links a { color: #3e0fbeff; font-weight: 600; text-decoration: none; }
.links a:hover { text-decoration: underline; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
</style>
</head>
<body>

<div class="signup-box">
    <h2>Create Your Account</h2>
    <?= $msg ?>

    <form method="POST" action="">
        <input type="text" name="full_name" placeholder="Full Name" required>
        <input type="email" name="email" placeholder="Email Address" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Sign Up</button>
    </form>

    <div class="links">
        <p>Already have an account? <a href="user-login.php">Login</a></p>
        <p>Are you a reseller? <a href="resellerSignup.php">Sign up here</a> or <a href="resellerLogin.php">Login</a></p>
    </div>
</div>

</body>
</html>
