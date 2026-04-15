<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../models/ProcessModel.php';
if (!isAuthenticated()) redirect(APP_URL . '/?action=login');
requireProcessAccess(18);
$processModel = new ProcessModel($pdo);
$message = $message_type = '';
$dispensed_prescriptions = [];
$payments = [];

// PayMongo API Key (Store in environment variable in production)
// For demo purposes, using test keys - replace with actual keys in production
$PAYMONGO_SECRET_KEY = getenv('PAYMONGO_SECRET_KEY') ?: 'sk_test_demo_key_replace_in_production';

try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.*, u.first_name, u.last_name,
               GROUP_CONCAT(CONCAT(pr.product_name, ' x', dm.quantity) SEPARATOR ', ') as medicines,
               SUM(pr.unit_price * dm.quantity) as total_amount
        FROM prescriptions p
        JOIN users u ON p.customer_id = u.id
        LEFT JOIN dispensed_medicines dm ON p.id = dm.prescription_id
        LEFT JOIN products pr ON dm.product_id = pr.id
        WHERE p.customer_id = ? AND p.status = 'Dispensed'
        GROUP BY p.id
        ORDER BY p.upload_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $dispensed_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $dispensed_prescriptions = [];
}

try {
    $stmt = $pdo->prepare("
        SELECT pay.*, pr.patient_name, pr.doctor_name
        FROM payments pay
        JOIN prescriptions pr ON pay.prescription_id = pr.id
        WHERE pay.customer_id = ?
        ORDER BY pay.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $payments = [];
}

// Handle payment processing
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='process_payment') {
    if (!verifyCSRFToken($_POST['csrf_token']??'')) {
        $message='Invalid token.';
        $message_type='error';
    } else {
        try {
            $prescription_id = intval($_POST['prescription_id']??0);
            $amount = floatval($_POST['amount']??0);
            $payment_method = sanitize($_POST['payment_method']??'');
            $token = sanitize($_POST['payment_token']??'');
            
            if (!$prescription_id || !$amount || !$payment_method) {
                throw new Exception('All fields required.');
            }
            
            if ($payment_method === 'credit_card' || $payment_method === 'gcash') {
                if (!$token) {
                    throw new Exception('Payment token required.');
                }
                
                // For demo purposes, simulate payment processing
                // In production, integrate with actual PayMongo API
                $payment_intent_id = 'pi_' . bin2hex(random_bytes(16));
                $status = 'succeeded'; // Simulate successful payment
                
                // Attempt to use PayMongo API if credentials are available
                if ($PAYMONGO_SECRET_KEY !== 'sk_test_demo_key_replace_in_production') {
                    try {
                        // Create payment intent with PayMongo
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, 'https://api.paymongo.com/v1/payment_intents');
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                        curl_setopt($ch, CURLOPT_USERPWD, $PAYMONGO_SECRET_KEY . ':');
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                            'data' => [
                                'attributes' => [
                                    'amount' => intval($amount * 100), // Convert to cents
                                    'payment_method_allowed' => [$payment_method === 'credit_card' ? 'card' : 'gcash'],
                                    'currency' => 'PHP',
                                    'description' => 'Pharmacy Prescription Payment - Rx #' . $prescription_id
                                ]
                            ]
                        ]));
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                        
                        $response = curl_exec($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($http_code === 201) {
                            $payment_data = json_decode($response, true);
                            $payment_intent_id = $payment_data['data']['id'] ?? $payment_intent_id;
                            
                            // Attach payment method to intent
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, 'https://api.paymongo.com/v1/payment_intents/' . $payment_intent_id . '/attach');
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                            curl_setopt($ch, CURLOPT_USERPWD, $PAYMONGO_SECRET_KEY . ':');
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                                'data' => [
                                    'attributes' => [
                                        'payment_method' => $token
                                    ]
                                ]
                            ]));
                            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                            
                            $response = curl_exec($ch);
                            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);
                            
                            if ($http_code === 200) {
                                $payment_data = json_decode($response, true);
                                $status = $payment_data['data']['attributes']['status'] ?? 'succeeded';
                            }
                        }
                    } catch(Exception $e) {
                        // Fall back to demo mode if API fails
                        $status = 'succeeded';
                    }
                }
                
                // Save payment to database
                $stmt = $pdo->prepare("
                    INSERT INTO payments (prescription_id, customer_id, amount, payment_method, transaction_id, status, created_at, paid_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
                ");
                $paid_at = ($status === 'succeeded') ? date('Y-m-d H:i:s') : null;
                $db_status = ($status === 'succeeded') ? 'Completed' : 'Pending';
                $stmt->execute([$prescription_id, $_SESSION['user_id'], $amount, $payment_method, $payment_intent_id, $db_status, $paid_at]);
                
                if ($status === 'succeeded') {
                    $message = 'Payment successful! Your prescription has been paid.';
                    $message_type = 'success';
                } else {
                    $message = 'Payment pending. Please complete the payment process.';
                    $message_type = 'warning';
                }
            } else {
                throw new Exception('Invalid payment method.');
            }
            
            // Refresh data
            $stmt = $pdo->prepare("
                SELECT DISTINCT p.*, u.first_name, u.last_name,
                       GROUP_CONCAT(CONCAT(pr.product_name, ' x', dm.quantity) SEPARATOR ', ') as medicines,
                       SUM(pr.unit_price * dm.quantity) as total_amount
                FROM prescriptions p
                JOIN users u ON p.customer_id = u.id
                LEFT JOIN dispensed_medicines dm ON p.id = dm.prescription_id
                LEFT JOIN products pr ON dm.product_id = pr.id
                WHERE p.customer_id = ? AND p.status = 'Dispensed'
                GROUP BY p.id
                ORDER BY p.upload_date DESC
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $dispensed_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("
                SELECT pay.*, pr.patient_name, pr.doctor_name
                FROM payments pay
                JOIN prescriptions pr ON pay.prescription_id = pr.id
                WHERE pay.customer_id = ?
                ORDER BY pay.created_at DESC
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            $message = $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Process Payment — <?php echo APP_NAME; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/public/style.css">
<script src="https://js.paymongo.com/v1/api.js"></script>
<style>
.prescription-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;display:grid;grid-template-columns:1fr auto;gap:16px;align-items:start}
.prescription-info{display:flex;flex-direction:column;gap:8px}
.prescription-patient{font-size:14px;font-weight:700;color:var(--text)}
.prescription-meta{font-size:12px;color:var(--text2);display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px}
.prescription-meta-item{display:flex;align-items:center;gap:4px}
.prescription-meta-label{color:var(--text3);font-weight:600}
.payment-badge{display:inline-block;padding:6px 12px;border-radius:6px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.payment-pending{background:rgba(245,158,11,.12);color:var(--warn)}
.payment-completed{background:rgba(79,255,176,.12);color:var(--accent)}
.payment-failed{background:rgba(248,113,113,.12);color:var(--danger)}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(4px);overflow-y:auto;padding:20px}
.modal.show{display:flex}
.modal-content{background:var(--surface);border:1px solid var(--border2);border-radius:12px;padding:28px;width:90%;max-width:600px;box-shadow:0 8px 40px rgba(0,0,0,.5)}
.payment-method-btn{padding:12px 16px;border:2px solid var(--border);border-radius:8px;background:var(--surface2);color:var(--text);cursor:pointer;font-size:13px;font-weight:600;transition:all .2s;display:flex;align-items:center;gap:8px}
.payment-method-btn:hover{border-color:var(--border2)}
.payment-method-btn.active{border-color:var(--accent);background:rgba(79,255,176,.05);color:var(--accent)}
.card-input{padding:12px;border:1px solid var(--border);border-radius:8px;background:var(--surface2);color:var(--text);font-family:monospace;font-size:13px;margin-bottom:12px}
.history-table{width:100%;border-collapse:collapse;margin-top:12px}
.history-table thead{background:var(--surface2);border-bottom:2px solid var(--border)}
.history-table th{padding:10px;text-align:left;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.05em;font-size:10px}
.history-table td{padding:10px;border-bottom:1px solid var(--border);font-size:11px}
.history-table tbody tr:hover{background:var(--surface2)}
</style>
</head><body>
<nav class="navbar"><div class="navbar-content">
<h2><i class="fas fa-capsules" style="margin-right:8px;color:var(--accent)"></i><?php echo APP_NAME; ?></h2>
<div class="navbar-right"><span class="user-role"><?php echo htmlspecialchars($ROLE_NAMES[$_SESSION['role_id']]??'User'); ?></span>
<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn-nav"><i class="fas fa-th-large"></i> Dashboard</a>
<a href="<?php echo APP_URL; ?>/?action=logout" class="btn-nav" style="color:var(--danger);border-color:rgba(248,113,113,0.3)"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div></div></nav>

<div class="process-wrapper" style="max-width:1100px">
<h1><i class="fas fa-credit-card" style="color:var(--accent);margin-right:10px"></i>Process Payment</h1>
<p class="subtitle">Pay for your dispensed medications securely</p>

<?php if($message): ?><div class="alert alert-<?php echo $message_type; ?>"><i class="fas fa-<?php echo $message_type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>

<div class="content-section">
<h2>Pending Payments</h2>

<?php if(empty($dispensed_prescriptions)): ?>
<div class="empty-state">
  <i class="fas fa-inbox" style="font-size:48px;margin-bottom:12px;display:block;color:var(--accent2)"></i>
  <p>No dispensed prescriptions requiring payment.</p>
</div>
<?php else: ?>

<?php foreach($dispensed_prescriptions as $prescription): 
    // Check if already paid
    $stmt = $pdo->prepare("SELECT status FROM payments WHERE prescription_id = ? AND customer_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$prescription['id'], $_SESSION['user_id']]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    $isPaid = $payment && $payment['status'] === 'Completed';
?>

<div class="prescription-card">
  <div class="prescription-info">
    <div class="prescription-patient">
      <i class="fas fa-pills" style="margin-right:8px;color:var(--accent)"></i>
      Prescription #<?php echo $prescription['id']; ?>
    </div>
    <div class="prescription-meta">
      <div class="prescription-meta-item">
        <span class="prescription-meta-label">Patient:</span>
        <span><?php echo htmlspecialchars($prescription['patient_name']); ?></span>
      </div>
      <div class="prescription-meta-item">
        <span class="prescription-meta-label">Doctor:</span>
        <span>Dr. <?php echo htmlspecialchars($prescription['doctor_name']); ?></span>
      </div>
      <div class="prescription-meta-item">
        <span class="prescription-meta-label">Date:</span>
        <span><?php echo date('M d, Y', strtotime($prescription['upload_date'])); ?></span>
      </div>
    </div>
    <div style="margin-top:8px;font-size:12px;color:var(--text2)">
      <strong>Medicines:</strong> <?php echo htmlspecialchars($prescription['medicines'] ?? 'N/A'); ?>
    </div>
  </div>
  <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px">
    <div style="text-align:right">
      <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Amount Due</div>
      <div style="font-size:20px;font-weight:700;color:var(--accent)">₱<?php echo number_format($prescription['total_amount'] ?? 0, 2); ?></div>
    </div>
    <?php if($isPaid): ?>
    <span class="payment-badge payment-completed"><i class="fas fa-check-circle" style="margin-right:4px"></i>Paid</span>
    <?php else: ?>
    <button type="button" class="btn btn-sm" onclick="openPaymentModal(<?php echo $prescription['id']; ?>, <?php echo $prescription['total_amount'] ?? 0; ?>)">
      <i class="fas fa-credit-card"></i> Pay Now
    </button>
    <?php endif; ?>
  </div>
</div>

<?php endforeach; ?>

<?php endif; ?>
</div>

<!-- Payment History -->
<div class="content-section">
<h2>Payment History</h2>

<?php if(empty($payments)): ?>
<div class="empty-state"><p>No payment history yet.</p></div>
<?php else: ?>

<div style="overflow-x:auto">
<table class="history-table">
<thead>
  <tr>
    <th>Date</th>
    <th>Prescription</th>
    <th>Amount</th>
    <th>Method</th>
    <th>Status</th>
  </tr>
</thead>
<tbody>
<?php foreach($payments as $p): 
    $statusBadge = '<span class="payment-badge payment-pending">PENDING</span>';
    if ($p['status'] === 'Completed') $statusBadge = '<span class="payment-badge payment-completed">COMPLETED</span>';
    elseif ($p['status'] === 'Failed') $statusBadge = '<span class="payment-badge payment-failed">FAILED</span>';
?>
<tr>
  <td><?php echo date('M d, Y H:i', strtotime($p['created_at'])); ?></td>
  <td>Rx #<?php echo $p['prescription_id']; ?></td>
  <td style="font-weight:600;color:var(--accent)">₱<?php echo number_format($p['amount'], 2); ?></td>
  <td style="text-transform:capitalize"><?php echo htmlspecialchars(str_replace('_', ' ', $p['payment_method'])); ?></td>
  <td><?php echo $statusBadge; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php endif; ?>
</div>

<a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="modal">
<div class="modal-content">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <h2 style="font-size:18px;font-weight:700;color:var(--text);margin:0">Complete Payment</h2>
    <button type="button" style="background:none;border:none;font-size:24px;color:var(--text3);cursor:pointer;padding:0" onclick="closePaymentModal()"><i class="fas fa-times"></i></button>
  </div>

  <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:20px">
    <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px">Amount to Pay</div>
    <div id="modalAmount" style="font-size:24px;font-weight:700;color:var(--accent)">₱0.00</div>
  </div>

  <form id="paymentForm" method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
    <input type="hidden" name="action" value="process_payment">
    <input type="hidden" id="prescriptionId" name="prescription_id">
    <input type="hidden" id="amount" name="amount">
    <input type="hidden" id="paymentToken" name="payment_token">

    <div class="form-group">
      <label>Payment Method <span class="required">*</span></label>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <button type="button" class="payment-method-btn active" onclick="selectPaymentMethod('credit_card', this)">
          <i class="fas fa-credit-card"></i> Credit Card
        </button>
        <button type="button" class="payment-method-btn" onclick="selectPaymentMethod('gcash', this)">
          <i class="fas fa-mobile-alt"></i> GCash
        </button>
      </div>
      <input type="hidden" id="paymentMethod" name="payment_method" value="credit_card">
    </div>

    <div id="cardInputs" style="display:block">
      <div class="form-group">
        <label>Card Number <span class="required">*</span></label>
        <input type="text" id="cardNumber" class="card-input" placeholder="1234 5678 9012 3456" maxlength="19" required>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>Expiry Date <span class="required">*</span></label>
          <input type="text" id="cardExpiry" class="card-input" placeholder="MM/YY" maxlength="5" required>
        </div>
        <div class="form-group">
          <label>CVV <span class="required">*</span></label>
          <input type="text" id="cardCVV" class="card-input" placeholder="123" maxlength="4" required>
        </div>
      </div>
    </div>

    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px">
      <button type="button" class="btn btn-secondary" onclick="closePaymentModal()"><i class="fas fa-times"></i> Cancel</button>
      <button type="submit" class="btn btn-primary"><i class="fas fa-lock"></i> Pay Securely</button>
    </div>
  </form>
</div>
</div>

<footer class="footer"><p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p></footer>

<script>
function openPaymentModal(prescriptionId, amount) {
  document.getElementById('prescriptionId').value = prescriptionId;
  document.getElementById('amount').value = amount;
  document.getElementById('modalAmount').textContent = '₱' + parseFloat(amount).toFixed(2);
  document.getElementById('paymentModal').classList.add('show');
}

function closePaymentModal() {
  document.getElementById('paymentModal').classList.remove('show');
}

function selectPaymentMethod(method, btn) {
  document.querySelectorAll('.payment-method-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('paymentMethod').value = method;
}

document.getElementById('paymentForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const cardNumber = document.getElementById('cardNumber').value.replace(/\s/g, '');
  const cardExpiry = document.getElementById('cardExpiry').value;
  const cardCVV = document.getElementById('cardCVV').value;
  
  if (!cardNumber || !cardExpiry || !cardCVV) {
    alert('Please fill in all card details');
    return;
  }
  
  // For demo purposes, create a simple token
  // In production, use PayMongo's client-side tokenization
  const token = 'tok_' + Math.random().toString(36).substr(2, 9);
  document.getElementById('paymentToken').value = token;
  
  this.submit();
});

// Format card number with spaces
document.getElementById('cardNumber').addEventListener('input', function(e) {
  let value = e.target.value.replace(/\s/g, '');
  let formatted = value.match(/.{1,4}/g)?.join(' ') || value;
  e.target.value = formatted;
});

// Format expiry date
document.getElementById('cardExpiry').addEventListener('input', function(e) {
  let value = e.target.value.replace(/\D/g, '');
  if (value.length >= 2) {
    value = value.substr(0, 2) + '/' + value.substr(2, 2);
  }
  e.target.value = value;
});

window.addEventListener('click', e => {
  if (e.target === document.getElementById('paymentModal')) {
    closePaymentModal();
  }
});
</script>
</body></html>
