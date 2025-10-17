<?php
/** file: admin-signup.php */
session_start();

/* ---------- DB bootstrap ---------- */
$conn = @new mysqli('localhost', 'root', '', 'm228_db');
if ($conn->connect_error) {
    $fatalMsg = 'Database connection failed. Please try again later.';
} else {
    $conn->query("
        CREATE TABLE IF NOT EXISTS admins (
            admin_id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

/* ---------- CSRF ---------- */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf'];

/* ---------- State ---------- */
$msg        = "";
$full_name  = $_POST['full_name'] ?? "";
$email      = $_POST['email'] ?? "";
$password   = $_POST['password'] ?? "";

/* ---------- Handle POST ---------- */
if (empty($fatalMsg) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $errs = [];

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) { $errs[] = "Security check failed. Please refresh and try again."; } // why: prevents CSRF
    if (trim($full_name) === "" || trim($email) === "" || trim($password) === "") { $errs[] = "All fields are required."; }
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errs[] = "Invalid email format."; }
    if ($password !== "" && strlen($password) < 8) { $errs[] = "Password must be at least 8 characters."; }

    if (empty($errs)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO admins (full_name, email, password) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sss", $full_name, $email, $hashed);
            if ($stmt->execute()) {
                $_SESSION['admin_id']  = $conn->insert_id;
                $_SESSION['full_name'] = $full_name;
                header("Location: admin-dashboard.php"); // adjust if you have an admin dashboard
                exit;
            } else {
                if ($conn->errno == 1062) {
                    $errs[] = "Email already registered.";
                } else {
                    $errs[] = "An unexpected error occurred. Please try again.";
                }
            }
            $stmt->close();
        } else {
            $errs[] = "Failed to prepare signup statement.";
        }
    }

    if (!empty($errs)) {
        $msg = '<div class="alert error"><i class="fa-solid fa-circle-exclamation"></i> ' .
               htmlspecialchars(implode(' ', $errs)) . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Admin Signup | Mashobane & Co</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

<style>
:root{
  --primary:#3e0fbe; --primary-900:#1b0066; --primary-600:#4e25c8;
  --surface:#ffffff; --border:#e5e7eb; --text-dark:#1f1f29; --text-muted:#6b7280;
  --danger:#b91c1c; --success:#0f766e;
}
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0;
  font-family:"Poppins",system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
  background: linear-gradient(135deg,#fafaff 0%,#f5f3ff 40%,#f9f9ff 100%);
  color:var(--text-dark);
}
.auth-shell{min-height:100vh;display:grid;grid-template-columns:1.1fr 1fr;}
.panel-left{
  position:relative;padding:64px 48px;color:#fff;overflow:hidden;
  background:
    radial-gradient(1200px 600px at -10% 110%, rgba(255,255,255,.08) 0%, rgba(255,255,255,0) 60%),
    linear-gradient(160deg,var(--primary-900) 0%,var(--primary) 55%,var(--primary-600) 100%);
}
.brand{display:flex;align-items:center;gap:12px;font-weight:600;letter-spacing:.3px;opacity:.95}
.brand img{width:36px;height:36px;object-fit:contain;filter:drop-shadow(0 2px 6px rgba(0,0,0,.2))}
.panel-left h1{margin:84px 0 12px;font-size:40px;line-height:1.1;letter-spacing:.2px}
.panel-left p{margin:0 0 28px;max-width:420px;color:rgba(255,255,255,.9)}
.ghost-btn{
  display:inline-flex;align-items:center;gap:10px;padding:14px 22px;border:2px solid rgba(255,255,255,.75);
  color:#fff;background:transparent;border-radius:999px;font-weight:600;text-decoration:none;transition:all .2s ease;
}
.ghost-btn:hover{background:rgba(255,255,255,.1);transform:translateY(-1px)}
.shape{position:absolute;border-radius:50%;opacity:.12;pointer-events:none;filter:blur(2px)}
.shape.s1{width:220px;height:220px;right:-60px;top:40px;background:#fff}
.shape.s2{width:320px;height:320px;left:-120px;bottom:-80px;background:#fff}
.shape.s3{width:120px;height:120px;right:80px;bottom:20%;background:#fff;opacity:.08}

.panel-right{display:flex;align-items:center;justify-content:center;background:var(--surface);padding:40px}
.card{
  width:100%;max-width:420px;background:#fff;border:1px solid var(--border);border-radius:24px;
  box-shadow:0 10px 30px rgba(31,41,55,.08);padding:32px 28px;
}
.card h2{margin:6px 0 6px;color:var(--primary-900);font-size:28px}
.subtitle{font-size:13px;color:var(--text-muted);margin-bottom:18px}

.alert{
  display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border-radius:12px;margin:8px 0 14px;font-size:14px;border:1px solid;
}
.alert.error{background:#fff4f4;border-color:#f3c7c7;color:var(--danger)}
.alert.success{background:#f1fcfa;border-color:#bde5df;color:var(--success)}

.form{margin-top:8px}
.group{position:relative;margin:12px 0}
.group .icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);opacity:.6}
.input{
  width:100%;padding:14px 14px 14px 42px;border:1px solid var(--border);border-radius:12px;background:#fff;outline:none;font-size:14px;
  transition:border-color .15s ease, box-shadow .15s ease;
}
.input::placeholder{color:#9aa0a6}
.input:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(62,15,190,.12)} /* why: accessible focus ring */

.action{margin-top:18px}
.btn{
  width:100%;padding:14px 18px;border:none;border-radius:999px;font-weight:700;cursor:pointer;
  background:linear-gradient(135deg,var(--primary) 0%,var(--primary-900) 100%);color:#fff;
  transition:transform .06s ease, filter .2s ease;
}
.btn:hover{filter:brightness(1.05);transform:translateY(-1px)}
.btn:active{transform:translateY(0)}

.links{margin-top:18px;text-align:center;font-size:14px;color:var(--text-muted)}
.links a{color:var(--primary);text-decoration:none;font-weight:600}
.links a:hover{text-decoration:underline}
.small-note{margin-top:8px;color:var(--text-muted);font-size:12px;text-align:center}

@media (max-width:980px){
  .auth-shell{grid-template-columns:1fr}
  .panel-left{min-height:42vh;padding:48px 32px}
  .panel-left h1{margin-top:40px;font-size:34px}
  .panel-right{padding:28px}
}
</style>
</head>
<body>

<main class="auth-shell">
  <section class="panel-left">
    <div class="brand">
      <img src="logo.png" alt="Mashobane & Co logo" />
      <span>Mashobane &amp; Co</span>
    </div>

    <h1>Welcome, Admin!</h1>
    <p>Already have an account? Sign in to manage your workspace.</p>
    <a class="ghost-btn" href="adminLogin.php">
      <i class="fa-solid fa-right-to-bracket"></i>
      <span>Admin Sign In</span>
    </a>

    <span class="shape s1"></span>
    <span class="shape s2"></span>
    <span class="shape s3"></span>
  </section>

  <section class="panel-right">
    <div class="card" role="region" aria-labelledby="signup-title">
      <h2 id="signup-title">Create Admin Account</h2>
      <div class="subtitle">Use your work email for registration</div>

      <?php if(!empty($fatalMsg)): ?>
        <div class="alert error"><i class="fa-solid fa-plug-circle-xmark"></i> <?= htmlspecialchars($fatalMsg) ?></div>
      <?php endif; ?>

      <?= $msg ?>

      <form class="form" method="POST" action="" novalidate>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>"/>

        <div class="group">
          <span class="icon"><i class="fa-solid fa-user-shield"></i></span>
          <input
            class="input"
            type="text"
            name="full_name"
            placeholder="Full Name"
            value="<?= htmlspecialchars($full_name ?? '') ?>"
            required
            aria-label="Full Name"
            autocomplete="name" />
        </div>

        <div class="group">
          <span class="icon"><i class="fa-regular fa-envelope"></i></span>
          <input
            class="input"
            type="email"
            name="email"
            placeholder="Email"
            value="<?= htmlspecialchars($email ?? '') ?>"
            required
            aria-label="Email"
            autocomplete="email" />
        </div>

        <div class="group">
          <span class="icon"><i class="fa-solid fa-lock"></i></span>
          <input
            class="input"
            type="password"
            name="password"
            placeholder="Password (min 8 chars)"
            minlength="8"
            required
            aria-label="Password"
            autocomplete="new-password" />
        </div>

        <div class="action">
          <button class="btn" type="submit">Sign Up</button>
        </div>
      </form>

      <div class="links">
        <p>Already an admin? <a href="adminLogin.php">Login</a></p>
        <p>User instead? <a href="userSignup.php">User signup</a> or <a href="user-login.php">User login</a></p>
      </div>

      <div class="small-note">By creating an account, you agree to our terms and privacy policy.</div>
    </div>
  </section>
</main>

</body>
</html>
