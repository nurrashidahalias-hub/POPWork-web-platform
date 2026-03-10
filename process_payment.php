<?php
session_start();
include("backend/db_connect.php");

// Ensure only employers access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'employer') {
  header("Location: login.html");
  exit();
}

$employer_id = $_SESSION['user_id'];
$application_id = isset($_GET['application_id']) ? intval($_GET['application_id']) : 0;

// Get application details with worker and job info
$sql = "SELECT 
    a.*,
    j.title as job_title,
    j.pay_rate,
    j.location,
    j.category,
    j.employer_id,
    u.email as worker_email,
    p.name as worker_name,
    p.photo_url as worker_photo,
    (SELECT COUNT(*) FROM attendance WHERE application_id = a.application_id) as total_sessions
    FROM applications a
    JOIN jobs j ON a.job_id = j.job_id
    JOIN users u ON a.user_id = u.user_id
    LEFT JOIN profiles p ON a.user_id = p.user_id
    WHERE a.application_id = ? AND j.employer_id = ? AND a.job_completed = 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $application_id, $employer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  $_SESSION['error_message'] = "Invalid application or job not completed.";
  header("Location: job_completion_payment.php");
  exit();
}

$data = $result->fetch_assoc();

// Calculate payment
$total_hours = $data['total_work_hours'] ?? 0;
$hourly_rate = $data['pay_rate'];
$calculated_amount = $total_hours * $hourly_rate;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $bonus = isset($_POST['bonus_amount']) ? floatval($_POST['bonus_amount']) : 0;
  $deduction = isset($_POST['deduction_amount']) ? floatval($_POST['deduction_amount']) : 0;
  $final_amount = $calculated_amount + $bonus - $deduction;
  $payment_method = $_POST['payment_method'] ?? 'cash';
  $payment_reference = $_POST['payment_reference'] ?? null;
  $notes = $_POST['notes'] ?? null;

  // Update application payment status
  $update_sql = "UPDATE applications 
                 SET payment_status = 'paid',
                     payment_amount = ?,
                     payment_date = NOW(),
                     payment_method = ?,
                     employer_notes = ?
                 WHERE application_id = ?";
  $update_stmt = $conn->prepare($update_sql);
  $update_stmt->bind_param("dssi", $final_amount, $payment_method, $notes, $application_id);

  if ($update_stmt->execute()) {
    // Create payment record
    $payment_sql = "INSERT INTO payment_records (
                      application_id, user_id, employer_id, job_id,
                      total_hours, hourly_rate, total_amount,
                      bonus_amount, deduction_amount, final_amount,
                      payment_status, payment_method, payment_reference,
                      payment_date, approved_by, approved_date, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?, NOW(), ?, NOW(), ?)";
    
    $payment_stmt = $conn->prepare($payment_sql);
    $payment_stmt->bind_param("iiiidddddsssis",
      $application_id, $data['user_id'], $employer_id, $data['job_id'],
      $total_hours, $hourly_rate, $calculated_amount,
      $bonus, $deduction, $final_amount,
      $payment_method, $payment_reference, $employer_id, $notes
    );
    $payment_stmt->execute();

    $_SESSION['success_message'] = "Payment processed successfully! RM " . number_format($final_amount, 2) . " marked as paid.";
    header("Location: job_completion_payment.php");
    exit();
  } else {
    $error = "Failed to process payment. Please try again.";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Process Payment | POP!Work</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/sidebar.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: #f0f2f5;
      min-height: 100vh;
      position: relative;
    }

    /* Photo Background Like Other Pages */
    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-image: url('uploads/photos/background.jpg');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      background-attachment: fixed;
    }

    /* Main Content with Sidebar */
    .main-content {
      margin-left: 280px;
      width: calc(100% - 280px);
      min-height: 100vh;
      position: relative;
      z-index: 1;
      padding: 40px 0;
    }

    @media (max-width: 768px) {
      .main-content {
        margin-left: 0;
        width: 100%;
      }
    }

    /* Back Link - Professional Design */
    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      color: white;
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      margin-bottom: 24px;
      padding: 12px 24px;
      background: linear-gradient(135deg, #8a1538 0%, #c91f4d 100%);
      border-radius: 12px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 4px 12px rgba(138, 21, 56, 0.25);
      border: 2px solid rgba(255, 255, 255, 0.1);
      position: relative;
      overflow: hidden;
    }

    .back-link::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.5s;
    }

    .back-link:hover::before {
      left: 100%;
    }
    
    .back-link:hover {
      background: linear-gradient(135deg, #6d1028 0%, #a71d47 100%);
      box-shadow: 0 6px 20px rgba(138, 21, 56, 0.4);
      transform: translateY(-2px);
      border-color: rgba(255, 255, 255, 0.2);
    }

    .back-link:active {
      transform: translateY(0);
      box-shadow: 0 2px 8px rgba(138, 21, 56, 0.3);
    }

    .back-link i {
      font-size: 16px;
      transition: transform 0.3s;
    }

    .back-link:hover i {
      transform: translateX(-4px);
    }

    .page-title {
      font-size: 32px;
      font-weight: 800;
      color: white;
      margin-bottom: 8px;
    }

    .page-subtitle {
      color: whitesmoke;
      font-size: 16px;
      margin-bottom: 32px;
    }

    .container {
      max-width: 800px;
      margin: 0 auto;
      padding: 0 20px 60px;
    }

    .card {
      background: white;
      border-radius: 20px;
      padding: 30px;
      box-shadow: 0 5px 20px rgba(0,0,0,0.08);
      margin-bottom: 24px;
    }

    .worker-header {
      display: flex;
      align-items: center;
      gap: 20px;
      padding-bottom: 20px;
      border-bottom: 2px solid #f0f0f0;
      margin-bottom: 20px;
    }

    .worker-avatar {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: linear-gradient(135deg, #8a1538, #c91f4d);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 700;
      font-size: 32px;
    }

    .worker-avatar img {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      object-fit: cover;
    }

    .worker-details h2 {
      font-size: 24px;
      margin-bottom: 4px;
    }

    .worker-email {
      color: #666;
      font-size: 14px;
    }

    .section-title {
      font-size: 18px;
      font-weight: 600;
      margin-bottom: 16px;
      color: #333;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .detail-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 24px;
    }

    .detail-item {
      background: #f8f9fa;
      padding: 16px;
      border-radius: 12px;
    }

    .detail-label {
      font-size: 12px;
      color: #666;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 8px;
    }

    .detail-value {
      font-size: 20px;
      font-weight: 700;
      color: #333;
    }

    .payment-calculation {
      background: linear-gradient(135deg, #8a1538, #a61e47);
      color: white;
      padding: 24px;
      border-radius: 12px;
      margin-bottom: 24px;
    }

    .calc-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 0;
      border-bottom: 1px solid rgba(255,255,255,0.2);
    }

    .calc-row:last-child {
      border-bottom: none;
      padding-top: 16px;
      margin-top: 8px;
      border-top: 2px solid rgba(255,255,255,0.3);
      font-size: 24px;
      font-weight: 700;
    }

    .form-group {
      margin-bottom: 20px;
    }

    label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
      color: #333;
    }

    input, select, textarea {
      width: 100%;
      padding: 12px 16px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 15px;
      font-family: 'Poppins', sans-serif;
      transition: all 0.3s;
    }

    input:focus, select:focus, textarea:focus {
      outline: none;
      border-color: #8a1538;
      box-shadow: 0 0 0 4px rgba(138, 21, 56, 0.1);
    }

    textarea {
      resize: vertical;
      min-height: 100px;
    }

    .input-hint {
      font-size: 13px;
      color: #666;
      margin-top: 4px;
    }

    .btn {
      padding: 14px 32px;
      border: none;
      border-radius: 25px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
    }

    .btn-primary {
      background: linear-gradient(135deg, #27ae60, #2ecc71);
      color: white;
    }

    .btn-secondary {
      background: white;
      color: #8a1538;
      border: 2px solid #8a1538;
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 20px rgba(0,0,0,0.2);
    }

    .button-group {
      display: flex;
      gap: 12px;
      margin-top: 24px;
    }

    .alert {
      padding: 16px 20px;
      border-radius: 12px;
      margin-bottom: 24px;
      font-weight: 500;
    }

    .alert-error {
      background: #f8d7da;
      color: #721c24;
      border: 2px solid #f5c6cb;
    }

    @media (max-width: 768px) {
      .button-group {
        flex-direction: column;
      }

      .btn {
        width: 100%;
        justify-content: center;
      }
    }
  </style>
</head>
<body>
  <?php 
    $page_title = "Process Payment";
    include 'includes/sidebar.php';
  ?>

  <div class="main-content">
    <div class="container">
      <a href="job_completion_payment.php" class="back-link">
        <i class="fas fa-arrow-left"></i> <- Back to Jobs
      </a>
      <h1 class="page-title">💰 Process Payment</h1>
      <p class="page-subtitle">Review and process payment for completed work</p>

    <?php if (isset($error)): ?>
      <div class="alert alert-error">
        ❌ <?= $error; ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="worker-header">
        <div class="worker-avatar">
          <?php if ($data['worker_photo']): ?>
            <img src="<?= htmlspecialchars($data['worker_photo']); ?>" alt="Worker">
          <?php else: ?>
            <?= strtoupper(substr($data['worker_name'] ?? $data['worker_email'], 0, 1)); ?>
          <?php endif; ?>
        </div>
        <div class="worker-details">
          <h2><?= htmlspecialchars($data['worker_name'] ?? 'Worker'); ?></h2>
          <div class="worker-email"><?= htmlspecialchars($data['worker_email']); ?></div>
        </div>
      </div>

      <div class="section-title">📋 Job Details</div>
      <div class="detail-grid">
        <div class="detail-item">
          <div class="detail-label">Job Title</div>
          <div class="detail-value"><?= htmlspecialchars($data['job_title']); ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Location</div>
          <div class="detail-value"><?= htmlspecialchars($data['location']); ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Total Sessions</div>
          <div class="detail-value"><?= $data['total_sessions']; ?> Shifts</div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Total Hours</div>
          <div class="detail-value"><?= number_format($total_hours, 2); ?>H</div>
        </div>
      </div>

      <div class="payment-calculation">
        <div class="calc-row">
          <span>Hourly Rate:</span>
          <span>RM <?= number_format($hourly_rate, 2); ?>/hour</span>
        </div>
        <div class="calc-row">
          <span>Total Hours:</span>
          <span><?= number_format($total_hours, 2); ?> hours</span>
        </div>
        <div class="calc-row">
          <span>Calculated Amount:</span>
          <span>RM <?= number_format($calculated_amount, 2); ?></span>
        </div>
        <div class="calc-row" id="bonus-row" style="display: none;">
          <span>➕ Bonus:</span>
          <span id="bonus-display">RM 0.00</span>
        </div>
        <div class="calc-row" id="deduction-row" style="display: none;">
          <span>➖ Deduction:</span>
          <span id="deduction-display">RM 0.00</span>
        </div>
        <div class="calc-row">
          <span>Final Amount:</span>
          <span id="final-amount">RM <?= number_format($calculated_amount, 2); ?></span>
        </div>
      </div>
    </div>

    <form method="POST" id="payment-form">
      <div class="card">
        <div class="section-title">💵 Payment Details</div>

        <div class="form-group">
          <label for="payment_method">Payment Method *</label>
          <select name="payment_method" id="payment_method" required>
            <option value="cash">Cash</option>
            <option value="bank_transfer">Bank Transfer</option>
            <option value="online_banking">Online Banking</option>
            <option value="e-wallet">E-Wallet (Touch 'n Go, GrabPay, etc.)</option>
            <option value="cheque">Cheque</option>
          </select>
        </div>

        <div class="form-group">
          <label for="payment_reference">Payment Reference (Optional)</label>
          <input type="text" 
                 name="payment_reference" 
                 id="payment_reference"
                 placeholder="Transaction ID, Receipt No, etc.">
          <div class="input-hint">Enter transaction ID or reference number for tracking</div>
        </div>

        <div class="form-group">
          <label for="bonus_amount">Bonus Amount (Optional)</label>
          <input type="number" 
                 name="bonus_amount" 
                 id="bonus_amount"
                 step="0.01"
                 min="0"
                 value="0"
                 placeholder="0.00">
          <div class="input-hint">Add bonus for excellent work</div>
        </div>

        <div class="form-group">
          <label for="deduction_amount">Deduction Amount (Optional)</label>
          <input type="number" 
                 name="deduction_amount" 
                 id="deduction_amount"
                 step="0.01"
                 min="0"
                 value="0"
                 placeholder="0.00">
          <div class="input-hint">Deduct for damages, advances, etc.</div>
        </div>

        <div class="form-group">
          <label for="notes">Notes (Optional)</label>
          <textarea name="notes" 
                    id="notes"
                    placeholder="Add any notes about this payment..."></textarea>
          <div class="input-hint">Include reason for bonus/deductions if applicable</div>
        </div>

        <div class="button-group">
          <button type="submit" class="btn btn-primary" onclick="return confirmPayment()">
            ✅ Confirm & Mark as Paid
          </button>
          <a href="job_completion_payment.php" class="btn btn-secondary">
            Cancel
          </a>
        </div>
      </div>
    </form>
  </div>


  <script>
    const baseAmount = <?= $calculated_amount; ?>;
    const bonusInput = document.getElementById('bonus_amount');
    const deductionInput = document.getElementById('deduction_amount');
    const finalAmountDisplay = document.getElementById('final-amount');
    const bonusDisplay = document.getElementById('bonus-display');
    const deductionDisplay = document.getElementById('deduction-display');
    const bonusRow = document.getElementById('bonus-row');
    const deductionRow = document.getElementById('deduction-row');

    function updateFinalAmount() {
      const bonus = parseFloat(bonusInput.value) || 0;
      const deduction = parseFloat(deductionInput.value) || 0;
      const final = baseAmount + bonus - deduction;

      finalAmountDisplay.textContent = 'RM ' + final.toFixed(2);
      bonusDisplay.textContent = 'RM ' + bonus.toFixed(2);
      deductionDisplay.textContent = 'RM ' + deduction.toFixed(2);

      // Show/hide bonus and deduction rows
      bonusRow.style.display = bonus > 0 ? 'flex' : 'none';
      deductionRow.style.display = deduction > 0 ? 'flex' : 'none';
    }

    bonusInput.addEventListener('input', updateFinalAmount);
    deductionInput.addEventListener('input', updateFinalAmount);

    function confirmPayment() {
      const bonus = parseFloat(bonusInput.value) || 0;
      const deduction = parseFloat(deductionInput.value) || 0;
      const final = baseAmount + bonus - deduction;

      let message = `Confirm payment of RM ${final.toFixed(2)}?\n\n`;
      message += `Base Amount: RM ${baseAmount.toFixed(2)}\n`;
      if (bonus > 0) message += `Bonus: +RM ${bonus.toFixed(2)}\n`;
      if (deduction > 0) message += `Deduction: -RM ${deduction.toFixed(2)}\n`;
      message += `\nThis action cannot be undone.`;

      return confirm(message);
    }
  </script>
    </div> <!-- End container -->
  </div> <!-- End main-content -->
</body>
</html>