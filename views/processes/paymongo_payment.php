<?php
require_once __DIR__ . '/../../config/config.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(18);

$checkout_items = $_SESSION['checkout_items'] ?? [];
$checkout_total = $_SESSION['checkout_total'] ?? 0;
$message = '';
$message_type = '';
$selected_payment_method = '';

if (empty($checkout_items)) {
    redirect(APP_URL . '/views/processes/checkout.php');
}

// PayMongo Payment Link - Replace with your actual PayMongo payment link
// To get your payment link: Log in to PayMongo dashboard → Create a payment link
$PAYMONGO_PAYMENT_LINK = 'https://pm.link/org-p8AovarnFGZQmVomi24zGLjm/test/WEANwK4';

// PayMongo API Configuration
$PAYMONGO_SECRET_KEY = PAYMONGO_SECRET_KEY;
$PAYMONGO_PUBLIC_KEY = PAYMONGO_PUBLIC_KEY;

// Get customer info
$stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle payment method selection
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='select_payment_method') {
    $selected_payment_method = sanitize($_POST['payment_method']??'');
    
    if (empty($selected_payment_method)) {
        $message = 'Please select a payment method.';
        $message_type = 'error';
    } else {
        $_SESSION['selected_payment_method'] = $selected_payment_method;
    }
}

// Handle pay with PayMongo (for card/gcash)
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='create_checkout') {
    try {
        $payment_method = sanitize($_POST['payment_method']??'');
        
        if (empty($payment_method)) {
            throw new Exception('Payment method not selected.');
        }
        
        // Store checkout data in session
        $_SESSION['paymongo_checkout'] = [
            'items' => $checkout_items,
            'total' => $checkout_total,
            'timestamp' => time(),
            'payment_method' => $payment_method
        ];
        
        // Redirect to PayMongo payment link
        header('Location: ' . $PAYMONGO_PAYMENT_LINK);
        exit;
    } catch(Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

// Handle cash payment
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='pay_cash') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) {
        $message='Invalid token.';
        $message_type='error';
    } else {
        try {
            $checkout_data = [
                'items' => $checkout_items,
                'total' => $checkout_total,
                'timestamp' => time(),
                'payment_method' => 'Cash'
            ];
            
            $payment_ids = [];
            foreach ($checkout_items as $item) {
                $prescription_id = $item['prescription_id'];
                $amount = $item['total_amount'];
                
                $transaction_id = 'cash_' . bin2hex(random_bytes(16));
                
                $stmt = $pdo->prepare("
                    INSERT INTO payments (prescription_id, customer_id, amount, payment_method, transaction_id, status, created_at, paid_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$prescription_id, $_SESSION['user_id'], $amount, 'Cash', $transaction_id, 'Completed']);
                
                $stmt = $pdo->prepare("
                    UPDATE dispensed_medicines 
                    SET payment_status = 'Verified' 
                    WHERE prescription_id = ?
                ");
                $stmt->execute([$prescription_id]);
                
                $payment_ids[] = $prescription_id;
            }
            
            unset($_SESSION['checkout_items']);
            unset($_SESSION['checkout_total']);
            unset($_SESSION['selected_payment_method']);
            
            $_SESSION['payment_success'] = true;
            $_SESSION['payment_ids'] = $payment_ids;
            $_SESSION['payment_method'] = 'Cash';
            
            header('Location: ' . APP_URL . '/views/processes/payment_success.php');
            exit;
        } catch(Exception $e) {
            $message = $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Handle confirm payment after returning from PayMongo
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='confirm_payment') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) {
        $message='Invalid token.';
        $message_type='error';
    } else {
        try {
            if (!isset($_SESSION['paymongo_checkout'])) {
                throw new Exception('Invalid payment session.');
            }
            
            $checkout_data = $_SESSION['paymongo_checkout'];
            $checkout_items = $checkout_data['items'];
            $payment_method = $checkout_data['payment_method'];
            
            // Map payment method names
            $payment_method_map = [
                'credit_card' => 'Credit Card',
                'gcash' => 'GCash',
                'cash' => 'Cash'
            ];
            $payment_method_display = $payment_method_map[$payment_method] ?? $payment_method;
            
            $payment_ids = [];
            foreach ($checkout_items as $item) {
                $prescription_id = $item['prescription_id'];
                $amount = $item['total_amount'];
                
                $transaction_id = 'pm_' . bin2hex(random_bytes(16));
                
                $stmt = $pdo->prepare("
                    INSERT INTO payments (prescription_id, customer_id, amount, payment_method, transaction_id, status, created_at, paid_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$prescription_id, $_SESSION['user_id'], $amount, $payment_method_display, $transaction_id, 'Completed']);
                
                $stmt = $pdo->prepare("
                    UPDATE dispensed_medicines 
                    SET payment_status = 'Verified' 
                    WHERE prescription_id = ?
                ");
                $stmt->execute([$prescription_id]);
                
                $payment_ids[] = $prescription_id;
            }
            
            unset($_SESSION['checkout_items']);
            unset($_SESSION['checkout_total']);
            unset($_SESSION['paymongo_checkout']);
            unset($_SESSION['selected_payment_method']);
            
            $_SESSION['payment_success'] = true;
            $_SESSION['payment_ids'] = $payment_ids;
            $_SESSION['payment_method'] = $payment_method_display;
            
            header('Location: ' . APP_URL . '/views/processes/payment_success.php');
            exit;
        } catch(Exception $e) {
            $message = $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Handle PayMongo callback - success (if PayMongo redirects with status parameter)
if ($_SERVER['REQUEST_METHOD']==='GET' && ($_GET['status']??'')==='success') {
    try {
        if (!isset($_SESSION['paymongo_checkout'])) {
            throw new Exception('Invalid payment session.');
        }
        
        $checkout_data = $_SESSION['paymongo_checkout'];
        $checkout_items = $checkout_data['items'];
        $payment_method = $checkout_data['payment_method'];
        
        // Map payment method names
        $payment_method_map = [
            'credit_card' => 'Credit Card',
            'gcash' => 'GCash',
            'cash' => 'Cash'
        ];
        $payment_method_display = $payment_method_map[$payment_method] ?? $payment_method;
        
        $payment_ids = [];
        foreach ($checkout_items as $item) {
            $prescription_id = $item['prescription_id'];
            $amount = $item['total_amount'];
            
            $transaction_id = 'pm_' . bin2hex(random_bytes(16));
            
            $stmt = $pdo->prepare("
                INSERT INTO payments (prescription_id, customer_id, amount, payment_method, transaction_id, status, created_at, paid_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$prescription_id, $_SESSION['user_id'], $amount, $payment_method_display, $transaction_id, 'Completed']);
            
            $stmt = $pdo->prepare("
                UPDATE dispensed_medicines 
                SET payment_status = 'Verified' 
                WHERE prescription_id = ?
            ");
            $stmt->execute([$prescription_id]);
            
            $payment_ids[] = $prescription_id;
        }
        
        unset($_SESSION['checkout_items']);
        unset($_SESSION['checkout_total']);
        unset($_SESSION['paymongo_checkout']);
        unset($_SESSION['selected_payment_method']);
        
        $_SESSION['payment_success'] = true;
        $_SESSION['payment_ids'] = $payment_ids;
        $_SESSION['payment_method'] = $payment_method_display;
        
        header('Location: ' . APP_URL . '/views/processes/payment_success.php');
        exit;
    } catch(Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

// Handle cancel
if ($_SERVER['REQUEST_METHOD']==='GET' && ($_GET['status']??'')==='cancel') {
    unset($_SESSION['paymongo_checkout']);
    $message = 'Payment cancelled. Please try again.';
    $message_type = 'error';
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>PayMongo Payment — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<style>
.payment-container{display:grid;grid-template-columns:1fr 350px;gap:24px;margin-bottom:28px}
.payment-form{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:40px}
.payment-header{text-align:center;margin-bottom:32px}
.payment-icon{font-size:48px;margin-bottom:16px;display:block;color:var(--accent)}
.payment-title{font-size:24px;font-weight:700;color:var(--text);margin-bottom:8px}
.payment-subtitle{font-size:14px;color:var(--text2)}
.customer-info{background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:20px;margin-bottom:24px}
.info-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);font-size:13px}
.info-row:last-child{border-bottom:none}
.info-label{color:var(--text3);font-weight:600}
.info-value{color:var(--text);font-weight:600}
.payment-methods{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:24px}
.payment-method-btn{padding:16px;border:2px solid var(--border);border-radius:10px;background:var(--surface2);color:var(--text);cursor:pointer;transition:all .2s;text-align:center;font-weight:600;font-size:13px}
.payment-method-btn:hover{border-color:var(--border2);background:var(--surface3)}
.payment-method-btn.selected{border-color:var(--accent);background:rgba(79,255,176,.1);color:var(--accent)}
.payment-method-icon{font-size:24px;margin-bottom:8px;display:block}
.payment-actions{display:flex;gap:12px;margin-top:24px}
.order-summary{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px;height:fit-content;position:sticky;top:20px}
.summary-title{font-size:13px;font-weight:700;color:var(--text);margin-bottom:16px;display:flex;align-items:center;gap:8px}
.summary-item{padding:12px 0;border-bottom:1px solid var(--border);font-size:12px;display:flex;justify-content:space-between}
.summary-item:last-child{border-bottom:none}
.summary-total{padding:16px 0;border-top:2px solid var(--border);margin-top:16px;display:flex;justify-content:space-between;font-size:14px;font-weight:700}
.summary-total-value{color:var(--accent);font-size:18px}
</style>
</head><body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right"><span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']]??'User'); ?></span>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>

<div class="process-wrapper" style="max-width:1200px">
<div class="payment-container">
  <div class="payment-form">
    <div class="payment-header">
      <i class="fas fa-lock payment-icon"></i>
      <h1 class="payment-title">Secure Payment</h1>
      <p class="payment-subtitle">Select your preferred payment method</p>
    </div>

    <?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>" style="margin-bottom:20px"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>

    <div class="customer-info">
      <div class="info-row">
        <span class="info-label">Name</span>
        <span class="info-value"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Email</span>
        <span class="info-value"><?php echo htmlspecialchars($customer['email']); ?></span>
      </div>
      <div class="info-row">
        <span class="info-label">Amount</span>
        <span class="info-value" style="color:var(--accent);font-size:16px">₱<?php echo number_format($checkout_total, 2); ?></span>
      </div>
    </div>

    <div style="margin-bottom:24px">
      <label style="display:block;font-size:12px;font-weight:700;color:var(--text);margin-bottom:12px;text-transform:uppercase;letter-spacing:.05em">Select Payment Method <span class="required">*</span></label>
      <div class="payment-methods">
        <button type="button" class="payment-method-btn" onclick="selectPaymentMethod('credit_card', this); return false;">
          <i class="fas fa-credit-card payment-method-icon"></i>
          Credit/Debit Card
        </button>
        <button type="button" class="payment-method-btn" onclick="selectPaymentMethod('gcash', this); return false;">
          <i class="fas fa-mobile-alt payment-method-icon"></i>
          GCash
        </button>
        <button type="button" class="payment-method-btn" onclick="selectPaymentMethod('cash', this); return false;">
          <i class="fas fa-money-bill-wave payment-method-icon"></i>
          Cash
        </button>
      </div>
      <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="">
    </div>

    <?php if(isset($_SESSION['paymongo_checkout'])): ?>
    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:16px;margin-bottom:20px">
      <p style="margin:0;font-size:13px;color:var(--text2)"><i class="fas fa-info-circle" style="color:var(--accent);margin-right:8px"></i>Payment session active. After completing payment on PayMongo, click "Confirm Payment" below.</p>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
      <input type="hidden" name="action" value="confirm_payment">
      <div class="payment-actions">
        <a href="<?php echo APP_URL; ?>/views/processes/checkout.php" class="btn btn-secondary" style="flex:1"><i class="fas fa-arrow-left"></i> Back</a>
        <button type="submit" class="btn btn-primary" style="flex:1"><i class="fas fa-check-circle"></i> Confirm Payment</button>
      </div>
    </form>
    <?php else: ?>
    <div class="payment-actions">
      <a href="<?php echo APP_URL; ?>/views/processes/checkout.php" class="btn btn-secondary" style="flex:1"><i class="fas fa-arrow-left"></i> Back</a>
      <button type="button" class="btn btn-primary" style="flex:1" id="proceedBtn" onclick="proceedWithPayment()" disabled><i class="fas fa-lock"></i> Proceed to Payment</button>
    </div>
    <?php endif; ?>
  </div>

  <div class="order-summary">
    <div class="summary-title"><i class="fas fa-receipt"></i> Order Summary</div>
    <?php foreach($checkout_items as $item): ?>
    <div class="summary-item">
      <span>Rx #<?php echo $item['prescription_id']; ?></span>
      <span style="font-weight:600;color:var(--accent)">₱<?php echo number_format($item['total_amount'], 2); ?></span>
    </div>
    <?php endforeach; ?>
    <div class="summary-total">
      <span>Total Amount</span>
      <span class="summary-total-value">₱<?php echo number_format($checkout_total, 2); ?></span>
    </div>
  </div>
</div>
</div>

<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>

<script>
let selectedPaymentMethod = '';

function selectPaymentMethod(method, button) {
  selectedPaymentMethod = method;
  document.getElementById('selectedPaymentMethod').value = method;
  
  // Update button selection
  document.querySelectorAll('.payment-method-btn').forEach(btn => btn.classList.remove('selected'));
  button.classList.add('selected');
  
  // Enable proceed button
  document.getElementById('proceedBtn').disabled = false;
}

function proceedWithPayment() {
  const method = document.getElementById('selectedPaymentMethod').value;
  
  if (!method) {
    alert('Please select a payment method');
    return false;
  }
  
  if (method === 'cash') {
    // Direct cash payment
    if (confirm('Confirm payment via Cash?\n\nAmount: ₱<?php echo number_format($checkout_total, 2); ?>')) {
      const form = document.createElement('form');
      form.method = 'POST';
      form.innerHTML = `
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="pay_cash">
      `;
      document.body.appendChild(form);
      form.submit();
    }
    return false;
  } else if (method === 'credit_card' || method === 'gcash') {
    // PayMongo payment (card or gcash)
    if (confirm('You will be redirected to PayMongo to complete your payment.\n\nAmount: ₱<?php echo number_format($checkout_total, 2); ?>')) {
      const form = document.createElement('form');
      form.method = 'POST';
      form.innerHTML = `
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="create_checkout">
        <input type="hidden" name="payment_method" value="${method}">
      `;
      document.body.appendChild(form);
      form.submit();
    }
    return false;
  }
}
</script>
</body></html>
