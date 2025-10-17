<?php
// file: reseller.php
// Styled reseller page: 4-step delivery status with stepper & accessible controls (SVG icons).
// Requires: db.php defines $pdo (PDO). Table: delivery_status(id INT PK, stage INT).

declare(strict_types=1);
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stage'])) {
    $stage = (int) $_POST['stage'];
    if ($stage >= 1 && $stage <= 4) {
        $stmt = $pdo->prepare("UPDATE delivery_status SET stage = ? WHERE id = 1");
        $stmt->execute([$stage]);
    }
    header('Location: reseller2.php'); // PRG pattern
    exit;
}

$stmt = $pdo->query("SELECT stage FROM delivery_status WHERE id = 1");
$currentStage = (int) ($stmt->fetchColumn() ?: 1);

$stages = [
    1 => ['label' => 'Order Processed',  'icon' => 'document'],
    2 => ['label' => 'Order Shipped',    'icon' => 'box'],
    3 => ['label' => 'Order On The Way', 'icon' => 'truck'],
    4 => ['label' => 'Order Arrived',    'icon' => 'check'],
];

function h(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

/**
 * Minimal inline SVG icon set (stroke = currentColor).
 * Why: keeps icons crisp without external deps.
 */
function svg_icon(string $name, int $size = 18): string {
    $common = sprintf(
        'class="icon" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"',
        $size,
        $size
    );
    switch ($name) {
        case 'document':
            return '<svg '.$common.'><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>';
        case 'box':
            return '<svg '.$common.'><path d="M21 16V8a2 2 0 0 0-1-1.73L13 2.27a2 2 0 0 0-2 0L4 6.27A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96L12 12l8.73-5.04"/><path d="M12 22V12"/></svg>';
        case 'truck':
            return '<svg '.$common.'><path d="M10 17h4V5H3v12h3"/><path d="M14 7h4l3 5v5h-7"/><circle cx="5.5" cy="17.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>';
        case 'check':
            return '<svg '.$common.'><path d="M20 6L9 17l-5-5"/></svg>';
        default:
            return '<svg '.$common.'><circle cx="12" cy="12" r="9"/></svg>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Reseller – Delivery Status</title>
<style>
  :root{
    --bg:#f7f8fb;
    --panel:#ffffff;
    --text:#0f1222;
    --muted:#5b6476;
    --brand:#0e9aa7;
    --brand-600:#635bff;
    --ok:#16a34a;
    --warn:#f59e0b;
    --ring:0 0 0 3px rgba(79,70,229,0.35);
    --radius:14px;
    --shadow:0 10px 30px rgba(10,20,30,0.08);
    --line:#e7e9f2;
    --done:#22c55e;
  }
  @media (prefers-color-scheme: dark){
    :root{
      --bg:#0b0c0f; --panel:#111318; --text:#e8eaf0; --muted:#a6adbb; --line:#2a2f3a; --shadow:0 10px 30px rgba(0,0,0,0.35);
    }
  }
  *{box-sizing:border-box}
  html,body{height:100%}
  body{
    margin:0; font-family:'Poppins', Arial, sans-serif;
    background: rgba(211, 208, 208, 1);
    color:var(--text);
    line-height:1.5;
    padding:24px;
  }
  .wrap{max-width:980px; margin:0 auto;}
  .card{
    background: #413e3eff;
    border:1px solid rgba(0,0,0,0.04);
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    padding:28px;
  }
  header h1{
    font-size:clamp(22px, 2.4vw, 30px); margin:0 0 6px 0;
    letter-spacing:.2px; color:var(--brand);
  }
  header p{margin:0; color:var(--brand);}
  .current{
    display:flex; align-items:center; gap:10px; margin-top:18px; color:var(--brand);
  }
  .badge{
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 12px; border-radius:999px;
    background:rgba(79,70,229,0.08);
    border:1px solid rgba(79,70,229,0.2);
    color:var(--brand); font-weight:600; font-size:14px;
  }
  .badge.ok{background:rgba(34,197,94,0.12); border-color:rgba(34,197,94,0.35);}
  .icon{width:18px; height:18px; display:inline-block; vertical-align:-3px;}
  .stepper{
    list-style:none; padding:0; margin:28px 0 6px 0;
    display:flex; gap:18px; align-items:flex-start;
  }
  .step{position:relative; flex:1; text-align:center;}
  .step .dot{
    width:34px; height:34px; border-radius:50%;
    margin:0 auto 10px; display:grid; place-items:center;
    border:2px solid var(--line); background:#0d1016; color:var(--muted); font-weight:700;
  }
  @media (prefers-color-scheme: light){ .step .dot{ background:#fff; } }
  .step.done .dot{ border-color:var(--done); background:rgba(34,197,94,0.18); color:#fff; }
  .step.active .dot{
    border-color:var(--brand); background:rgba(79,70,229,0.18); color:#fff;
    box-shadow:0 0 0 4px rgba(99,102,241,0.15);
  }
  .step .title{ font-size:13px; color:#000; }
  @media (prefers-color-scheme: dark){ .step .title{ color:var(--muted); } }
  .stepper .bar{
    position:absolute; top:16px; left:calc(-50% + 17px); right:calc(50% + 17px);
    height:3px; background:var(--line); z-index:-1;
  }
  .step.done .bar, .step.active .bar{ background:linear-gradient(90deg, var(--done), var(--brand-600)); }
  .actions{
    display:grid; grid-template-columns: repeat(2, minmax(0,1fr));
    gap:12px; margin-top:22px;
  }
  @media (min-width:720px){ .actions{ grid-template-columns: repeat(4, minmax(0,1fr)); } }
  button.stage-btn{
    appearance:none; border:1px solid rgba(0,0,0,0.06);
    background:var(--brand);
    padding:12px 14px; border-radius:12px; font-size:15px; font-weight:600; color:#fff;
    display:flex; align-items:center; justify-content:center; gap:8px; width:100%;
    cursor:pointer; transition: transform .06s ease, border-color .15s ease, box-shadow .15s ease;
  }
  button.stage-btn:focus-visible{ outline:none; box-shadow:var(--ring); }
  button.stage-btn:hover{ transform:translateY(-1px); border-color:rgba(99,102,241,0.55); }
  .pill{
    font-size:12px; padding:4px 8px; border-radius:999px; color:var(--muted);
    background:#fff; border:1px solid rgba(0,0,0,0.06);
  }
  button.stage-btn[disabled]{ cursor:not-allowed; opacity:.75; }
  footer{ margin-top:18px; color:var(--muted); font-size:12px; }
</style>
</head>
<body>
  <div class="wrap">
    <div class="card" role="region" aria-labelledby="pageTitle">
      <header>
        <h1 id="pageTitle">Reseller Page</h1>
        <p>Click a button to set the delivery stage.</p>
        <div class="current" aria-live="polite">
          <span>Current Stage:</span>
          <?php
            $label = 'Stage ' . $currentStage . ': ' . $stages[$currentStage]['label'];
            $badgeClass = $currentStage === 4 ? 'badge ok' : 'badge';
            echo '<span class="'. $badgeClass .'">'. svg_icon($stages[$currentStage]['icon'], 18) . h($label) .'</span>';
          ?>
        </div>
      </header>

      <ol class="stepper" role="list" aria-label="Delivery progress">
        <?php for ($i=1; $i<=4; $i++):
          $state = ($i < $currentStage) ? 'done' : (($i === $currentStage) ? 'active' : '');
          $title = 'Stage ' . $i . ': ' . $stages[$i]['label'];
        ?>
          <li class="step <?= $state ?>">
            <?php if ($i !== 1): ?><span class="bar" aria-hidden="true"></span><?php endif; ?>
            <div class="dot" aria-hidden="true"><?= $i ?></div>
            <div class="title"><?= h($title) ?></div>
          </li>
        <?php endfor; ?>
      </ol>

      <form method="post" class="actions" aria-label="Update stage">
        <?php for ($i=1; $i<=4; $i++):
          $title = 'Stage ' . $i . ': ' . $stages[$i]['label'];
          $isActive = ($i === $currentStage);
        ?>
          <button
            type="submit"
            name="stage"
            value="<?= $i ?>"
            class="stage-btn"
            data-variant="<?= $i ?>"
            <?php if ($isActive): ?>disabled aria-disabled="true"<?php endif; ?>
            aria-label="Set <?= h($title) ?>"
            title="<?= h($title) ?>"
          >
            <?= svg_icon($stages[$i]['icon'], 18) ?>
            <span><?= h($title) ?></span>
            <?php if ($isActive): ?>
              <span class="pill" aria-hidden="true">current</span>
            <?php else: ?>
              <span class="pill" aria-hidden="true">set</span>
            <?php endif; ?>
          </button>
        <?php endfor; ?>
      </form>

      <footer>Tip: The active stage button is disabled to prevent redundant updates.</footer>
    </div>
  </div>
</body>
</html>
