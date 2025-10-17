<?php
// file: tracking.php
include 'db.php';

include 'db.php';

// Get order ID from URL (e.g., tracking.php?order_id=5)
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    die("❌ Invalid tracking number.");
}

// Fetch order status from the orders table
$stmt = $pdo->prepare("SELECT status FROM orders WHERE order_id = ?");
$stmt->execute([$order_id]);
$status = $stmt->fetchColumn();

// Map order statuses to numeric stages
$statusMap = [
    'Pending'   => 1,
    'Shipped'   => 2,
    'Order On The Way'  => 3,
    'Delivered' => 4,
    'Cancelled' => 0,
];

$currentStage = $statusMap[$status] ?? 1;
$currentStage = max(1, min(4, $currentStage));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Delivery Tracking</title>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
<meta name="theme-color" content="#0e9aa7" />
<style>
  :root{
    --bg:#f5f7fb;
    --surface:#fff;
    --text:#0f172a;
    --muted:#667085;
    --brand:#0e9aa7;
    --brand-2:#0b7e89;
    --accent:#2563eb;
    --success:#10b981;
    --rail:#e5e7eb;
    --radius:18px;
    --shadow:0 8px 30px rgba(2,6,23,.08);
    --gap:28px;
    --toggle-bg:#ffffff;
    --toggle-fg:#0f172a;
    --toggle-ring:rgba(14,154,167,.45);
  }
  .theme-dark{
    --bg:#0b0f1a;
    --surface:#0f1526;
    --text:#f8fafc;
    --muted:#9aa4b2;
    --rail:#273046;
    --shadow:0 10px 36px rgba(0,0,0,.55);
    --toggle-bg:#141b2d;
    --toggle-fg:#e5e7eb;
    --toggle-ring:rgba(59,130,246,.45);
  }
  *{box-sizing:border-box}
  html,body{height:100%}
  body{
    margin:0;
    font-family: 'Poppins', Arial, sans-serif;
    background:
      radial-gradient(1100px 700px at 10% -10%, rgba(14,154,167,.08), transparent 60%),
      var(--bg);
    color:var(--text);
    -webkit-font-smoothing:antialiased;
  }
  .page-wrapper{min-height:100vh; display:flex; flex-direction:column}
  .header{
    position:relative; background:linear-gradient(160deg, var(--brand), var(--brand-2));
    color:#fff; padding:36px 20px 56px; text-align:left; overflow:hidden;
  }
  .header::after{
    content:''; position:absolute; inset:0;
    background:
      radial-gradient(12px 12px at 24px 24px, rgba(255,255,255,.08) 25%, transparent 26%) repeat,
      radial-gradient(8px 8px at 12px 12px, rgba(255,255,255,.05) 25%, transparent 26%) repeat;
    background-size:48px 48px, 36px 36px; opacity:.45; pointer-events:none;
  }
  .header h1{margin:0; font-size:clamp(22px, 3.2vw, 30px); letter-spacing:.3px}

  /* Theme toggle */
  .theme-toggle{
    position:absolute; top:14px; right:14px; z-index:2;
    display:inline-flex; align-items:center; justify-content:center;
    width:42px; height:42px; border-radius:999px; border:0;
    background:var(--toggle-bg); color:var(--toggle-fg);
    box-shadow:0 2px 10px rgba(2,6,23,.18);
    cursor:pointer; transition:transform .06s ease, box-shadow .2s ease, background .2s ease;
  }
  .theme-toggle:hover{ transform:translateY(-1px) }
  .theme-toggle:active{ transform:translateY(0) scale(.98) }
  .theme-toggle:focus-visible{
    outline:3px solid transparent;
    box-shadow:0 0 0 4px var(--toggle-ring), 0 2px 10px rgba(2,6,23,.18);
  }
  .theme-toggle svg{ width:18px; height:18px; }

  .instructions{
     margin-top:34px;
     padding:0 16px; 
     width:30%; 
     height:10%;
     min-width:280px;
     max-width:980px;}
  .instructions p{
    margin:0 auto; max-width:980px; background:var(--surface); color:var(--muted);
    border-radius:999px; box-shadow:var(--shadow); padding:14px 18px; text-align:left;
  }

  .content{
    display:flex; flex-wrap:wrap; gap:var(--gap);
    padding:24px clamp(14px, 4vw, 48px) 48px; justify-content:space-between;
  }

  .estimated-delivery, .progress-container{
    flex:1; min-width:280px; background:var(--surface);
    border-radius:var(--radius); box-shadow:var(--shadow); padding:22px;
  }
  .estimated-delivery{
    min-height:300px; display:flex; flex-direction:column; justify-content:space-between;
  }

  .card-top{ display:flex; align-items:center; gap:10px; margin-bottom:4px }
  .cube{
    width:36px; height:36px; border-radius:12px; display:grid; place-items:center;
    background:rgba(14,154,167,.12); color:var(--brand);
  }
  .cube svg{ width:20px; height:20px; }
  .title{ font-weight:700; font-size:16px; letter-spacing:.2px; color:#0f172a }
  .theme-dark .title{ color:var(--text) }
  .sub{ margin-top:6px; color:var(--muted); font-size:14px }

  .h-progress{ position:relative; margin:18px 4px 14px }
  .h-rail{ position:relative; height:4px; border-radius:4px; background:var(--rail) }
  .h-fill{
    position:absolute; inset:0 auto 0 0; width:0%; background:linear-gradient(90deg, var(--brand), var(--accent));
    border-radius:4px; height:4px; transition:width .45s ease;
  }
  .h-dots{ position:relative; margin-top:-10px; display:flex; justify-content:space-between }
  .h-dot{
    width:14px; height:14px; border-radius:50%; background:#d1d5db; border:2px solid #fff;
    box-shadow:0 0 0 2px #fff, 0 2px 6px rgba(2,6,23,.15);
  }
  .theme-dark .h-dot{ border-color:#141b2d; box-shadow:0 0 0 2px #141b2d, 0 2px 6px rgba(0,0,0,.4) }
  .h-dot.completed{ background:var(--brand) }
  .h-dot.active{ background:var(--success); box-shadow:0 0 0 2px currentColor, 0 0 0 6px rgba(16,185,129,.2) }

  .route{ display:flex; justify-content:space-between; gap:12px; border-top:1px dashed var(--rail); padding-top:12px }
  .route .col{ flex:1 }
  .label{ font-size:12px; color:var(--muted) }
  .value{ font-weight:700; margin-top:3px }

  .track-button{
    margin-top:10px; padding:12px 16px; width:100%; max-width:240px; align-self:flex-start;
    border:0; border-radius:999px; color:#fff; font-weight:700; cursor:pointer;
    background:linear-gradient(135deg, var(--brand), var(--brand-2));
    transition:transform .06s ease, opacity .2s ease;
  }
  .track-button:hover{ transform:translateY(-1px) }
  .track-button:active{ transform:translateY(0) scale(.99) }
  .track-button:focus-visible{ outline:3px solid color-mix(in oklab, var(--brand), #fff 60%) }

  .progress-container{ display:flex; justify-content:center; align-items:flex-start }
  .timeline{
    --fill-h:0px; display:flex; flex-direction:column; position:relative;
    margin-left:40px; min-width:300px;
  }
  .timeline::before{
    content:''; position:absolute; left:15px; top:0; bottom:0; width:4px; background:var(--rail); border-radius:2px;
  }
  .timeline::after{
    content:''; position:absolute; left:15px; top:0; width:4px; height:var(--fill-h);
    background:linear-gradient(180deg, var(--brand), var(--accent)); border-radius:2px; transition:height .45s ease;
  }
  .timeline-step{ position:relative; display:flex; align-items:flex-start; margin:0 0 34px 0 }
  .timeline-step:last-child{ margin-bottom:0 }
  .timeline-step .icon{
    width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center;
    background:#cbd5e1; color:#0b1220; position:absolute; left:0; z-index: 1;
  }
  .timeline-step .icon svg{ width:18px; height:18px; z-index:2; }
  .timeline-step.completed .icon{ background:var(--brand); color:#fff }
  .timeline-step.active .icon{
    background:var(--success); color:#05311f; font-weight:700;
    box-shadow:0 0 0 6px rgba(16,185,129,.18); animation:bounce 1.1s infinite;
  }
  .timeline-step .content{ margin-left:46px; margin-top:4px; font-weight:700 }
  @keyframes bounce{ 0%,100%{transform:translateY(0)} 50%{transform:translateY(-5px)} }
  @media (prefers-reduced-motion: reduce){
    .timeline::after, .h-fill, .timeline-step.active .icon{ transition:none; animation:none }
  }
  @media (max-width:760px){
    .content{ flex-direction:column }
    .timeline{ margin-left:26px; min-width:unset }
  }
</style>
</head>
<body>
  <div class="page-wrapper" id="appRoot">
    <div class="header">
      <h1>Let’s Track Your Package</h1>

      <!-- Toggle uses inline SVG (no assets) -->
      <button class="theme-toggle" id="themeToggle" type="button" aria-pressed="false" aria-label="Toggle dark mode">
        <span class="icon" id="themeIcon">
          <!-- default swapped by JS -->
        </span>
      </button>
    </div>

    <div class="instructions">
      <p>Enter your tracking number to view details.</p>
    </div>

    <div class="content">
      <!-- LEFT -->
      <div class="estimated-delivery" aria-live="polite">
        <div>
          <div class="card-top">
            <div class="cube" aria-hidden="true">
              <!-- Package SVG -->
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true">
                <path d="M21 16V8a2 2 0 0 0-1-1.73L12 2 4 6.27A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73L12 22l8-4.27A2 2 0 0 0 21 16z"/>
                <path d="M3.27 6.96 12 12l8.73-5.04"/>
                <path d="M12 12v10"/>
                <path d="M7.5 4.21 16.5 9.4"/>
              </svg>
            </div>
            <div>
              <div class="title">Current Shipment</div>
              <div class="sub" id="statusLine">Status: —</div>
            </div>
          </div>

          <div class="h-progress" aria-label="Shipment progress" role="group">
            <div class="h-rail"><div class="h-fill" style="width:0%"></div></div>
            <div class="h-dots" aria-hidden="true">
              <div class="h-dot"></div><div class="h-dot"></div><div class="h-dot"></div><div class="h-dot"></div>
            </div>
          </div>

          <div class="route">
            <div class="col">
              <div class="label">From</div>
              <div class="value">Processing Center</div>
            </div>
            <div class="col" style="text-align:right">
              <div class="label">To</div>
              <div class="value">Your Address</div>
            </div>
          </div>
        </div>

        
      </div>

      <!-- RIGHT -->
      <div class="progress-container">
        <div class="timeline" role="list" aria-label="Delivery progress">
          <?php
            // Inline SVGs as strings; use double quotes inside
            $icons = [
              '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>', // file
              '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73L12 2 4 6.27A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73L12 22l8-4.27A2 2 0 0 0 21 16z"/><path d="M3.27 6.96 12 12l8.73-5.04"/><path d="M12 12v10"/><path d="M7.5 4.21 16.5 9.4"/></svg>', // package
              '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="3" width="15" height="13" rx="2" ry="2"/><path d="M16 8h4l3 3v5h-7z"/><circle cx="6" cy="19" r="2"/><circle cx="18" cy="19" r="2"/></svg>', // truck
              '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9 12l2 2 4-4"/></svg>'  // check-circle
            ];
            $stages = ['Order Processed','Order Shipped','Order En Route','Order Arrived'];
            foreach ($stages as $i => $stage) {
              $stepNum = $i + 1;
              $class = ($currentStage > $stepNum) ? 'completed' : (($currentStage === $stepNum) ? 'active' : '');
              $ariaCurrent = ($currentStage === $stepNum) ? ' aria-current="step"' : '';
              $iconSvg = $icons[$i];
              echo "<div class='timeline-step $class'$ariaCurrent role='listitem'>
                      <div class='icon' aria-hidden='true'>$iconSvg</div>
                      <div class='content'>".$stage."</div>
                    </div>";
            }
          ?>
        </div>
      </div>
    </div>
  </div>

<script>
  const STAGES = ['Pending','Shipped','Order On The Way','Delivered', 'Cancelled'];
  const THEME_KEY = 'theme';

  // Inline SVG strings for the toggle
  const SUN_SVG = `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <circle cx="12" cy="12" r="4"></circle>
      <path d="M12 2v2M12 20v2M4 12H2M22 12h-2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>
    </svg>`;
  const MOON_SVG = `
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
    </svg>`;

  function clamp(n, min, max){ return Math.max(min, Math.min(max, n)); }

  function applyTheme(theme){
    const rootEl = document.documentElement;
    const isDark = theme === 'dark';
    rootEl.classList.toggle('theme-dark', isDark);
    const btn = document.getElementById('themeToggle');
    const icon = document.getElementById('themeIcon');
    if (btn && icon){
      btn.setAttribute('aria-pressed', String(isDark));
      icon.innerHTML = isDark ? SUN_SVG : MOON_SVG; // why: clear affordance
      btn.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
    }
  }

  function initTheme(){
    const saved = localStorage.getItem(THEME_KEY);
    if (saved === 'light' || saved === 'dark'){ applyTheme(saved); return; }
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    applyTheme(prefersDark ? 'dark' : 'light');
  }

  function toggleTheme(){
    const isDark = document.documentElement.classList.contains('theme-dark');
    const next = isDark ? 'light' : 'dark';
    applyTheme(next);
    try{ localStorage.setItem(THEME_KEY, next); }catch{}
  }

  function updateUI(stage){
    stage = clamp(Number(stage)||1, 1, 4);

    // Horizontal progress
    const fill = document.querySelector('.h-fill');
    const dots = document.querySelectorAll('.h-dot');
    const statusLine = document.getElementById('statusLine');
    const pct = ((stage - 1) / (dots.length - 1)) * 100;
    if (fill) fill.style.width = `${pct}%`;
    dots.forEach((d,i)=>{
      d.classList.toggle('completed', i + 1 <= stage);
      d.classList.toggle('active', i + 1 === stage);
    });
    if (statusLine) statusLine.textContent = `Status: ${STAGES[stage - 1] || '—'}`;

    // Vertical timeline
    const steps = document.querySelectorAll('.timeline-step');
    const timeline = document.querySelector('.timeline');
    steps.forEach((step, i) => {
      step.classList.toggle('completed', i + 1 < stage);
      step.classList.toggle('active', i + 1 === stage);
      if (i + 1 === stage) step.setAttribute('aria-current','step'); else step.removeAttribute('aria-current');
    });
    const first = steps[0];
    if (first && timeline){
      const gap = 34;
      const stepH = first.offsetHeight + gap;
      const fillH = Math.max(0, (stage - 1) * stepH + 10);
      timeline.style.setProperty('--fill-h', `${fillH}px`);
    }
  }

  async function pollStage(){
    try{
      const orderId = new URLSearchParams(window.location.search).get('order_id');
if (!orderId) return;
const res = await fetch(`get_stage.php?order_id=${orderId}`, {cache:'no-store'});
      if (!res.ok) return;
      const data = await res.json();
      updateUI(data?.stage ?? 1);
    }catch{}
  }

  document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    document.getElementById('themeToggle')?.addEventListener('click', toggleTheme);
    updateUI(<?php echo $currentStage; ?>);
    pollStage();
    setInterval(pollStage, 2000);
  });
</script>
</body>
</html>
