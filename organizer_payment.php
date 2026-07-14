<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/access.php';

require_organizer();

if (is_organizer_paid()) {
    header('Location: organizer_dashboard.php');
    exit;
}

$u = current_user();
$err = '';
$processing = false;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = trim((string)($_POST['payment_method'] ?? ''));
    if (!in_array($method, ['upi', 'card', 'netbanking'], true)) {
        $err = 'Please select a valid payment method.';
    } else {
        // Simple simulation validation
        if ($method === 'upi') {
            $upiId = trim((string)($_POST['upi_id'] ?? ''));
            if ($upiId === '') {
                $err = 'Please enter your UPI ID.';
            }
        } elseif ($method === 'card') {
            $cardNum = preg_replace('/\D/', '', (string)($_POST['card_number'] ?? ''));
            $expiry = trim((string)($_POST['card_expiry'] ?? ''));
            $cvv = trim((string)($_POST['card_cvv'] ?? ''));
            if (strlen($cardNum) < 16 || $expiry === '' || strlen($cvv) < 3) {
                $err = 'Please fill out all card details correctly.';
            }
        }
        
        if ($err === '') {
            $processing = true;
            
            // Perform simulated update to database
            try {
                $pdo = db();
                $st = $pdo->prepare('UPDATE users SET is_paid_member = 1 WHERE id = :id');
                $st->execute([':id' => (int)$u['id']]);
                
                // Update session
                $_SESSION['user']['is_paid_member'] = true;
                $success = true;
            } catch (Throwable $e) {
                $err = 'Database update failed: ' . $e->getMessage();
                $processing = false;
            }
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-8 col-xl-7">
    
    <?php if ($success): ?>
      <!-- Success Screen -->
      <div class="cp-card p-5 text-center my-4 animate-fade-in">
        <div class="mb-4">
          <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 text-success" style="width: 80px; height: 80px; border: 2px solid var(--cp-green)">
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor" class="bi bi-patch-check-fill" viewBox="0 0 16 16">
              <path d="M10.067.87a2.89 2.89 0 0 0-4.134 0l-.622.638-.89-.011a2.89 2.89 0 0 0-2.924 2.924l.01.89-.636.622a2.89 2.89 0 0 0 0 4.134l.637.622-.011.89a2.89 2.89 0 0 0 2.924 2.924l.89-.01.622.636a2.89 2.89 0 0 0 4.134 0l.622-.637.89.011a2.89 2.89 0 0 0 2.924-2.924l-.01-.89.636-.622a2.89 2.89 0 0 0 0-4.134l-.637-.622.011-.89a2.89 2.89 0 0 0-2.924-2.924l-.89.01zm.287 5.984-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7 8.793l2.646-2.647a.5.5 0 0 1 .708.708z"/>
            </svg>
          </div>
        </div>
        <h2 class="fw-bold mb-2">Payment Successful!</h2>
        <p class="cp-muted mb-4">Your Organizer Premium Membership has been activated successfully.</p>
        
        <div class="p-3 mb-4 rounded bg-dark bg-opacity-50 border border-secondary text-start mx-auto" style="max-width: 400px;">
          <div class="d-flex justify-content-between mb-2">
            <span class="cp-muted small">Transaction ID:</span>
            <span class="small font-monospace text-light">CP-TXN-<?= strtoupper(bin2hex(random_bytes(6))) ?></span>
          </div>
          <div class="d-flex justify-content-between mb-2">
            <span class="cp-muted small">Amount Paid:</span>
            <span class="small fw-semibold text-light">₹1,178.82 INR</span>
          </div>
          <div class="d-flex justify-content-between">
            <span class="cp-muted small">Status:</span>
            <span class="badge bg-success-subtle text-success border border-success border-opacity-25 rounded-pill">Active</span>
          </div>
        </div>
        
        <a href="organizer_dashboard.php" class="btn btn-cp btn-lg px-5">Go to Dashboard</a>
      </div>

    <?php elseif ($processing): ?>
      <!-- Simulated Loading Screen -->
      <div class="cp-card p-5 text-center my-4">
        <div class="spinner-border text-success mb-4" style="width: 3rem; height: 3rem;" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <h3 class="fw-bold mb-2">Processing Payment...</h3>
        <p class="cp-muted mb-0">Please do not refresh the page or click back. We are communicating with your bank.</p>
        
        <script>
          setTimeout(function() {
            window.location.reload();
          }, 2500);
        </script>
      </div>

    <?php else: ?>
      <!-- Checkout Form -->
      <div class="cp-card p-4 p-md-5 my-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
          <div>
            <h2 class="fw-bold mb-1">Upgrade to Premium</h2>
            <div class="cp-muted">Activate your tournament organization membership</div>
          </div>
          <span class="badge cp-badge rounded-pill px-3 py-2 fs-6">₹ INR Checkout</span>
        </div>

        <?php if ($err): ?>
          <div class="alert alert-danger"><?= h($err) ?></div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
          <!-- Summary Column -->
          <div class="col-md-5 order-md-2">
            <div class="p-4 rounded border border-secondary" style="background: rgba(255,255,255,0.02)">
              <h5 class="fw-bold mb-3 border-bottom border-secondary pb-2">Order Summary</h5>
              <div class="d-flex justify-content-between mb-2">
                <span class="cp-muted small">Membership Plan</span>
                <span class="small">₹999.00</span>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <span class="cp-muted small">GST (18%)</span>
                <span class="small">₹179.82</span>
              </div>
              <hr class="border-secondary my-3">
              <div class="d-flex justify-content-between align-items-end mb-0">
                <span class="fw-bold text-light">Total (INR)</span>
                <span class="fw-bold fs-4 text-success">₹1,178.82</span>
              </div>
            </div>
            
            <div class="mt-3 small cp-muted p-2 text-center border border-secondary border-dashed rounded">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="bi bi-shield-lock-fill text-success me-1" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M8 0c-.69 0-1.843.265-2.928.56-1.11.3-2.229.655-2.887.87a1.54 1.54 0 0 0-1.044 1.262c-.596 4.477.787 7.795 2.465 9.99a11.777 11.777 0 0 0 2.517 2.453c.386.273.744.482 1.048.625.28.132.581.24.829.24s.548-.108.829-.24a7.158 7.158 0 0 0 1.048-.625 11.775 11.775 0 0 0 2.517-2.453c1.678-2.195 3.061-5.513 2.465-9.99a1.54 1.54 0 0 0-1.044-1.263 62.467 62.467 0 0 0-2.887-.87C9.843.266 8.69 0 8 0zm0 5a1.5 1.5 0 0 1 .5 2.915 1 1 0 0 1-.5.783v1.802a.5.5 0 0 1-1 0v-1.802a1 1 0 0 1-.5-.783A1.5 1.5 0 0 1 8 5z"/>
              </svg>
              Secure 256-Bit SSL Encrypted Payment
            </div>
          </div>

          <!-- Checkout Form Column -->
          <div class="col-md-7 order-md-1">
            <form method="post" id="checkoutForm">
              <h5 class="fw-bold mb-3">Select Payment Method</h5>
              
              <!-- Payment Method Radio Toggle -->
              <div class="d-flex flex-column gap-2 mb-4">
                <!-- UPI Selector -->
                <label class="p-3 rounded border border-secondary d-flex align-items-center justify-content-between cursor-pointer method-selector" style="background: rgba(255,255,255,0.02)">
                  <div class="d-flex align-items-center gap-3">
                    <input type="radio" class="form-check-input mt-0" name="payment_method" value="upi" checked onclick="togglePaymentFields('upi')">
                    <div>
                      <div class="fw-semibold">UPI Payment</div>
                      <div class="cp-muted small">GPay, PhonePe, Paytm, BHIM</div>
                    </div>
                  </div>
                  <span class="fs-5">🇮🇳 UPI</span>
                </label>

                <!-- Card Selector -->
                <label class="p-3 rounded border border-secondary d-flex align-items-center justify-content-between cursor-pointer method-selector" style="background: rgba(255,255,255,0.02)">
                  <div class="d-flex align-items-center gap-3">
                    <input type="radio" class="form-check-input mt-0" name="payment_method" value="card" onclick="togglePaymentFields('card')">
                    <div>
                      <div class="fw-semibold">Credit / Debit Card</div>
                      <div class="cp-muted small">Visa, Mastercard, RuPay</div>
                    </div>
                  </div>
                  <span class="fs-5">💳 Card</span>
                </label>

                <!-- Net Banking Selector -->
                <label class="p-3 rounded border border-secondary d-flex align-items-center justify-content-between cursor-pointer method-selector" style="background: rgba(255,255,255,0.02)">
                  <div class="d-flex align-items-center gap-3">
                    <input type="radio" class="form-check-input mt-0" name="payment_method" value="netbanking" onclick="togglePaymentFields('netbanking')">
                    <div>
                      <div class="fw-semibold">Net Banking</div>
                      <div class="cp-muted small">All Indian Banks supported</div>
                    </div>
                  </div>
                  <span class="fs-5">🏦 Bank</span>
                </label>
              </div>

              <!-- Payment Fields Details -->
              <div class="p-4 rounded border border-secondary mb-4 bg-dark bg-opacity-25">
                
                <!-- UPI Details -->
                <div id="upi-fields" class="payment-fields-group">
                  <label class="form-label">UPI ID *</label>
                  <input class="form-control mb-2" type="text" name="upi_id" placeholder="username@upi" value="<?= h($_POST['upi_id'] ?? '') ?>">
                  <div class="form-text cp-muted">Enter your virtual payment address (VPA) to get approval request.</div>
                </div>

                <!-- Card Details -->
                <div id="card-fields" class="payment-fields-group d-none">
                  <div class="mb-3">
                    <label class="form-label">Cardholder Name *</label>
                    <input class="form-control" type="text" name="card_name" placeholder="John Doe">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Card Number *</label>
                    <input class="form-control" type="text" name="card_number" maxlength="19" placeholder="4111 2222 3333 4444" id="cardNumInput">
                  </div>
                  <div class="row g-2">
                    <div class="col-6">
                      <label class="form-label">Expiry Date *</label>
                      <input class="form-control" type="text" name="card_expiry" placeholder="MM/YY" maxlength="5">
                    </div>
                    <div class="col-6">
                      <label class="form-label">CVV *</label>
                      <input class="form-control" type="password" name="card_cvv" placeholder="•••" maxlength="4">
                    </div>
                  </div>
                </div>

                <!-- Netbanking Details -->
                <div id="netbanking-fields" class="payment-fields-group d-none">
                  <label class="form-label">Select Bank *</label>
                  <select class="form-select" name="bank_name">
                    <option value="sbi">State Bank of India</option>
                    <option value="hdfc">HDFC Bank</option>
                    <option value="icici">ICICI Bank</option>
                    <option value="axis">Axis Bank</option>
                    <option value="kotak">Kotak Mahindra Bank</option>
                  </select>
                </div>

              </div>

              <div class="d-grid">
                <button class="btn btn-cp btn-lg py-3" type="submit">Pay ₹1,178.82 Securely</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<script>
function togglePaymentFields(method) {
    document.querySelectorAll('.payment-fields-group').forEach(el => {
        el.classList.add('d-none');
    });
    document.getElementById(method + '-fields').classList.remove('d-none');
}

// Auto format card number with spaces
const cardInput = document.getElementById('cardNumInput');
if (cardInput) {
    cardInput.addEventListener('input', function (e) {
        let val = e.target.value.replace(/\D/g, '');
        let formatted = '';
        for (let i = 0; i < val.length; i++) {
            if (i > 0 && i % 4 === 0) formatted += ' ';
            formatted += val[i];
        }
        e.target.value = formatted;
    });
}
</script>

<style>
.cursor-pointer { cursor: pointer; }
.method-selector:hover { border-color: rgba(21, 209, 122, 0.4) !important; background: rgba(21, 209, 122, 0.02) !important; }
.border-dashed { border-style: dashed !important; }
.animate-fade-in { animation: fadeIn 0.4s ease-out forwards; }
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>

<?php require_once __DIR__ . '/footer.php'; ?>
