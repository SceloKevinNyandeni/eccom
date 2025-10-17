<?php
/** file: auth/resellerSignup.php */
session_start();

/* ---------- DB bootstrap ---------- */
$fatalMsg = "";
$conn = @new mysqli('localhost', 'root', '', 'm228_db');
if ($conn->connect_error) {
    $fatalMsg = 'Database connection failed. Please try again later.';
} else {
    $conn->query("
        CREATE TABLE IF NOT EXISTS resellers (
            reseller_id INT AUTO_INCREMENT PRIMARY KEY,
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
$msg          = "";
$fieldErrors  = ['full_name' => '', 'email' => '', 'password' => ''];
$full_name    = $_POST['full_name'] ?? "";
$email        = $_POST['email'] ?? "";
$password     = $_POST['password'] ?? "";

/* ---------- Handle POST ---------- */
if (empty($fatalMsg) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $errsGlobal = [];

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $errsGlobal[] = "Security check failed. Please refresh and try again."; /* why: CSRF protection */
    }

    if (trim($full_name) === "") { $fieldErrors['full_name'] = "Full name is required."; }
    elseif (mb_strlen(trim($full_name)) < 2) { $fieldErrors['full_name'] = "Please enter your full name."; }

    if ($email === "") { $fieldErrors['email'] = "Email is required."; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $fieldErrors['email'] = "Enter a valid email address."; }

    if ($password === "") { $fieldErrors['password'] = "Password is required."; }
    elseif (strlen($password) < 8) { $fieldErrors['password'] = "Minimum 8 characters."; }

    if (empty($errsGlobal) && !$fieldErrors['full_name'] && !$fieldErrors['email'] && !$fieldErrors['password']) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO resellers (full_name, email, password) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sss", $full_name, $email, $hashed);
            if ($stmt->execute()) {
                $_SESSION['reseller_id']    = $conn->insert_id;   /* why: use connection insert_id for mysqli */
                $_SESSION['reseller_email'] = $email;
                $_SESSION['reseller_name']  = $full_name;

                header("Location: upload.php");
                exit;
            } else {
                if ($conn->errno == 1062) {
                    $fieldErrors['email'] = "Email already registered.";
                } else {
                    $errsGlobal[] = "An unexpected error occurred. Please try again.";
                }
            }
            $stmt->close();
        } else {
            $errsGlobal[] = "Failed to prepare signup statement.";
        }
    }

    if (!empty($errsGlobal)) {
        $msg = '<div class="alert error"><i class="fa-solid fa-circle-exclamation"></i> ' .
               htmlspecialchars(implode(' ', $errsGlobal)) . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Reseller Signup | Mashobane & Co</title>

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

/* Left gradient panel */
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

/* Alerts */
.alert{
  display:flex;align-items:flex-start;gap:10px;padding:12px 14px;border-radius:12px;margin:8px 0 14px;font-size:14px;border:1px solid;
}
.alert.error{background:#fff4f4;border-color:#f3c7c7;color:var(--danger)}
.alert.success{background:#f1fcfa;border-color:#bde5df;color:var(--success)}

/* Inputs + inline errors */
.form{margin-top:8px}
.group{position:relative;margin:12px 0}
.group .icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);opacity:.6}
.input{
  width:100%;padding:14px 14px 14px 42px;border:1px solid var(--border);border-radius:12px;background:#fff;outline:none;font-size:14px;
  transition:border-color .15s ease, box-shadow .15s ease;
}
.input::placeholder{color:#9aa0a6}
.input:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(62,15,190,.12)} /* why: accessible focus ring */
.has-error .input{ border-color: var(--danger); box-shadow: 0 0 0 4px rgba(185,28,28,.12); }
.error-text{ font-size:12px; color:var(--danger); margin-top:6px; padding-left:2px; }

.toggle-pass{
  position:absolute;right:14px;top:50%;transform:translateY(-50%);
  cursor:pointer;opacity:.6;transition:opacity .2s ease;
}
.toggle-pass:hover{opacity:.9}

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

    <h1>Become a Reseller</h1>
    <p>Already registered? Sign in to upload and manage your assets.</p>
    <a class="ghost-btn" href="resellerLogin.php">
      <i class="fa-solid fa-right-to-bracket"></i>
      <span>Reseller Login</span>
    </a>

    <span class="shape s1"></span>
    <span class="shape s2"></span>
    <span class="shape s3"></span>
  </section>

  <section class="panel-right">
    <div class="card" role="region" aria-labelledby="signup-title">
      <h2 id="signup-title">Create Reseller Account</h2>
      <div class="subtitle">Use your email to register</div>

      <?php if(!empty($fatalMsg)): ?>
        <div class="alert error"><i class="fa-solid fa-plug-circle-xmark"></i> <?= htmlspecialchars($fatalMsg) ?></div>
      <?php endif; ?>

      <?= $msg ?>

      <form class="form" method="POST" action="" novalidate id="signupForm">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>"/>

        <div class="group <?= $fieldErrors['full_name'] ? 'has-error' : '' ?>">
          <span class="icon"><i class="fa-solid fa-user"></i></span>
          <input
            class="input"
            type="text"
            name="full_name"
            id="full_name"
            placeholder="Full Name"
            value="<?= htmlspecialchars($full_name ?? '') ?>"
            required aria-label="Full Name" autocomplete="name" />
          <?php if($fieldErrors['full_name']): ?>
            <div class="error-text"><?= htmlspecialchars($fieldErrors['full_name']) ?></div>
          <?php else: ?><div class="error-text" style="display:none"></div><?php endif; ?>
        </div>

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
          <?php else: ?><div class="error-text" style="display:none"></div><?php endif; ?>
        </div>

        <div class="group <?= $fieldErrors['password'] ? 'has-error' : '' ?>">
          <span class="icon"><i class="fa-solid fa-lock"></i></span>
          <input
            class="input"
            type="password"
            name="password"
            id="password"
            placeholder="Password (min 8 chars)"
            minlength="8"
            required aria-label="Password" autocomplete="new-password" />
          <span id="togglePass" class="toggle-pass" title="Show/Hide password">
            <i class="fa-regular fa-eye"></i>
          </span>
          <?php if($fieldErrors['password']): ?>
            <div class="error-text"><?= htmlspecialchars($fieldErrors['password']) ?></div>
          <?php else: ?><div class="error-text" style="display:none"></div><?php endif; ?>
        </div>

        <div class="action">
          <button class="btn" type="submit">Sign Up</button>
        </div>
      </form>

      <div class="links">
        <p>Already a reseller? <a href="reseller-login.php">Login</a></p>
        <p>User instead? <a href="user-signup.php">User signup</a></p>
      </div>

      <div class="small-note">By creating an account, you agree to our terms and privacy policy.</div>
    </div>
  </section>
</main>

<script>
/* client-side validation mirrors server; better UX */
const form = document.getElementById('signupForm');
const nameEl = document.getElementById('full_name');
const emailEl = document.getElementById('email');
const pwdEl = document.getElementById('password');
const toggle = document.getElementById('togglePass');

function showError(inputEl, message){
  const group = inputEl.closest('.group');
  const msgEl = group.querySelector('.error-text');
  msgEl.textContent = message || '';
  msgEl.style.display = message ? 'block' : 'none';
  group.classList.toggle('has-error', !!message);
}
function validateName(){
  const v = nameEl.value.trim();
  return showError(nameEl, v.length >= 2 ? '' : (v ? 'Please enter your full name.' : 'Full name is required.'));
}
function validateEmail(){
  const v = emailEl.value.trim();
  if (!v) return showError(emailEl, 'Email is required.');
  const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
  return showError(emailEl, ok ? '' : 'Enter a valid email address.');
}
function validatePassword(){
  const v = pwdEl.value;
  if (!v) return showError(pwdEl, 'Password is required.');
  return showError(pwdEl, v.length >= 8 ? '' : 'Minimum 8 characters.');
}

[nameEl, emailEl, pwdEl].forEach(el => el.addEventListener('input', () => {
  if (el === nameEl) validateName();
  if (el === emailEl) validateEmail();
  if (el === pwdEl) validatePassword();
}));

form?.addEventListener('submit', (e) => {
  validateName(); validateEmail(); validatePassword();
  const hasErrors = document.querySelectorAll('.group.has-error').length > 0;
  if (hasErrors) e.preventDefault();
});

/* show/hide password */
toggle?.addEventListener('click', () => {
  const isPwd = pwdEl.type === 'password';
  pwdEl.type = isPwd ? 'text' : 'password';
  toggle.innerHTML = isPwd ? '<i class="fa-regular fa-eye-slash"></i>' : '<i class="fa-regular fa-eye"></i>';
});
</script>

</body>
</html>
