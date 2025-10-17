<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $password  = trim($_POST['password']);

    // Basic validation
    if (empty($full_name) || empty($email) || empty($password)) {
        die("❌ All fields are required.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("❌ Invalid email format.");
    }

    if (strlen($password) < 8) {
        die("❌ Password must be at least 8 characters.");
    }

    // Hash password securely
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Connect to DB
    $conn = new mysqli('localhost', 'root', '', 'm228_db');
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Create resellers table if not exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS resellers (
            reseller_id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Insert reseller
    $stmt = $conn->prepare("INSERT INTO resellers (full_name, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $full_name, $email, $hashed_password);

    if ($stmt->execute()) {
        // Get the ID of the new reseller
        $_SESSION['reseller_id'] = $stmt->insert_id;
        $_SESSION['reseller_email'] = $email;

        header("Location: upload.php");
        exit;
    } else {
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
