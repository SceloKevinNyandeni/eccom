<?php
/** file: auth/user-signup.php */
session_start();

$msg = "";
$full_name = $_POST['full_name'] ?? "";
$email     = $_POST['email'] ?? "";
$password  = $_POST['password'] ?? "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    if (trim($full_name) === "" || trim($email) === "" || trim($password) === "") {
        $errors[] = "All fields are required.";
    }
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if ($password && strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $conn = new mysqli('localhost', 'root', '', 'm228_db');
        if ($conn->connect_error) {
            $errors[] = "Database connection failed.";
        } else {
            $conn->query("
                CREATE TABLE IF NOT EXISTS users (
                    user_id INT AUTO_INCREMENT PRIMARY KEY,
                    full_name VARCHAR(100) NOT NULL,
                    email VARCHAR(100) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sss", $full_name, $email, $hashed_password);
                if ($stmt->execute()) {
                    $_SESSION['user_id']    = $conn->insert_id;
                    $_SESSION['user_name']  = $full_name;
                    $_SESSION['user_email'] = $email;
                    header("Location: ../index.php");
                    exit;
                } else {
                    if ($conn->errno == 1062) {
                        $errors[] = "Email already registered.";
                    } else {
                        $errors[] = "An unexpected error occurred. Please try again.";
                    }
                }
                $stmt->close();
            } else {
                $errors[] = "Failed to prepare signup statement.";
            }
            $conn->close();
        }
    }

    if (!empty($errors)) {
        $msg = '<div class="alert error"><i class="fa-solid fa-circle-exclamation"></i> ' .
               htmlspecialchars(implode(' ', $errors)) . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>User Signup | Mashobane & Co</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

<style>
:root{
  --primary:#3e0fbe;
  --primary-900:#1b0066;
  --primary-600:#4e25c8;
  --primary-100:#efeafe;
  --text-dark:#1f1f29;
  --text-muted:#6b7280;
  --surface:#ffffff;
  --border:#e5e7eb;
  --danger:#b91c1c;
  --success:#0f766e;
}
*{box-sizing:border-box}
html,body{height:100%}

/* Center the card, no full-screen layout */
body{
  margin:0;
  font-family:"Poppins",system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
  background: linear-gradient(135deg,#fafaff 0%,#f5f3ff 40%,#f9f9ff 100%);
  color:var(--text-dark);
  min-height:100vh;
  display:grid;
  place-items:center; /* centers the container */
  padding:24px;       /* breathing room on small screens */
}

/* The container itself, fixed width and rounded */
.auth-shell{
  width:min(980px, 96vw);
  display:grid;
  grid-template-columns: 1.1fr 1fr;
  border-radius:24px;
  overflow:hidden; /* rounds inner panels */
  box-shadow:0 20px 60px rgba(31,41,55,.15);
  background:var(--surface);
  border:1px solid var(--border);
}

/* Left welcome panel stays gradient, but only inside the centered card */
.panel-left{
  position:relative;
  padding:48px 40px;
  color:#fff;
  background:
    radial-gradient(1200px 600px at -10% 110%, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0) 60%),
    linear-gradient(160deg, var(--primary-900) 0%, var(--primary) 55%, var(--primary-600) 100%);
  overflow:hidden;
}
.brand{display:flex;align-items:center;gap:12px;font-weight:600;letter-spacing:.3px;opacity:.95}
.panel-left h1{margin:44px 0 12px;font-size:34px;line-height:1.15;letter-spacing:.2px}
.panel-left p{margin:0 0 24px;max-width:420px;color:rgba(255,255,255,.9)}
.panel-left .ghost-btn{
  display:inline-flex;align-items:center;gap:10px;padding:12px 18px;border:2px solid rgba(255,255,255,.75);
  color:#fff;background:transparent;border-radius:999px;font-weight:600;text-decoration:none;transition:all .2s ease;
}
.panel-left .ghost-btn:hover{background:rgba(255,255,255,.1);transform:translateY(-1px)}
.shape{position:absolute;border-radius:50%;opacity:.12;pointer-events:none;filter:blur(2px)}
.shape.s1{ width:180px;height:180px; right:-60px; top:20px; background:#fff; }
.shape.s2{ width:260px;height:260px; left:-120px; bottom:-80px; background:#fff; }
.shape.s3{ width:100px;height:100px; right:60px; bottom:18%; background:#fff; opacity:.08; }

/* Right form panel */
.panel-right{
  display:flex;align-items:center;justify-content:center;
  background:var(--surface);
  padding:28px;
}

.card{
  width:100%;
  max-width:420px;
  background:#fff;
  border:1px solid var(--border);
  border-radius:20px;
  box-shadow:0 10px 30px rgba(31,41,55,.08);
  padding:28px 24px;
}
.card h2{margin:6px 0 6px;color:var(--primary-900);font-size:26px}
.subtitle{font-size:13px;color:var(--text-muted);margin-bottom:16px}

.alert{
  display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border-radius:12px;margin:8px 0 14px;font-size:14px;border:1px solid;
}
.alert.error{ background:#fff4f4;border-color:#f3c7c7;color:var(--danger); }
.alert.success{ background:#f1fcfa;border-color:#bde5df;color:var(--success); }

.form{margin-top:8px}
.group{position:relative;margin:12px 0}
.group .icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);opacity:.6}
.input{
  width:100%;padding:14px 14px 14px 42px;border:1px solid var(--border);border-radius:12px;background:#fff;outline:none;font-size:14px;
  transition:border-color .15s ease, box-shadow .15s ease;
}
.input::placeholder{color:#9aa0a6}
.input:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(62,15,190,.12)} /* why: accessible focus */

.action{margin-top:16px}
.btn{
  width:100%;padding:14px 18px;border:none;border-radius:999px;font-weight:700;cursor:pointer;
  background:linear-gradient(135deg,var(--primary) 0%, var(--primary-900) 100%);color:#fff;
  transition:transform .06s ease, filter .2s ease;
}
.btn:hover{ filter:brightness(1.05); transform:translateY(-1px); }
.btn:active{ transform:translateY(0); }

.links{margin-top:16px;text-align:center;font-size:14px;color:var(--text-muted)}
.links a{color:var(--primary);text-decoration:none;font-weight:600}
.links a:hover{ text-decoration:underline; }
.small-note{margin-top:8px;color:var(--text-muted);font-size:12px;text-align:center}

/* stack on small screens */
@media (max-width: 980px){
  .auth-shell{ grid-template-columns: 1fr; width:min(680px, 96vw); }
  .panel-left{ padding:36px 28px }
  .panel-left h1{ margin-top:12px; font-size:28px }
  .panel-right{ padding:22px }
}
</style>
</head>
<body>

<main class="auth-shell">
  <section class="panel-left">
    <div class="brand"><span>Mashobane &amp; Co</span></div>

    <h1>Welcome Back!</h1>
    <p>To keep connected with us, please sign in with your personal info.</p>
    <a class="ghost-btn" href="user-login.php">
      <i class="fa-solid fa-right-to-bracket"></i>
      <span>Sign In</span>
    </a>

    <span class="shape s1"></span>
    <span class="shape s2"></span>
    <span class="shape s3"></span>
  </section>

  <section class="panel-right">
    <div class="card" role="region" aria-labelledby="signup-title">
      <h2 id="signup-title">Create Account</h2>
      <div class="subtitle">or use your email for registration</div>

      <?= $msg ?>

      <form class="form" method="POST" action="" novalidate>
        <div class="group">
          <span class="icon"><i class="fa-solid fa-user"></i></span>
          <input class="input" type="text" name="full_name" placeholder="Full Name"
                 value="<?= htmlspecialchars($full_name ?? '') ?>" required aria-label="Full Name" autocomplete="name" />
        </div>

        <div class="group">
          <span class="icon"><i class="fa-regular fa-envelope"></i></span>
          <input class="input" type="email" name="email" placeholder="Email"
                 value="<?= htmlspecialchars($email ?? '') ?>" required aria-label="Email" autocomplete="email" />
        </div>

        <div class="group">
          <span class="icon"><i class="fa-solid fa-lock"></i></span>
          <input class="input" type="password" name="password" placeholder="Password" minlength="8"
                 required aria-label="Password" autocomplete="new-password" />
        </div>

        <div class="action">
          <button class="btn" type="submit">Sign Up</button>
        </div>
      </form>

      <div class="links">
        <p>Already have an account? <a href="user-login.php">Login</a></p>
        <p>Are you a reseller? <a href="resellerSignup.php">Sign up here</a> or <a href="resellerLogin.php">Login</a></p>
      </div>

      <div class="small-note">By creating an account, you agree to our terms and privacy policy.</div>
    </div>
  </section>
</main>

</body>
</html>
