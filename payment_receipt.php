<?php
session_start();
include("backend/db_connect.php");

// Ensure only workers access
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'worker') {
  header("Location: login.html");
  exit();
}

$worker_id = $_SESSION['user_id'];
$application_id = isset($_GET['application_id']) ? intval($_GET['application_id']) : 0;

// Get payment details
$sql = "SELECT 
    a.application_id,
    a.job_id,
    a.user_id,
    a.total_work_hours,
    a.job_completed,
    a.completion_date,
    a.payment_status,
    a.payment_amount,
    a.payment_date,
    a.payment_method,
    a.employer_notes,
    j.title as job_title,
    j.pay_rate,
    j.location,
    j.category,
    j.description,
    j.employer_id,
    e.email as employer_email,
    e.name as employer_name,
    ep.phone as employer_phone,
    w.email as worker_email,
    w.name as worker_name,
    wp.phone as worker_phone,
    (SELECT COUNT(*) FROM attendance WHERE application_id = a.application_id) as total_sessions,
    pr.bonus_amount,
    pr.deduction_amount,
    pr.payment_reference,
    pr.notes as payment_notes
    FROM applications a
    JOIN jobs j ON a.job_id = j.job_id
    JOIN users e ON j.employer_id = e.user_id
    LEFT JOIN profiles ep ON j.employer_id = ep.user_id
    JOIN users w ON a.user_id = w.user_id
    LEFT JOIN profiles wp ON a.user_id = wp.user_id
    LEFT JOIN payment_records pr ON a.application_id = pr.application_id
    WHERE a.application_id = ? AND a.user_id = ? AND a.payment_status = 'paid'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $application_id, $worker_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  $_SESSION['error_message'] = "Payment receipt not found or payment not yet processed.";
  header("Location: worker_payment.php");
  exit();
}

$data = $result->fetch_assoc();
$receipt_number = 'RCPT-' . str_pad($data['application_id'], 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Receipt #<?= $receipt_number ?> | POP!Work</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary: #8a1538;
      --primary-light: #c91f4d;
      --text-dark: #1c1e21;
      --text-medium: #4a5568;
      --text-light: #65676b;
      --border-color: #e1e8ed;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
      background: #f5f7fa;
      padding: 20px;
    }

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: var(--primary);
      text-decoration: none;
      margin-bottom: 20px;
      font-weight: 600;
      padding: 10px 16px;
      background: white;
      border-radius: 8px;
      transition: all 0.3s;
    }

    .back-link:hover {
      background: var(--primary);
      color: white;
      transform: translateX(-4px);
    }

    .receipt-container {
      max-width: 900px;
      margin: 0 auto;
      background: white;
      box-shadow: 0 5px 30px rgba(0,0,0,0.1);
    }

    .receipt-header {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      color: white;
      padding: 40px;
      text-align: center;
    }

    .receipt-header h1 {
      font-size: 36px;
      margin-bottom: 8px;
      font-weight: 700;
    }

    .receipt-header p {
      font-size: 18px;
      opacity: 0.9;
    }

    .receipt-number {
      background: rgba(255,255,255,0.2);
      display: inline-block;
      padding: 8px 20px;
      border-radius: 20px;
      font-weight: 600;
      margin-top: 12px;
      font-size: 14px;
      letter-spacing: 1px;
    }

    .receipt-body {
      padding: 40px;
    }

    .section {
      margin-bottom: 32px;
      padding-bottom: 32px;
      border-bottom: 2px solid #f0f0f0;
    }

    .section:last-child {
      border-bottom: none;
      margin-bottom: 0;
      padding-bottom: 0;
    }

    .section-title {
      font-size: 14px;
      font-weight: 700;
      color: var(--primary);
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .parties-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
    }

    .party-box {
      background: #f8f9fa;
      padding: 20px;
      border-radius: 12px;
      border: 2px solid #e9ecef;
    }

    .party-label {
      font-size: 12px;
      font-weight: 700;
      color: var(--text-light);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 12px;
    }

    .party-name {
      font-size: 20px;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 8px;
    }

    .party-info {
      font-size: 14px;
      color: var(--text-medium);
      line-height: 1.8;
    }

    .party-info div {
      display: flex;
      align-items: flex-start;
      gap: 8px;
      margin-bottom: 4px;
    }

    .party-info i {
      color: var(--primary);
      font-size: 12px;
      margin-top: 4px;
    }

    .job-details-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
    }

    .detail-box {
      padding: 16px;
      background: #f8f9fa;
      border-radius: 8px;
      border-left: 4px solid var(--primary);
    }

    .detail-label {
      font-size: 11px;
      font-weight: 700;
      color: var(--text-light);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 6px;
    }

    .detail-value {
      font-size: 16px;
      font-weight: 600;
      color: var(--text-dark);
    }

    .payment-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 16px;
    }

    .payment-table th,
    .payment-table td {
      padding: 16px;
      text-align: left;
    }

    .payment-table thead {
      background: #f8f9fa;
      border-bottom: 2px solid var(--primary);
    }

    .payment-table th {
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      color: var(--text-medium);
      letter-spacing: 0.5px;
    }

    .payment-table td {
      font-size: 15px;
      color: var(--text-dark);
      border-bottom: 1px solid #f0f0f0;
    }

    .payment-table tbody tr:last-child td {
      border-bottom: none;
    }

    .payment-table .amount-cell {
      text-align: right;
      font-weight: 600;
    }

    .total-row {
      background: linear-gradient(135deg, rgba(138, 21, 56, 0.1), rgba(201, 31, 77, 0.1));
      font-weight: 700;
    }

    .total-row td {
      font-size: 20px;
      padding-top: 20px;
      padding-bottom: 20px;
      border-top: 3px solid var(--primary);
      color: var(--primary);
    }

    .payment-method-box {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      background: rgba(39, 174, 96, 0.1);
      border: 2px solid rgba(39, 174, 96, 0.3);
      border-radius: 8px;
      color: #27ae60;
      font-weight: 600;
      font-size: 14px;
      margin-top: 8px;
    }

    .notes-box {
      background: #fff3cd;
      border-left: 4px solid #ffc107;
      padding: 16px;
      border-radius: 8px;
      margin-top: 16px;
    }

    .notes-title {
      font-size: 13px;
      font-weight: 700;
      color: #856404;
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .notes-content {
      font-size: 14px;
      color: #856404;
      line-height: 1.6;
    }

    .receipt-footer {
      background: #f8f9fa;
      padding: 24px 40px;
      text-align: center;
      border-top: 2px solid #e9ecef;
    }

    .footer-text {
      font-size: 13px;
      color: var(--text-light);
      line-height: 1.8;
    }

    .action-buttons {
      display: flex;
      gap: 12px;
      justify-content: center;
      margin-bottom: 20px;
    }

    .btn {
      padding: 12px 28px;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      border: none;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      color: white;
    }

    .btn-secondary {
      background: white;
      color: var(--primary);
      border: 2px solid var(--primary);
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }

    @media print {
      body {
        background: white;
        padding: 0;
      }

      .back-link,
      .action-buttons {
        display: none;
      }

      .receipt-container {
        box-shadow: none;
        max-width: 100%;
      }
    }

    @media (max-width: 768px) {
      .parties-grid {
        grid-template-columns: 1fr;
      }

      .receipt-body {
        padding: 24px;
      }

      .receipt-header {
        padding: 24px;
      }

      .receipt-header h1 {
        font-size: 28px;
      }

      .action-buttons {
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
  <a href="worker_payment.php" class="back-link">
    <i class="fas fa-arrow-left"></i> Back to Payments
  </a>

  <div class="action-buttons">
    <button onclick="window.print()" class="btn btn-primary">
      <i class="fas fa-print"></i> Print Receipt
    </button>
    <button onclick="downloadPDF()" class="btn btn-secondary">
      <i class="fas fa-download"></i> Download PDF
    </button>
  </div>

  <div class="receipt-container">
    <div class="receipt-header">
      <h1>💰 Payment Receipt</h1>
      <p>Official Payment Confirmation</p>
      <div class="receipt-number"><?= $receipt_number ?></div>
    </div>

    <div class="receipt-body">
      <!-- Parties Section -->
      <div class="section">
        <div class="section-title">
          <i class="fas fa-users"></i> Payment Parties
        </div>
        <div class="parties-grid">
          <div class="party-box">
            <div class="party-label">Paid By (Employer)</div>
            <div class="party-name"><?= htmlspecialchars($data['employer_name'] ?? 'Employer') ?></div>
            <div class="party-info">
              <div>
                <i class="fas fa-envelope"></i>
                <span><?= htmlspecialchars($data['employer_email']) ?></span>
              </div>
              <?php if ($data['employer_phone']): ?>
              <div>
                <i class="fas fa-phone"></i>
                <span><?= htmlspecialchars($data['employer_phone']) ?></span>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="party-box">
            <div class="party-label">Paid To (Worker)</div>
            <div class="party-name"><?= htmlspecialchars($data['worker_name'] ?? 'Worker') ?></div>
            <div class="party-info">
              <div>
                <i class="fas fa-envelope"></i>
                <span><?= htmlspecialchars($data['worker_email']) ?></span>
              </div>
              <?php if ($data['worker_phone']): ?>
              <div>
                <i class="fas fa-phone"></i>
                <span><?= htmlspecialchars($data['worker_phone']) ?></span>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Job Details Section -->
      <div class="section">
        <div class="section-title">
          <i class="fas fa-briefcase"></i> Job Details
        </div>
        <div class="job-details-grid">
          <div class="detail-box">
            <div class="detail-label">Job Title</div>
            <div class="detail-value"><?= htmlspecialchars($data['job_title']) ?></div>
          </div>
          <div class="detail-box">
            <div class="detail-label">Location</div>
            <div class="detail-value"><?= htmlspecialchars($data['location']) ?></div>
          </div>
          <div class="detail-box">
            <div class="detail-label">Category</div>
            <div class="detail-value"><?= htmlspecialchars($data['category']) ?></div>
          </div>
          <div class="detail-box">
            <div class="detail-label">Work Sessions</div>
            <div class="detail-value"><?= $data['total_sessions'] ?> sessions</div>
          </div>
          <div class="detail-box">
            <div class="detail-label">Completion Date</div>
            <div class="detail-value"><?= date('M d, Y', strtotime($data['completion_date'])) ?></div>
          </div>
          <div class="detail-box">
            <div class="detail-label">Payment Date</div>
            <div class="detail-value"><?= date('M d, Y', strtotime($data['payment_date'])) ?></div>
          </div>
        </div>

        <?php if ($data['payment_method']): ?>
        <div class="payment-method-box">
          <i class="fas fa-check-circle"></i>
          <span>Paid via <?= ucwords(str_replace('_', ' ', $data['payment_method'])) ?></span>
          <?php if ($data['payment_reference']): ?>
            <span>• Ref: <?= htmlspecialchars($data['payment_reference']) ?></span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Payment Breakdown Section -->
      <div class="section">
        <div class="section-title">
          <i class="fas fa-calculator"></i> Payment Breakdown
        </div>
        <table class="payment-table">
          <thead>
            <tr>
              <th>Description</th>
              <th>Hours / Rate</th>
              <th class="amount-cell">Amount (RM)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Work Hours</td>
              <td><?= number_format($data['total_work_hours'], 2) ?> hours × RM <?= number_format($data['pay_rate'], 2) ?>/hour</td>
              <td class="amount-cell"><?= number_format($data['total_work_hours'] * $data['pay_rate'], 2) ?></td>
            </tr>
            <?php if (isset($data['bonus_amount']) && $data['bonus_amount'] > 0): ?>
            <tr>
              <td>Bonus</td>
              <td>Performance bonus</td>
              <td class="amount-cell" style="color: #27ae60;">+ <?= number_format($data['bonus_amount'], 2) ?></td>
            </tr>
            <?php endif; ?>
            <?php if (isset($data['deduction_amount']) && $data['deduction_amount'] > 0): ?>
            <tr>
              <td>Deduction</td>
              <td>Applied deduction</td>
              <td class="amount-cell" style="color: #e74c3c;">- <?= number_format($data['deduction_amount'], 2) ?></td>
            </tr>
            <?php endif; ?>
            <tr class="total-row">
              <td colspan="2">Total Amount Paid</td>
              <td class="amount-cell">RM <?= number_format($data['payment_amount'], 2) ?></td>
            </tr>
          </tbody>
        </table>

        <?php if ($data['employer_notes'] || $data['payment_notes']): ?>
        <div class="notes-box">
          <div class="notes-title"><i class="fas fa-sticky-note"></i> Payment Notes</div>
          <div class="notes-content">
            <?= nl2br(htmlspecialchars($data['employer_notes'] ?? $data['payment_notes'])) ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="receipt-footer">
      <div class="footer-text">
        <strong>Receipt generated on <?= date('F d, Y \a\t g:i A') ?></strong><br>
        This is an official payment receipt from POP!Work platform.<br>
        For questions or concerns, please contact your employer or POP!Work support.
      </div>
    </div>
  </div>

  <script>
    function downloadPDF() {
      // Simple implementation - in production, use a proper PDF library
      alert('PDF download functionality requires additional setup.\nFor now, please use Print and save as PDF.');
      window.print();
    }
  </script>
</body>
</html>