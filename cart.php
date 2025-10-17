<?php
session_start();

// Init cart if not present
if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'add_to_cart':
            $id = (int)($_POST['product_id'] ?? 0);
            $name = $_POST['product_name'] ?? '';
            $price = (float)($_POST['product_price'] ?? 0);
            $image = $_POST['product_image'] ?? '';
            if (isset($_SESSION['cart'][$id])) {
                $_SESSION['cart'][$id]['quantity'] += 1;
                $msg = "Another $name added to your cart.";
            } else {
                $_SESSION['cart'][$id] = ['id'=>$id,'name'=>$name,'price'=>$price,'image'=>$image,'quantity'=>1];
                $msg = "$name added to cart successfully!";
            }
            break;
        case 'update_qty':
            $id = (int)($_POST['product_id'] ?? 0);
            $qty = max(1, (int)($_POST['quantity'] ?? 1));
            if (isset($_SESSION['cart'][$id])) {
                $_SESSION['cart'][$id]['quantity'] = $qty;
                $msg = "Quantity updated successfully!";
            } else $msg = "Item not found in cart.";
            break;
        case 'remove_item':
            $id = (int)($_POST['product_id'] ?? 0);
            if (isset($_SESSION['cart'][$id])) { unset($_SESSION['cart'][$id]); $msg="Item removed from cart."; }
            else $msg="Item not found.";
            break;
        case 'clear_cart':
            $_SESSION['cart'] = []; $msg="Cart cleared successfully!"; break;
        default: $msg="Invalid action.";
    }

    $totalItems = array_sum(array_column($_SESSION['cart'], 'quantity'));
    $totalPrice = 0; foreach ($_SESSION['cart'] as $it) { $totalPrice += $it['price'] * $it['quantity']; }

    echo json_encode([
        'success'=>true,
        'message'=>$msg,
        'cartCount'=>$totalItems,
        'totalPrice'=>number_format($totalPrice,2),
        'cart'=>$_SESSION['cart']
    ]);
    exit;
}

// Totals for initial render
$totalItems = array_sum(array_column($_SESSION['cart'], 'quantity'));
$totalPrice = 0; foreach ($_SESSION['cart'] as $it) { $totalPrice += $it['price'] * $it['quantity']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Your Cart | Mashobane and Co</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --ink:#0f172a; 
    --muted:#6b7280; 
    --line:#e5e7eb; 
    --brand:hsl(216,77%,48%); 
    --brand-600:hsl(216,77%,40%);
    --bg:#fafafa; 
    --card:#ffffff; 
    --ok:#16a34a;
  }
  *{
    box-sizing:border-box;
  }
  html,body{
    margin:0;
  }
  body{
    font-family:Inter, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; background:var(--bg); 
    color:var(--ink);
  }

  /* Top black bar */
  .topbar{
    background:#000; 
    color:#fff; 
    padding:10px 5%;
    display:flex; 
    align-items:center; 
    justify-content:space-between; 
    gap:10px;
  }
  .topbar a{
    color:#fff; 
    text-decoration:none; 
    font-weight:600; 
    opacity:.9;
  }
  .topbar a:hover{
    opacity:1;
  }

  /* Main header under bar */
  .pagehead{
    padding:24px 5% 8px;
    display:flex; 
    align-items:end; 
    justify-content:space-between; 
    gap:16px;
    border-bottom:1px solid var(--line);
    background:#fff;
  }
  .pagehead h1{
    margin:0; 
    font-weight:800; 
    letter-spacing:.2px;
  }
  .crumb a{
    color:var(--brand); 
    text-decoration:none; 
    font-weight:600;
  }
  .crumb a:hover{
    color:var(--brand-600);
  }

  /* Layout grid */
  .wrap{
    max-width:1200px; 
    margin:18px auto; 
    padding:0 5%;
    display:grid; 
    grid-template-columns: minmax(0,1fr) 360px;
    gap:24px;
  }
  @media (max-width: 980px){ 
    .wrap{ grid-template-columns:1fr; }
  }

  /* Left: cart items table styled like cards */
  .cart-panel{ 
    background:#fff; 
    border:1px solid var(--line); 
    border-radius:12px;
   }
  .cart-head{
     padding:16px 18px; 
     border-bottom:1px solid var(--line); 
     display:flex; 
     align-items:center; 
     justify-content:space-between 
    }
  .cart-head .count{ 
    font-weight:700 
  }

  table.cart-table{
     width:100%; 
     border-collapse:separate; 
     border-spacing:0 12px; 
     padding:16px 
    }
  table.cart-table thead{
     display:none 
    } /* hide labels to mimic cards */
  table.cart-table tbody tr{
    background:#fff; 
    border:1px solid var(--line); 
    border-radius:12px; 
    box-shadow:0 2px 8px rgba(0,0,0,.06);
  }
  table.cart-table tbody tr>td{ 
    padding:14px; 
    vertical-align:middle 
  }
  /* Card layout inside row */
  .rowgrid{ 
    display:grid; 
    grid-template-columns: 84px 1.2fr .6fr .6fr .6fr .4fr; 
    gap:12px;
    align-items:center
   }
  @media (max-width: 720px){
    .rowgrid{
       grid-template-columns: 72px 1fr auto; 
       grid-auto-rows:auto; 
       row-gap:8px;
      }
    .cell-price, .cell-subtotal, .cell-action {
       justify-self:end; 
      }
  }
  .prod-img img{ 
    width:84px; 
    height:84px; 
    object-fit:cover; 
    border-radius:8px;
  }
  .prod-name{ 
    font-weight:600;
   }
  .sku{ 
    color:var(--muted);
     font-size:12px; 
    }
  .cell-price, .cell-subtotal{ 
    font-weight:700; 
  }
  .qty-input{
    width:72px; 
    padding:8px; 
    border:1px solid var(--line); 
    border-radius:8px; 
    text-align:center; 
    font-weight:600;
    background:#fff; 
    color:var(--ink);
  }
  .remove-btn{
    background:transparent; 
    color:#dc2626; 
    border:1px solid #fecaca; 
    padding:8px 12px; 
    border-radius:8px; 
    cursor:pointer; 
    font-weight:700;
  }
  .remove-btn:hover{ 
    background:#fee2e2; 
  }

  /* Right: summary box (no promo code UI) */
  .summary{
    position:sticky; top:18px;
    background:#fff; border:1px solid var(--line); 
    border-radius:12px; 
    padding:18px; 
    height:max-content;
    box-shadow:0 2px 8px rgba(0,0,0,.06);
  }
  .summary h3{ 
    margin:0 0 10px;
  }
  .note{ font-size:12px; 
    color:var(--muted); 
    margin-bottom:12px;
  }
  .totals{ border-top:1px solid var(--line); 
    padding-top:12px; 
    margin-top:8px;
   }
  .row{ display:flex; 
    align-items:center; 
    justify-content:space-between; 
    margin:6px 0;
   }
  .row.save{ 
    color:var(--ok);
   }
  .row.err{ 
    color:#ef4444;
   }
  .grand{ 
    font-weight:800; 
    font-size:18px; 
    padding-top:6px;
   }
  .checkout{
    margin-top:14px; 
    width:100%; 
    padding:12px; 
    border-radius:8px; 
    background:#1b0066ff; 
    color:white; 
    font-weight:800; 
    border:0; 
    cursor:pointer;
  }
  .checkout:hover{ 
    filter:brightness(.96);
   }

  /* Floating success/error banner */
  .message-banner {
    position: fixed; 
    top: -60px; 
    left: 50%;
    transform: translateX(-50%); 
    padding: 12px 25px; 
    border-radius: 8px;
    font-weight: 600; 
    z-index: 9999; 
    opacity: 0;
    transition: top .6s ease, opacity .6s ease; 
    color:#fff;
  }
  .message-banner.show { 
    top: 20px; 
    opacity: 1;
   }
  .message-success { 
    background: #16a34a;
  }
  .message-error {
     background: #dc2626;
     }

  /* Upsell strip placeholder */
  .upsell{ 
    max-width:1200px; 
    margin:28px auto; 
    padding:0 5%; 
    color:var(--muted); 
    font-size:13px; 
    }
</style>
</head>
<body>

<!-- Slim top bar -->
<div class="topbar">
  <div><a href="#"><i class="fa-regular fa-user"></i> Login</a></div>
  <div style="font-weight:800; letter-spacing:.3px">MASHOBANE & CO</div>
  <div><i class="fa-solid fa-bag-shopping"></i> <span class="cart-count">(<?= $totalItems ?>)</span></div>
</div>

<!-- Page header -->
<div class="pagehead">
  <h1>Your Cart</h1>
  <div class="crumb">
    <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Continue Shopping</a>
  </div>
</div>

<!-- Main grid -->
<div class="wrap">

  <!-- Left: cart items -->
  <section class="cart-panel">
    <div class="cart-head">
      <div class="count"><?= $totalItems ?> Item<?= $totalItems==1?'':'s' ?></div>
      <button id="clear-cart" class="remove-btn" title="Clear all">Clear Cart</button>
    </div>

    <table class="cart-table">
      <tbody id="cart-body">
        <?php if (!empty($_SESSION['cart'])): ?>
          <?php foreach ($_SESSION['cart'] as $item): ?>
          <tr data-id="<?= $item['id'] ?>">
            <td>
              <div class="rowgrid">
                <div class="prod-img"><img src="<?= htmlspecialchars($item['image']) ?>" alt=""></div>
                <div>
                  <div class="prod-name"><?= htmlspecialchars($item['name']) ?></div>
                  <div class="sku">SKU: <?= htmlspecialchars($item['id']) ?> · In Stock</div>
                </div>
                <div class="cell-price">R <?= number_format($item['price'], 2) ?></div>
                <div>
                  <input type="number" min="1" value="<?= (int)$item['quantity'] ?>" class="qty-input">
                </div>
                <div class="cell-subtotal">R <?= number_format($item['price'] * $item['quantity'], 2) ?></div>
                <div class="cell-action"><button class="remove-btn">✕</button></div>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td style="padding:20px; text-align:center; color:var(--muted)">Your cart is empty</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </section>

  <!-- Right: summary (no promo input) -->
  <aside class="summary">
    <h3>Promotions</h3>
    <div class="row save">
      <span>Free Shipping on Orders R399+</span>
      <span>- R0.00</span>
    </div>

    <div class="totals">
      <div class="row"><span>Subtotal</span><span>R <span id="total-price"><?= number_format($totalPrice, 2) ?></span></span></div>
      <div class="row"><span>Shipping cost</span><span>R 0.00</span></div>
      <div class="row err"><span>Shipping Discount</span><span>- R0.00</span></div>
      <div class="row"><span>Estimated Sales Tax</span><span>TBD</span></div>
      <div class="row grand"><span>Estimated Total</span><span>R <span id="est-total"><?= number_format($totalPrice, 2) ?></span></span></div>
    </div>

    <button class="checkout" onclick="window.location.href='checkout.php'">CHECKOUT</button>
  </aside>
</div>

<div class="upsell">You may also like · (add product suggestions here later)</div>

<script>
// Toast messages
function showMessage(message, success=true) {
  const msg = document.createElement('div');
  msg.className = 'message-banner ' + (success ? 'message-success' : 'message-error');
  msg.textContent = message;
  document.body.appendChild(msg);
  setTimeout(() => msg.classList.add('show'), 100);
  setTimeout(() => { msg.classList.remove('show'); setTimeout(() => msg.remove(), 600); }, 3000);
}

// AJAX cart actions
async function updateCart(action, data = {}) {
  data.action = action;
  const formData = new FormData();
  for (const k in data) formData.append(k, data[k]);
  const res = await fetch('cart.php', { method:'POST', body:formData });
  const result = await res.json();
  if (result.success) {
    renderCart(result.cart, result.totalPrice, result.cartCount);
    showMessage(result.message, true);
  } else {
    showMessage(result.message || 'Something went wrong', false);
  }
}

// Render cart body
function renderCart(cart, totalPrice, cartCount) {
  const tbody = document.getElementById('cart-body');
  const totalPriceEl = document.getElementById('total-price');
  const estTotalEl = document.getElementById('est-total');
  const cartCountEl = document.querySelector('.cart-count');

  tbody.innerHTML = '';
  if (Object.keys(cart).length === 0) {
    tbody.innerHTML = '<tr><td style="padding:20px; text-align:center; color:#6b7280">Your cart is empty 😢</td></tr>';
  } else {
    for (const id in cart) {
      const item = cart[id];
      const row = document.createElement('tr');
      row.dataset.id = item.id;
      row.innerHTML = `
        <td>
          <div class="rowgrid">
            <div class="prod-img"><img src="${item.image}" alt=""></div>
            <div>
              <div class="prod-name">${item.name}</div>
              <div class="sku">SKU: ${item.id} · In Stock</div>
            </div>
            <div class="cell-price">R ${parseFloat(item.price).toFixed(2)}</div>
            <div><input type="number" min="1" value="${item.quantity}" class="qty-input"></div>
            <div class="cell-subtotal">R ${(item.price * item.quantity).toFixed(2)}</div>
            <div class="cell-action"><button class="remove-btn">✕</button></div>
          </div>
        </td>`;
      tbody.appendChild(row);
    }
  }

  totalPriceEl.textContent = totalPrice;
  estTotalEl.textContent = totalPrice;
  cartCountEl.textContent = `(${cartCount})`;
}

// Quantity change
document.addEventListener('change', e => {
  if (e.target.classList.contains('qty-input')) {
    const tr = e.target.closest('tr');
    const id = tr.dataset.id;
    const quantity = e.target.value;
    updateCart('update_qty', { product_id: id, quantity });
  }
});

// Remove item
document.addEventListener('click', e => {
  if (e.target.classList.contains('remove-btn')) {
    const id = e.target.closest('tr').dataset.id;
    updateCart('remove_item', { product_id: id });
  }
});

// Clear cart
document.getElementById('clear-cart').addEventListener('click', () => updateCart('clear_cart'));
</script>
</body>
</html>
