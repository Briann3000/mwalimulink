<?php
// views/elibrary_cart.php - E-Library Shopping Cart & Batch M-Pesa Checkout
if (session_status() === PHP_SESSION_NONE) {
  init_session();
}
$authUser = auth_user();
$userEmail = $authUser['email'] ?? '';
$paymentRef = trim($_GET['ref'] ?? '');
$buyerEmail = trim($_GET['email'] ?? ($userEmail ?: ''));
$isCompleted = !empty($paymentRef);

$cartIds = $_SESSION['elibrary_cart'] ?? [];
$cartItems = [];
$totalAmount = 0.00;

if (!empty($cartIds)) {
  $ids = array_map('intval', $cartIds);
  $slots = R::genSlots($ids);
  $cartItems = R::find('resource', " id IN ({$slots}) ", $ids);
  foreach ($cartItems as $item) {
    $totalAmount += max(0.00, (float) $item->price);
  }
}

// Post-payment handling
$purchasedItems = [];
if ($isCompleted) {
  $purchasedIds = $_SESSION['last_purchased_cart'] ?? $cartIds;
  if (!empty($purchasedIds)) {
    $ids = array_map('intval', $purchasedIds);
    $slots = R::genSlots($ids);
    $purchasedItems = R::find('resource', " id IN ({$slots}) ", $ids);
    foreach ($purchasedItems as $item) {
      record_resource_access($item->id, $authUser['role'] ?? 'guest', $authUser['user_id'] ?? null, $buyerEmail, 'mpesa_purchase', (float) $item->price, $paymentRef);
      $_SESSION['elibrary_unlocked_' . $item->id] = true;
    }

    // Send automated email delivery with download links
    $emailSessionKey = 'emailed_cart_' . md5($paymentRef . '_' . implode('_', $ids));
    if (!empty($buyerEmail) && empty($_SESSION[$emailSessionKey])) {
      send_elibrary_purchase_email($buyerEmail, $purchasedItems, $paymentRef);
      $_SESSION[$emailSessionKey] = true;
    }
  }
  // Clear cart after successful purchase
  $_SESSION['elibrary_cart'] = [];
  unset($_SESSION['last_purchased_cart']);
}

$intasendPubKey = env('INTASEND_PUBLIC_KEY', 'ISPubKey_live_sample');
$isTestMode = env('INTASEND_TEST_MODE', 'true') === 'true';
?>

<style>
  .mwalimu-cart-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 1.5rem 1rem 4rem 1rem;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    color: #1e293b;
  }

  .cart-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
  }

  @media (min-width: 800px) {
    .cart-grid {
      grid-template-columns: 1fr 340px;
    }
  }

  .cart-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  }

  .cart-item-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
    gap: 12px;
  }

  .cart-item-row:last-child {
    border-bottom: none;
  }

  .btn-cart-pay {
    background: #0f766e;
    color: #ffffff !important;
    border: none;
    padding: 14px 20px;
    border-radius: 10px;
    font-weight: 800;
    font-size: 0.95rem;
    width: 100%;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: background 0.2s;
  }

  .btn-cart-pay:hover {
    background: #0d9488;
  }
</style>

<div class="mwalimu-cart-container">

  <!-- Breadcrumbs -->
  <div style="font-size: 0.82rem; color: #64748b; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 6px;">
    <a href="/" style="color: #64748b; text-decoration: none;"><i class="fa fa-home"></i> Home</a>
    <span>/</span>
    <a href="/elibrary" style="color: #64748b; text-decoration: none;">E-Library</a>
    <span>/</span>
    <span style="color: #0f766e; font-weight: 700;">Shopping Cart</span>
  </div>

  <?php if ($isCompleted): ?>
    <!-- SUCCESS PURCHASE CONFIRMATION & DOWNLOADS -->
    <div class="cart-card" style="border-color: #86efac; background: #f0fdf4; margin-bottom: 1.5rem; text-align: center; padding: 2.5rem 1.5rem;">
      <div style="width: 60px; height: 60px; border-radius: 50%; background: #dcfce7; color: #15803d; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin: 0 auto 1rem auto;">
        <i class="fa fa-check"></i>
      </div>
      <h1 style="font-size: 1.6rem; font-weight: 900; color: #0f172a; margin: 0 0 0.5rem 0;">Payment Successful!</h1>
      <p style="color: #166534; font-size: 0.95rem; max-width: 500px; margin: 0 auto 1.5rem auto;">
        Your payment has been verified. You can download your purchased learning materials below.
      </p>

      <div style="max-width: 600px; margin: 0 auto; display: flex; flex-direction: column; gap: 10px; text-align: left;">
        <?php foreach ($purchasedItems as $pItem): ?>
          <div style="background: #ffffff; border: 1px solid #bbf7d0; border-radius: 10px; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
            <div>
              <div style="font-size: 0.75rem; font-weight: 800; color: #0369a1;"><?= h($pItem->grade_level) ?> &bull; <?= h($pItem->subject) ?></div>
              <div style="font-size: 0.9rem; font-weight: 700; color: #0f172a;"><?= h($pItem->title) ?></div>
            </div>
            <a href="/elibrary/download?id=<?= $pItem->id ?>&ref=<?= urlencode($paymentRef) ?>&email=<?= urlencode($buyerEmail) ?>" class="btn-cart-pay" style="width: auto; padding: 8px 16px; font-size: 0.82rem; background: #15803d;">
              <i class="fa fa-download"></i> Download PDF
            </a>
          </div>
        <?php endforeach; ?>
      </div>

      <div style="margin-top: 2rem;">
        <a href="/elibrary" style="color: #0f766e; text-decoration: none; font-weight: 700; font-size: 0.9rem;">
          &larr; Back to E-Library Catalog
        </a>
      </div>
    </div>

  <?php elseif (empty($cartItems)): ?>
    <!-- EMPTY CART STATE -->
    <div class="cart-card" style="text-align: center; padding: 3.5rem 1.5rem;">
      <div style="font-size: 3rem; color: #cbd5e1; margin-bottom: 0.75rem;">
        <i class="fa fa-cart-shopping"></i>
      </div>
      <h2 style="font-size: 1.4rem; font-weight: 800; color: #0f172a; margin: 0 0 0.5rem 0;">Your Cart is Empty</h2>
      <p style="color: #64748b; font-size: 0.92rem; max-width: 420px; margin: 0 auto 1.5rem auto;">
        You have not added any learning notes or revision papers to your cart yet.
      </p>
      <a href="/elibrary" class="btn-cart-pay" style="display: inline-flex; width: auto; padding: 12px 24px;">
        <i class="fa fa-book-open"></i> Browse E-Library
      </a>
    </div>

  <?php else: ?>
    <!-- ACTIVE CART & CHECKOUT GRID -->
    <div class="cart-grid">

      <!-- Left Column: Cart Items -->
      <div class="cart-card">
        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.85rem; margin-bottom: 1rem;">
          <h2 style="font-size: 1.2rem; font-weight: 800; color: #0f172a; margin: 0;">
            Selected Items (<?= count($cartItems) ?>)
          </h2>
          <button type="button" onclick="clearCart()" style="background: none; border: none; color: #dc2626; font-size: 0.8rem; font-weight: 700; cursor: pointer;">
            Clear Cart
          </button>
        </div>

        <div>
          <?php foreach ($cartItems as $item): ?>
            <div class="cart-item-row" id="cart-item-<?= $item->id ?>">
              <div>
                <div style="font-size: 0.74rem; font-weight: 800; color: #0369a1;"><?= h($item->grade_level) ?> &bull; <?= h($item->subject) ?></div>
                <div style="font-size: 0.9rem; font-weight: 700; color: #0f172a; margin: 2px 0;"><?= h($item->title) ?></div>
                <div style="font-size: 0.75rem; color: #64748b;"><?= h($item->category) ?></div>
              </div>

              <div style="display: flex; align-items: center; gap: 14px;">
                <div style="font-size: 0.95rem; font-weight: 900; color: #0f766e;">
                  KES <?= number_format($item->price, 0) ?>
                </div>
                <button type="button" onclick="removeFromCart(<?= $item->id ?>)" style="background: #fee2e2; color: #dc2626; border: none; width: 28px; height: 28px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center;" title="Remove">
                  &times;
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Right Column: Order Summary & Instant M-Pesa Checkout -->
      <div>
        <div class="cart-card" style="position: sticky; top: 90px;">
          <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0 0 1rem 0;">Order Summary</h3>

          <div style="display: flex; justify-content: space-between; font-size: 0.9rem; color: #475569; margin-bottom: 0.75rem;">
            <span>Items Subtotal:</span>
            <span>KES <?= number_format($totalAmount, 0) ?></span>
          </div>

          <div style="display: flex; justify-content: space-between; font-size: 1.15rem; font-weight: 900; color: #0f172a; padding-top: 0.75rem; border-top: 1px solid #e2e8f0; margin-bottom: 1.25rem;">
            <span>Total:</span>
            <span style="color: #0f766e;">KES <?= number_format($totalAmount, 0) ?></span>
          </div>

          <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 1.25rem;">
            <div>
              <label style="font-size: 0.78rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Email Address for File Delivery *</label>
              <input type="email" id="buyerEmail" value="<?= h($userEmail) ?>" required placeholder="e.g. teacher@gmail.com" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 12px; font-size: 0.85rem; box-sizing: border-box;">
            </div>

            <div>
              <label style="font-size: 0.78rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">M-Pesa Phone Number</label>
              <input type="tel" id="buyerPhone" placeholder="e.g. 0712345678" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 12px; font-size: 0.85rem; box-sizing: border-box;">
            </div>
          </div>

          <button type="button" onclick="checkoutCart()" class="btn-cart-pay">
            <i class="fa fa-mobile-screen-button"></i> Pay KES <?= number_format($totalAmount, 0) ?> via M-Pesa
          </button>

          <div style="font-size: 0.75rem; color: #64748b; text-align: center; margin-top: 8px;">
            🔒 Instant STK Push & automatic PDF delivery to your email.
          </div>
        </div>
      </div>

    </div>
  <?php endif; ?>

</div>

<!-- IntaSend Inline SDK -->
<script src="https://unpkg.com/intasend-inlinejs-sdk@3.0.4/build/intasend-inline.js"></script>
<script>
  function removeFromCart(id) {
    fetch('/api/elibrary?action=remove_from_cart&id=' + id, { method: 'POST' })
      .then(res => res.json())
      .then(data => {
        window.location.reload();
      });
  }

  function clearCart() {
    if (confirm('Clear all items from your cart?')) {
      fetch('/api/elibrary?action=clear_cart', { method: 'POST' })
        .then(() => window.location.reload());
    }
  }

  function checkoutCart() {
    const email = document.getElementById('buyerEmail').value.trim();
    if (!email || !email.includes('@')) {
      alert('Please enter a valid email address to receive your files.');
      document.getElementById('buyerEmail').focus();
      return;
    }

    const amount = <?= (float)$totalAmount ?>;
    const phone = document.getElementById('buyerPhone').value.trim();

    if (typeof window.IntaSend === 'undefined') {
      alert('Payment gateway is loading. Please try again in a few moments.');
      return;
    }

    new window.IntaSend({
      public_key: '<?= h($intasendPubKey) ?>',
      live: <?= $isTestMode ? 'false' : 'true' ?>
    })
    .on('COMPLETE', function(response) {
      const trackingId = response.tracking_id || response.signature || 'INTASEND_PAID';
      window.location.href = '/elibrary/cart?ref=' + encodeURIComponent(trackingId) + '&email=' + encodeURIComponent(email);
    })
    .on('FAILED', function(response) {
      alert('M-Pesa payment was not completed. Please try again.');
    })
    .run({
      amount: amount,
      currency: 'KES',
      email: email,
      phone_number: phone,
      title: 'Mwalimu E-Library Bundle (<?= count($cartItems) ?> Items)',
      comment: 'Cart Bundle'
    });
  }
</script>
