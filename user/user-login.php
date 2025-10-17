<?php
session_start();

// Connect to database
$conn = new mysqli('localhost', 'root', '', 'm228_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ Ensure table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            header("Location: ../index.php"); // redirect after login
            exit();
        } else {
            $msg = "<p class='error'>❌ Incorrect password.</p>";
        }
    } else {
        $msg = "<p class='error'>⚠️ No account found with that email.</p>";
    }

    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>User Login | Mashobane & Co</title>
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
.login-box {
    background: white;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    width: 350px;
    text-align: center;
}
h2 { color: #3e0fbeff; margin-bottom: 20px; }
input {
    width: 93%; padding: 12px; margin: 10px 0;
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

<div class="login-box">
    <h2>Login to Your Account</h2>
    <?= $msg ?>

    <form method="POST" action="">
        <input type="email" name="email" placeholder="Email Address" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>

    <div class="links">
        <p>Don’t have an account? <a href="userSignup.php">Sign up</a></p>
        <p>Are you a reseller? <a href="resellerLogin.php">Login</a> or <a href="resellerSignup.php">Register</a></p>
    </div>
</div>

</body>
</html>
