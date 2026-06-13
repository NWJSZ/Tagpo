<?php
require_once 'config/database.php';
require_once 'config/session_config.php';
require_once 'config/app.php';

if (!isset($_SESSION['receipt_data'])) {
  header('Location: ' . getBaseUrl() . 'payment.php');
  exit();
}

$receipt = $_SESSION['receipt_data'];
$issuedAt = date('F j, Y \a\t g:i A', $receipt['timestamp']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Receipt | Tagpo</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/styles.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" />

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

  <style>
    html,
    body {
      background: #fff !important;
    }

    main.receipt-card,
    .receipt-card {
      max-width: 900px;
      margin: 2rem auto;
      background: #fff !important;
    }

    .receipt-card .card,
    .receipt-card .card-body {
      background: #fff !important;
      border: none;
    }

    .receipt-card .card {
      box-shadow: none !important;
    }

    .receipt-badge {
      font-size: 0.85rem;
    }

    @media print {
      .no-print {
        display: none !important;
      }

      .navbar {
        position: static !important;
        width: 100% !important;
      }

      .navbar .container {
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        padding: 0 !important;
      }

      .navbar-toggler,
      .navbar .navbar-collapse,
      .navbar .d-flex {
        display: none !important;
      }

      .navbar-brand {
        margin: 0 auto !important;
        text-align: center !important;
      }
    }
  </style>
</head>

<body>

  <?php include 'includes/header.php'; ?>

  <main class="container receipt-card">
    <div class="card shadow-sm border-0">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start mb-4">
          <div>
            <h2 class="fw-bold mb-1">Booking Receipt</h2>
            <p class="text-muted mb-0">Thank you for your booking.</p>
          </div>
          <div class="text-end">
            <span class="badge bg-primary receipt-badge">Invoice</span>
            <h5 class="mb-0"><?= htmlspecialchars($receipt['invoice_number']); ?></h5>
            <small class="text-muted"><?= htmlspecialchars($issuedAt); ?></small>
          </div>
        </div>

        <div class="row mb-4">
          <div class="col-md-6">
            <h6 class="text-uppercase text-secondary">Customer</h6>
            <p class="mb-1"><?= htmlspecialchars($receipt['first_name'] . ' ' . $receipt['last_name']); ?></p>
            <p class="mb-1 text-muted"><?= htmlspecialchars($receipt['email']); ?></p>
            <p class="mb-1 text-muted"><?= htmlspecialchars($receipt['phone']); ?></p>
          </div>
          <div class="col-md-6">
            <h6 class="text-uppercase text-secondary">Payment</h6>
            <p class="mb-1"><?= htmlspecialchars($receipt['payment_method']); ?></p>
            <?php if (!empty($receipt['card_last4'])): ?>
              <p class="mb-1">Card ending in ****<?= htmlspecialchars($receipt['card_last4']); ?></p>
            <?php endif; ?>
            <p class="mb-0"><strong>Total Paid:</strong> ₱<?= number_format($receipt['total']); ?></p>
          </div>
        </div>

        <div class="mb-4">
          <h6 class="text-uppercase text-secondary">Event Details</h6>
          <div class="row">
            <div class="col-sm-6 mb-2"><strong>Venue:</strong> <?= htmlspecialchars($receipt['venue_name']); ?></div>
            <div class="col-sm-6 mb-2"><strong>Event Type:</strong> <?= htmlspecialchars($receipt['event_id']); ?></div>
            <div class="col-sm-6 mb-2"><strong>Date:</strong> <?= htmlspecialchars($receipt['event_date']); ?></div>
            <div class="col-sm-6 mb-2"><strong>Time:</strong> <?= htmlspecialchars($receipt['event_time']); ?></div>
            <div class="col-sm-6 mb-2"><strong>Duration:</strong> <?= htmlspecialchars($receipt['duration']); ?></div>
            <div class="col-sm-6 mb-2"><strong>Guests:</strong> <?= htmlspecialchars($receipt['guest_count']); ?></div>
          </div>
        </div>

        <div class="mb-4">
          <h6 class="text-uppercase text-secondary">Charges</h6>
          <div class="table-responsive">
            <table class="table table-borderless mb-0">
              <tbody>
                <?php foreach ($receipt['fees'] as $index => $amount): ?>
                  <tr>
                    <td><?= htmlspecialchars($receipt['fee_labels'][$index]); ?></td>
                    <td class="text-end">₱<?= number_format($amount); ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!empty($receipt['addons'])): ?>
                  <tr>
                    <td>Add-ons</td>
                    <td class="text-end"><?= htmlspecialchars(implode(', ', $receipt['addons'])); ?></td>
                  </tr>
                <?php endif; ?>
                <tr class="border-top">
                  <td class="fw-bold">Grand Total</td>
                  <td class="fw-bold text-end">₱<?= number_format($receipt['total']); ?></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center gap-3 no-print" data-html2canvas-ignore="true">
          <a href="payment.php" class="btn btn-secondary">Back to Payment</a>
          <button type="button" class="btn btn-outline-secondary" onclick="window.downloadreceipt();">Download PDF</button>
          <button type="button" class="btn btn-primary" onclick="window.print();">Print Receipt</button>
        </div>
      </div>
    </div>
  </main>

  <div class="no-print">
    <?php include 'includes/footer.php'; ?>
  </div>

  <script>
    window.downloadreceipt = function() {
      const {
        jsPDF
      } = window.jspdf;

      html2canvas(document.querySelector('.receipt-card'), {
        backgroundColor: '#fff',
        scale: 2,
        useCORS: true,
      }).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const pdf = new jsPDF('p', 'mm', 'a4');

        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pdfHeight = (canvas.height * pdfWidth) / canvas.width;

        pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
        pdf.save("receipt-<?= htmlspecialchars($receipt['invoice_number']); ?>.pdf");
      });
    }
  </script>

</body>
</html>