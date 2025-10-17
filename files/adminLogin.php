<?php
/** file: adminLogin.php */
session_start();

/* ---------- Config ---------- */
const RL_MAX_ATTEMPTS = 5;
const RL_WINDOW_SEC   = 600;      // 10 minutes
const REMEMBER_TTL    = 2592000;  // 30 days

/* ---------- DB ---------- */
$conn = @new mysqli('localhost', 'root', '', 'm228_db');
if ($conn->connect_error) {
    $fatalMsg = 'Database connection failed. Please try again later.';
}

/* Ensure tables (idempotent) */
if (empty($fatalMsg)) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS admins (
            admin_id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    /* use a dedicated admin remember-me table to avoid column mismatches */
    $conn->query("
        CREATE TABLE IF NOT EXISTS remember_tokens_admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            selector CHAR(18) NOT NULL UNIQUE,
            hashed_validator CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(admin_id),
            FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE CASCADE
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(100) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(email), INDEX(ip), INDEX(attempted_at)
        )
    ");
}

/* ---------- Helpers ---------- */
function client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function set_secure_cookie(string $name, string $value, int $ttl): void {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
    setcookie($name, $value, [
        'expires'  => time() + $ttl,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clear_cookie(string $name): void {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
    setcookie($name, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function too_many_attempts(mysqli $conn, string $email, string $ip): bool {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM login_attempts WHERE email=? AND ip=? AND attempted_at >= (NOW() - INTERVAL ? SECOND)");
    $window = RL_WINDOW_SEC;
    $stmt->bind_param("ssi", $email, $ip, $window);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ((int)$res['c']) >= RL_MAX_ATTEMPTS;
}

function record_attempt(mysqli $conn, string $email, string $ip): void {
    $stmt = $conn->prepare("INSERT INTO login_attempts (email, ip) VALUES (?, ?)");
    $stmt->bind_param("ss", $email, $ip);
    $stmt->execute();
    $stmt->close();
    $conn->query("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)");
}

function clear_attempts(mysqli $conn, string $email, string $ip): void {
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE email=? AND ip=?");
    $stmt->bind_param("ss", $email, $ip);
    $stmt->execute();
    $stmt->close();
}

/* remember-me helpers for admins */
function create_remember_token_admin(mysqli $conn, int $adminId): string {
    $selector  = bin2hex(random_bytes(9));
    $validator = bin2hex(random_bytes(32));
    $hash      = hash('sha256', $validator);
    $expires   = date('Y-m-d H:i:s', time() + REMEMBER_TTL);

    $stmt = $conn->prepare("INSERT INTO remember_tokens_admins (admin_id, selector, hashed_validator, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $adminId, $selector, $hash, $expires);
    $stmt->execute();
    $stmt->close();

    return $selector . ':' . $validator;
}

function rotate_remember_token_admin(mysqli $conn, int $adminId, string $oldSelector): string {
    $stmt = $conn->prepare("DELETE FROM remember_tokens_admins WHERE admin_id=? AND selector=?");
    $stmt->bind_param("is", $adminId, $oldSelector);
    $stmt->execute();
    $stmt->close();
    return create_remember_token_admin($conn, $adminId);
}

function consume_remember_cookie_admin(mysqli $conn): ?array {
    if (empty($_COOKIE['remember_admin'])) return null;

    $parts = explode(':', $_COOKIE['remember_admin'], 2);
    if (count($parts) !== 2) { clear_cookie('remember_admin'); return null; }

    [$selector, $validator] = $parts;

    $stmt = $conn->prepare("SELECT admin_id, hashed_validator, expires_at FROM remember_tokens_admins WHERE selector=? LIMIT 1");
    $stmt->bind_param("s", $selector);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) { clear_cookie('remember_admin'); return null; }
    if (strtotime($row['expires_at']) < time()) {
        $del = $conn->prepare("DELETE FROM remember_tokens_admins WHERE selector=?");
        $del->bind_param("s", $selector);
        $del->execute();
        $del->close();
        clear_cookie('remember_admin');
        return null;
    }

    $calc = hash('sha256', $validator);
    if (!hash_equals($row['hashed_validator'], $calc)) {
        $uid = (int)$row['admin_id'];
        $zap = $conn->prepare("DELETE FROM remember_tokens_admins WHERE admin_id=?");
        $zap->bind_param("i", $uid);
        $zap->execute();
        $zap->close();
        clear_cookie('remember_admin');
        return null;
    }

    $newToken = rotate_remember_token_admin($conn, (int)$row['admin_id'], $selector);
    set_secure_cookie('remember_admin', $newToken, REMEMBER_TTL);

    return ['admin_id' => (int)$row['admin_id']];
}

/* ---------- Auto-login via Remember Me (admins) ---------- */
if (empty($fatalMsg) && empty($_SESSION['admin_id'])) {
    $payload = consume_remember_cookie_admin($conn);
    if ($payload) {
        $stmt = $conn->prepare("SELECT full_name FROM admins WHERE admin_id=?");
        $stmt->bind_param("i", $payload['admin_id']);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $_SESSION['admin_id']   = $payload['admin_id'];
        $_SESSION['full_name']  = $res['full_name'] ?? 'Admin';

        header("Location: ../index.php");
        exit;
    }
}

/* ---------- CSRF ---------- */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf'];

/* ---------- Handle POST ---------- */
$msg = "";
$email = $_POST['email'] ?? "";

if (empty($fatalMsg) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = client_ip();

    $email_in = trim($_POST['email'] ?? "");
    $password_in = trim($_POST['password'] ?? "");
    $remember = isset($_POST['remember']);
    $posted_csrf = $_POST['csrf'] ?? '';

    $errs = [];
    if (!hash_equals($_SESSION['csrf'], $posted_csrf)) { $errs[] = "Security check failed. Please refresh and try again."; }
    if ($email_in === "" || $password_in === "") { $errs[] = "Email and password are required."; }
    if ($email_in && !filter_var($email_in, FILTER_VALIDATE_EMAIL)) { $errs[] = "Invalid email format."; }
    if (empty($errs) && too_many_attempts($conn, $email_in, $ip)) { $errs[] = "Too many attempts. Please wait a few minutes."; }

    if (empty($errs)) {
        $stmt = $conn->prepare("SELECT admin_id, full_name, password FROM admins WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email_in);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            if (password_verify($password_in, $admin['password'])) {
                $_SESSION['admin_id']   = (int)$admin['admin_id'];
                $_SESSION['full_name']  = $admin['full_name'];

                clear_attempts($conn, $email_in, $ip);

                if ($remember) {
                    $token = create_remember_token_admin($conn, (int)$admin['admin_id']);
                    set_secure_cookie('remember_admin', $token, REMEMBER_TTL);
                } else {
                    clear_cookie('remember_admin');
                }

                header("Location: admin-dashboard.php");
                exit;
            } else {
                $errs[] = "Incorrect password.";
                record_attempt($conn, $email_in, $ip);
            }
        } else {
            $errs[] = "No account found with that email.";
            record_attempt($conn, $email_in, $ip);
        }
        $stmt->close();
    }

    if (!empty($errs)) {
        $msg = '<div class="alert error"><i class="fa-solid fa-circle-exclamation"></i> ' .
               htmlspecialchars(implode(' ', $errs)) . '</div>';
    }

    $email = $email_in; // sticky
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Admin Login | Mashobane & Co</title>

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
  margin:0;font-family:"Poppins",system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
  background: linear-gradient(135deg,#fafaff 0%,#f5f3ff 40%,#f9f9ff 100%);color:var(--text-dark);
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
  width:100%;padding:14px 44px 14px 42px;border:1px solid var(--border);border-radius:12px;background:#fff;outline:none;font-size:14px;
  transition:border-color .15s ease, box-shadow .15s ease;
}
.input::placeholder{color:#9aa0a6}
.input:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(62,15,190,.12)} /* why: accessible focus ring */

.toggle-pass{
  position:absolute;right:14px;top:50%;transform:translateY(-50%);
  cursor:pointer;opacity:.6;transition:opacity .2s ease;
}
.toggle-pass:hover{opacity:.9}

.row-flex{display:flex;align-items:center;justify-content:space-between;margin-top:6px}
.checkbox{display:flex;align-items:center;gap:8px;font-size:14px;color:var(--text-muted)}
.checkbox input{width:auto;margin:0}

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

    <h1>New Here?</h1>
    <p>Admins can sign in to manage the workspace.</p>
    <a class="ghost-btn" href="admin-signup.php">
      <i class="fa-solid fa-user-shield"></i>
      <span>Create Admin Account</span>
    </a>

    <span class="shape s1"></span>
    <span class="shape s2"></span>
    <span class="shape s3"></span>
  </section>

  <section class="panel-right">
    <div class="card" role="region" aria-labelledby="login-title">
      <h2 id="login-title">Admin Sign In</h2>
      <div class="subtitle">Use your admin email to continue</div>

      <?php if(!empty($fatalMsg)): ?>
        <div class="alert error"><i class="fa-solid fa-plug-circle-xmark"></i> <?= htmlspecialchars($fatalMsg) ?></div>
      <?php endif; ?>

      <?= $msg ?>

      <form class="form" method="POST" action="" novalidate>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>"/>

        <div class="group">
          <span class="icon"><i class="fa-regular fa-envelope"></i></span>
          <input class="input" type="email" name="email" placeholder="Email"
                 value="<?= htmlspecialchars($email ?? '') ?>" required aria-label="Email" autocomplete="email" />
        </div>

        <div class="group">
          <span class="icon"><i class="fa-solid fa-lock"></i></span>
          <input id="password" class="input" type="password" name="password" placeholder="Password" minlength="8"
                 required aria-label="Password" autocomplete="current-password" />
          <span id="togglePass" class="toggle-pass" title="Show/Hide password">
            <i class="fa-regular fa-eye"></i>
          </span>
        </div>

        <div class="row-flex">
          <label class="checkbox">
            <input type="checkbox" name="remember" />
            <span>Remember me</span>
          </label>
          <a href="#" class="forgot" style="font-size:14px;text-decoration:none;color:var(--primary)">Forgot password?</a>
        </div>

        <div class="action">
          <button class="btn" type="submit">Login</button>
        </div>
      </form>

      <div class="links">
        <p>User instead? <a href="user-login.php">User login</a> or <a href="userSignup.php">User signup</a></p>
      </div>

      <div class="small-note">Having trouble? Contact support.</div>
    </div>
  </section>
</main>

<script>
const toggle = document.getElementById('togglePass');
const pwd = document.getElementById('password');
toggle?.addEventListener('click', () => {
  const isPwd = pwd.type === 'password';
  pwd.type = isPwd ? 'text' : 'password';
  toggle.innerHTML = isPwd
    ? '<i class="fa-regular fa-eye-slash"></i>'
    : '<i class="fa-regular fa-eye"></i>';
});
</script>

</body>
</html>
