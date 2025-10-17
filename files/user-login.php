<?php
/* ============================
   file: auth/user-login.php
   ============================ */
session_start();

/* ---------- Config ---------- */
const RL_MAX_ATTEMPTS = 5;
const RL_WINDOW_SEC   = 600;      // 10 minutes
const REMEMBER_TTL    = 2592000;  // 30 days

/* ---------- DB ---------- */
$fatalMsg = "";
$conn = @new mysqli('localhost', 'root', '', 'm228_db');
if ($conn->connect_error) {
    $fatalMsg = 'Database connection failed. Please try again later.';
}

/* Ensure tables (idempotent) */
if (empty($fatalMsg)) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS users (
            user_id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS remember_tokens_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            selector CHAR(18) NOT NULL UNIQUE,
            hashed_validator CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(user_id),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
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
    $win = RL_WINDOW_SEC;
    $stmt->bind_param("ssi", $email, $ip, $win);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ((int)($res['c'] ?? 0)) >= RL_MAX_ATTEMPTS;
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
/* remember-me (users) */
function create_remember_token_user(mysqli $conn, int $userId): string {
    $selector  = bin2hex(random_bytes(9));
    $validator = bin2hex(random_bytes(32));
    $hash      = hash('sha256', $validator);
    $expires   = date('Y-m-d H:i:s', time() + REMEMBER_TTL);

    $stmt = $conn->prepare("INSERT INTO remember_tokens_users (user_id, selector, hashed_validator, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $selector, $hash, $expires);
    $stmt->execute();
    $stmt->close();

    return $selector . ':' . $validator;
}
function rotate_remember_token_user(mysqli $conn, int $userId, string $oldSelector): string {
    $stmt = $conn->prepare("DELETE FROM remember_tokens_users WHERE user_id=? AND selector=?");
    $stmt->bind_param("is", $userId, $oldSelector);
    $stmt->execute();
    $stmt->close();
    return create_remember_token_user($conn, $userId);
}
function consume_remember_cookie_user(mysqli $conn): ?array {
    if (empty($_COOKIE['remember_user'])) return null;
    $parts = explode(':', $_COOKIE['remember_user'], 2);
    if (count($parts) !== 2) { clear_cookie('remember_user'); return null; }

    [$selector, $validator] = $parts;

    $stmt = $conn->prepare("SELECT user_id, hashed_validator, expires_at FROM remember_tokens_users WHERE selector=? LIMIT 1");
    $stmt->bind_param("s", $selector);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) { clear_cookie('remember_user'); return null; }
    if (strtotime($row['expires_at']) < time()) {
        $del = $conn->prepare("DELETE FROM remember_tokens_users WHERE selector=?");
        $del->bind_param("s", $selector);
        $del->execute();
        $del->close();
        clear_cookie('remember_user');
        return null;
    }

    $calc = hash('sha256', $validator);
    if (!hash_equals($row['hashed_validator'], $calc)) {
        $uid = (int)$row['user_id'];
        $zap = $conn->prepare("DELETE FROM remember_tokens_users WHERE user_id=?");
        $zap->bind_param("i", $uid);
        $zap->execute();
        $zap->close();
        clear_cookie('remember_user');
        return null;
    }

    $newToken = rotate_remember_token_user($conn, (int)$row['user_id'], $selector);
    set_secure_cookie('remember_user', $newToken, REMEMBER_TTL);
    return ['user_id' => (int)$row['user_id']];
}

/* ---------- CSRF ---------- */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf'];

/* ---------- Auto-login via Remember Me ---------- */
if (empty($fatalMsg) && empty($_SESSION['user_id'])) {
    $payload = consume_remember_cookie_user($conn);
    if ($payload) {
        $stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id=?");
        $stmt->bind_param("i", $payload['user_id']);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $_SESSION['user_id']   = $payload['user_id'];
        $_SESSION['full_name'] = $res['full_name'] ?? 'User';

        header("Location: ../index.php");
        exit;
    }
}

/* ---------- Handle Request ---------- */
$msg          = "";
$fieldErrors  = ['email' => '', 'password' => ''];
$email        = $_POST['email'] ?? "";

if (empty($fatalMsg) && $_SERVER["REQUEST_METHOD"] === "POST") {
    $ip          = client_ip();
    $email_in    = trim($_POST['email'] ?? "");
    $password_in = trim($_POST['password'] ?? "");
    $remember    = isset($_POST['remember']);
    $posted_csrf = $_POST['csrf'] ?? '';

    $errsGlobal = [];

    if (!hash_equals($_SESSION['csrf'], $posted_csrf)) { $errsGlobal[] = "Security check failed. Please refresh and try again."; }
    if ($email_in === "") { $fieldErrors['email'] = "Email is required."; }
    elseif (!filter_var($email_in, FILTER_VALIDATE_EMAIL)) { $fieldErrors['email'] = "Enter a valid email address."; }
    if ($password_in === "") { $fieldErrors['password'] = "Password is required."; }
    elseif (strlen($password_in) < 8) { $fieldErrors['password'] = "Minimum 8 characters."; }

    if (empty($errsGlobal) && empty($fieldErrors['email']) && empty($fieldErrors['password'])) {
        if (too_many_attempts($conn, $email_in, $ip)) {
            $errsGlobal[] = "Too many attempts. Please wait a few minutes.";
        } else {
            $stmt = $conn->prepare("SELECT user_id, full_name, password FROM users WHERE email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $email_in);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password_in, $user['password'])) {
                        $_SESSION['user_id']   = (int)$user['user_id'];
                        $_SESSION['full_name'] = $user['full_name'];

                        clear_attempts($conn, $email_in, $ip);

                        if ($remember) {
                            $token = create_remember_token_user($conn, (int)$user['user_id']);
                            set_secure_cookie('remember_user', $token, REMEMBER_TTL);
                        } else {
                            clear_cookie('remember_user');
                        }

                        header("Location: ../index.php");
                        exit;
                    } else {
                        $fieldErrors['password'] = "Incorrect password.";
                        record_attempt($conn, $email_in, $ip);
                    }
                } else {
                    $fieldErrors['email'] = "No account found with that email.";
                    record_attempt($conn, $email_in, $ip);
                }
                $stmt->close();
            } else {
                $errsGlobal[] = "Failed to prepare login statement.";
            }
        }
    }

    if (!empty($errsGlobal)) {
        $msg = '<div class="alert error"><i class="fa-solid fa-circle-exclamation"></i> ' .
               htmlspecialchars(implode(' ', $errsGlobal)) . '</div>';
    }

    $email = $email_in; // sticky
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>User Login | Mashobane & Co</title>

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

/* Centered container (not full-screen layout) */
body{
  margin:0;
  font-family:"Poppins",system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;
  background: linear-gradient(135deg,#fafaff 0%,#f5f3ff 40%,#f9f9ff 100%);
  color:var(--text-dark);
  min-height:100vh;
  display:grid;
  place-items:center;
  padding:24px;
}

/* Fixed-width wrapper for both panels */
.auth-shell{
  width:min(980px, 96vw);
  display:grid;
  grid-template-columns:1.1fr 1fr;
  border-radius:24px;
  overflow:hidden;
  background:var(--surface);
  border:1px solid var(--border);
  box-shadow:0 20px 60px rgba(31,41,55,.15);
}

/* Left gradient panel inside container */
.panel-left{
  position:relative;padding:48px 40px;color:#fff;overflow:hidden;
  background:
    radial-gradient(1200px 600px at -10% 110%, rgba(255,255,255,.08) 0%, rgba(255,255,255,0) 60%),
    linear-gradient(160deg,var(--primary-900) 0%,var(--primary) 55%,var(--primary-600) 100%);
}
.brand{display:flex;align-items:center;gap:12px;font-weight:600;letter-spacing:.3px;opacity:.95}
.panel-left h1{margin:44px 0 12px;font-size:34px;line-height:1.15;letter-spacing:.2px}
.panel-left p{margin:0 0 24px;max-width:420px;color:rgba(255,255,255,.9)}
.ghost-btn{
  display:inline-flex;align-items:center;gap:10px;padding:12px 18px;border:2px solid rgba(255,255,255,.75);
  color:#fff;background:transparent;border-radius:999px;font-weight:600;text-decoration:none;transition:all .2s ease;
}
.ghost-btn:hover{background:rgba(255,255,255,.1);transform:translateY(-1px)}
.shape{position:absolute;border-radius:50%;opacity:.12;pointer-events:none;filter:blur(2px)}
.shape.s1{width:180px;height:180px;right:-60px;top:20px;background:#fff}
.shape.s2{width:260px;height:260px;left:-120px;bottom:-80px;background:#fff}
.shape.s3{width:100px;height:100px;right:60px;bottom:18%;background:#fff;opacity:.08}

/* Right form card */
.panel-right{display:flex;align-items:center;justify-content:center;background:var(--surface);padding:28px}
.card{
  width:100%;max-width:420px;background:#fff;border:1px solid var(--border);border-radius:20px;
  box-shadow:0 10px 30px rgba(31,41,55,.08);padding:28px 24px;
}
.card h2{margin:6px 0 6px;color:var(--primary-900);font-size:26px}
.subtitle{font-size:13px;color:var(--text-muted);margin-bottom:16px}

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

/* inline field errors */
.has-error .input{ border-color: var(--danger); box-shadow: 0 0 0 4px rgba(185,28,28,.12); }
.error-text{ font-size:12px; color:var(--danger); margin-top:6px; padding-left:2px; }

.toggle-pass{
  position:absolute;right:14px;top:50%;transform:translateY(-50%);
  cursor:pointer;opacity:.6;transition:opacity .2s ease;
}
.toggle-pass:hover{opacity:.9}

.row-flex{display:flex;align-items:center;justify-content:space-between;margin-top:6px}
.checkbox{display:flex;align-items:center;gap:8px;font-size:14px;color:var(--text-muted)}
.checkbox input{width:auto;margin:0}

.action{margin-top:16px}
.btn{
  width:100%;padding:14px 18px;border:none;border-radius:999px;font-weight:700;cursor:pointer;
  background:linear-gradient(135deg,var(--primary) 0%,var(--primary-900) 100%);color:#fff;
  transition:transform .06s ease, filter .2s ease;
}
.btn:hover{filter:brightness(1.05);transform:translateY(-1px)}
.btn:active{transform:translateY(0)}

.links{margin-top:16px;text-align:center;font-size:14px;color:var(--text-muted)}
.links a{color:var(--primary);text-decoration:none;font-weight:600}
.links a:hover{text-decoration:underline}
.small-note{margin-top:8px;color:var(--text-muted);font-size:12px;text-align:center}

/* Responsive stack */
@media (max-width:980px){
  .auth-shell{grid-template-columns:1fr;width:min(680px, 96vw)}
  .panel-left{padding:36px 28px}
  .panel-left h1{margin-top:12px;font-size:28px}
  .panel-right{padding:22px}
}
</style>
</head>
<body>

<main class="auth-shell">
  <section class="panel-left">
    <div class="brand"><span>Mashobane &amp; Co</span></div>

    <h1>New Here?</h1>
    <p>Create an account to start your journey with us.</p>
    <a class="ghost-btn" href="userSignup.php">
      <i class="fa-solid fa-user-plus"></i>
      <span>Create Account</span>
    </a>

    <span class="shape s1"></span>
    <span class="shape s2"></span>
    <span class="shape s3"></span>
  </section>

  <section class="panel-right">
    <div class="card" role="region" aria-labelledby="login-title">
      <h2 id="login-title">Sign In</h2>
      <div class="subtitle">Use your email account to continue</div>

      <?php if(!empty($fatalMsg)): ?>
        <div class="alert error"><i class="fa-solid fa-plug-circle-xmark"></i> <?= htmlspecialchars($fatalMsg) ?></div>
      <?php endif; ?>

      <?= $msg ?>

      <form class="form" method="POST" action="" novalidate id="loginForm">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>"/>

        <div class="group <?= $fieldErrors['email'] ? 'has-error' : '' ?>">
          <span class="icon"><i class="fa-regular fa-envelope"></i></span>
          <input
            class="input"
            type="email"
            name="email"
            id="email"
            placeholder="Email"
            value="<?= htmlspecialchars($email ?? '') ?>"
            required aria-label="Email" autocomplete="email" />
          <?php if($fieldErrors['email']): ?>
            <div class="error-text"><?= htmlspecialchars($fieldErrors['email']) ?></div>
          <?php else: ?>
            <div class="error-text" style="display:none"></div>
          <?php endif; ?>
        </div>

        <div class="group <?= $fieldErrors['password'] ? 'has-error' : '' ?>">
          <span class="icon"><i class="fa-solid fa-lock"></i></span>
          <input
            class="input"
            type="password"
            name="password"
            id="password"
            placeholder="Password"
            minlength="8"
            required aria-label="Password" autocomplete="current-password" />
          <span id="togglePass" class="toggle-pass" title="Show/Hide password">
            <i class="fa-regular fa-eye"></i>
          </span>
          <?php if($fieldErrors['password']): ?>
            <div class="error-text"><?= htmlspecialchars($fieldErrors['password']) ?></div>
          <?php else: ?>
            <div class="error-text" style="display:none"></div>
          <?php endif; ?>
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
        <p>Don’t have an account? <a href="userSignup.php">Sign up</a></p>
        <p>Are you a reseller? <a href="resellerLogin.php">Login</a> or <a href="resellerSignup.php">Register</a></p>
      </div>

      <div class="small-note">Having trouble? Contact support.</div>
    </div>
  </section>
</main>

<script>
/* why: client-side validation mirrors server; better UX */
const form = document.getElementById('loginForm');
const email = document.getElementById('email');
const pwd = document.getElementById('password');
const toggle = document.getElementById('togglePass');

function showError(inputEl, message){
  const group = inputEl.closest('.group');
  const msgEl = group.querySelector('.error-text');
  msgEl.textContent = message || '';
  msgEl.style.display = message ? 'block' : 'none';
  group.classList.toggle('has-error', !!message);
}
function validateEmail(){
  if (!email.value.trim()) return showError(email, 'Email is required.');
  const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value);
  return showError(email, valid ? '' : 'Enter a valid email address.');
}
function validatePassword(){
  if (!pwd.value) return showError(pwd, 'Password is required.');
  return showError(pwd, pwd.value.length >= 8 ? '' : 'Minimum 8 characters.');
}

email.addEventListener('input', validateEmail);
pwd.addEventListener('input', validatePassword);

form?.addEventListener('submit', (e) => {
  validateEmail();
  validatePassword();
  const hasErrors = document.querySelectorAll('.group.has-error').length > 0;
  if (hasErrors) e.preventDefault();
});

/* why: improves UX without exposing password to server */
toggle?.addEventListener('click', () => {
  const isPwd = pwd.type === 'password';
  pwd.type = isPwd ? 'text' : 'password';
  toggle.innerHTML = isPwd ? '<i class="fa-regular fa-eye-slash"></i>' : '<i class="fa-regular fa-eye"></i>';
});
</script>

</body>
</html>
