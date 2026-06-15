<?php
require_once 'config/database.php';
require_once 'config/session_config.php';
require_once 'config/app.php';

$_SESSION['last_activity'] = time();

if (!isLoggedIn()) {
    header('Location: auth/login.php');
    exit();
}

$user   = getCurrentUser();
$userId = (int) $user['id'];

// Refresh cookie
setcookie('user_session', $user['email'], time() + 86400 * 7, '/');

// ── Fetch all bookings for this user (with venue + event + payment info) ──────
$sql = "
    SELECT
        b.booking_id,
        b.event_date,
        b.event_time,
        b.duration,
        b.guest_count,
        b.total_price,
        b.status        AS booking_status,
        v.name          AS venue_name,
        v.location      AS venue_location,
        v.image_url     AS venue_image,
        e.event_name,
        c.status        AS cart_status,
        p.payment_status,
        p.payment_method,
        p.transaction_id,
        p.payment_date
    FROM bookings b
    JOIN venues   v ON b.venue_id  = v.id
    JOIN event    e ON b.event_id  = e.event_id
    JOIN carts    c ON b.cart_id   = c.cart_id
    LEFT JOIN payments p ON p.cart_id = c.cart_id
    WHERE b.user_id = ?
    ORDER BY b.booking_id DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Fetch addons per booking ──────────────────────────────────────────────────
$bookingIds = array_column($bookings, 'booking_id');
$addonsByBooking = [];

if (!empty($bookingIds)) {
    $placeholders = implode(',', array_fill(0, count($bookingIds), '?'));
    $types = str_repeat('i', count($bookingIds));
    $stmt = $conn->prepare(
        "SELECT ba.booking_id, a.addon_name, ba.unit_price
         FROM booking_addons ba
         JOIN addons a ON ba.addon_id = a.addon_id
         WHERE ba.booking_id IN ($placeholders)"
    );
    $stmt->bind_param($types, ...$bookingIds);
    $stmt->execute();
    $addonRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($addonRows as $row) {
        $addonsByBooking[$row['booking_id']][] = $row;
    }
}

// Status badge helper
function statusBadge(string $status): string {
  return match($status) {
    'confirmed' => '<span class="badge rounded-pill badge-confirmed">Confirmed</span>',
    'pending'   => '<span class="badge rounded-pill bg-warning text-dark">Pending</span>',
    'cancelled' => '<span class="badge rounded-pill bg-danger">Cancelled</span>',
    'paid'      => '<span class="badge rounded-pill badge-paid">Paid</span>',
    'failed'    => '<span class="badge rounded-pill bg-danger">Failed</span>',
    default     => '<span class="badge rounded-pill bg-secondary">' . htmlspecialchars(ucfirst($status)) . '</span>',
  };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Bookings | TAGPO</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"/>
  <link rel="stylesheet" href="assets/css/styles.css"/>
  <style>
    .booking-card {
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      background: #fff;
      overflow: hidden;
      transition: box-shadow var(--transition);
    }
    .booking-card:hover {
      box-shadow: var(--shadow);
    }
    .booking-venue-thumb {
      width: 90px;
      height: 90px;
      object-fit: cover;
      border-radius: var(--radius);
      flex-shrink: 0;
      background: var(--bg);
    }
    .booking-venue-thumb-placeholder {
      width: 90px;
      height: 90px;
      border-radius: var(--radius);
      flex-shrink: 0;
      background: linear-gradient(135deg, #b5a084, #6b4f35);
      display: flex;
      align-items: center;
      justify-content: center;
      color: rgba(255,255,255,.6);
      font-size: 1.6rem;
    }
    .meta-pill {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 3px 10px;
      font-size: 0.78rem;
      color: var(--muted);
      font-weight: 500;
    }
    .addon-chip {
      display: inline-block;
      background: #f0f4ff;
      color: #1a56db;
      border-radius: 20px;
      padding: 2px 10px;
      font-size: 0.75rem;
      font-weight: 500;
      margin: 2px 2px 2px 0;
    }
    .booking-total {
      font-family: 'Playfair Display', serif;
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--deep);
    }
    .empty-state {
      text-align: center;
      padding: 80px 20px;
    }
    .empty-state .empty-icon {
      font-size: 4rem;
      opacity: .25;
      margin-bottom: 16px;
    }
    .section-divider {
      height: 1px;
      background: var(--border);
      margin: 12px 0;
    }
    .payment-info-row {
      font-size: 0.82rem;
      color: var(--muted);
    }
    .payment-info-row strong {
      color: var(--deep);
    }
  </style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<!-- Breadcrumb -->
<div class="breadcrumb-bar">
  <div class="container">
    <a href="index.php">Home</a>
    <span class="mx-2" style="color:#d1d5db;">/</span>
    <span>My Bookings</span>
  </div>
</div>

<!-- Page Header -->
<div class="container mt-5 mb-2">
  <span class="section-eyebrow">Your Reservations</span>
  <h2 class="section-heading">My Bookings</h2>
  <p class="section-sub">All your event bookings and payment history in one place.</p>
</div>

<!-- Bookings List -->
<div class="container mb-5">
  <?php if (empty($bookings)): ?>
    <div class="empty-state">
      <div class="empty-icon">📭</div>
      <h4>No bookings yet</h4>
      <p class="text-muted mb-4">You haven't made any bookings. Start by exploring our venues!</p>
      <a href="search.php" class="btn-book">Browse Venues</a>
    </div>

  <?php else: ?>
    <div class="row gy-4">
      <?php foreach ($bookings as $b): ?>
        <?php
          $addons       = $addonsByBooking[$b['booking_id']] ?? [];
          $hasImage     = !empty($b['venue_image']);
          $isPaid       = $b['payment_status'] === 'paid';
          $isConfirmed  = $b['booking_status'] === 'confirmed';
          $txId         = $b['transaction_id'] ?? null;
          $payDate      = $b['payment_date']   ?? null;
        ?>
        <div class="col-12">
          <div class="booking-card p-4">

            <!-- Top row: thumb + venue info + total + status -->
            <div class="d-flex gap-4 align-items-start flex-wrap">

              <!-- Venue Thumbnail -->
              <?php if ($hasImage): ?>
                <img src="<?= htmlspecialchars($b['venue_image']) ?>"
                     alt="<?= htmlspecialchars($b['venue_name']) ?>"
                     class="booking-venue-thumb">
              <?php else: ?>
                <div class="booking-venue-thumb-placeholder">
                  <i class="bi bi-building"></i>
                </div>
              <?php endif; ?>

              <!-- Main Info -->
              <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                  <div>
                    <h5 class="mb-1 fw-bold" style="font-family:'Playfair Display',serif;">
                      <?= htmlspecialchars($b['venue_name']) ?>
                    </h5>
                    <p class="text-muted small mb-2">
                      <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($b['venue_location']) ?>
                    </p>
                  </div>

                  <!-- Booking status badge -->
                  <div class="text-end">
                    <?= statusBadge($b['booking_status']) ?>
                    <?php if ($b['payment_status'] && $b['payment_status'] !== $b['booking_status']): ?>
                      <br><small class="text-muted mt-1 d-inline-block">
                        Payment: <?= statusBadge($b['payment_status']) ?>
                      </small>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Meta pills -->
                <div class="d-flex flex-wrap gap-2 mb-3">
                  <span class="meta-pill">
                    <i class="bi bi-calendar-event"></i>
                    <?= htmlspecialchars(date('F j, Y', strtotime($b['event_date']))) ?>
                  </span>
                  <span class="meta-pill">
                    <i class="bi bi-clock"></i>
                    <?= htmlspecialchars(date('g:i A', strtotime($b['event_time']))) ?>
                  </span>
                  <span class="meta-pill">
                    <i class="bi bi-hourglass-split"></i>
                    <?= (int)$b['duration'] ?> hr<?= $b['duration'] > 1 ? 's' : '' ?>
                  </span>
                  <span class="meta-pill">
                    <i class="bi bi-people"></i>
                    <?= (int)$b['guest_count'] ?> guests
                  </span>
                  <span class="meta-pill">
                    <i class="bi bi-star"></i>
                    <?= htmlspecialchars($b['event_name']) ?>
                  </span>
                </div>

                <!-- Add-ons -->
                <?php if (!empty($addons)): ?>
                  <div class="mb-3">
                    <span class="text-muted" style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.8px;">Add-ons:</span><br>
                    <?php foreach ($addons as $addon): ?>
                      <span class="addon-chip">
                        <?= htmlspecialchars($addon['addon_name']) ?>
                        <span style="opacity:.65;">+&#8369;<?= number_format($addon['unit_price']) ?></span>
                      </span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <div class="section-divider"></div>

                <!-- Bottom row: payment info + total -->
                <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mt-3">

                  <div class="payment-info-row">
                    <?php if ($b['payment_status'] === 'paid'): ?>
                      <?php if (!empty($b['payment_method'])): ?>
                        <div>
                          <strong>Method:</strong>
                          <?= htmlspecialchars(ucwords(str_replace('_', ' ', $b['payment_method']))) ?>
                        </div>
                      <?php endif; ?>

                      <?php if ($txId): ?>
                        <div><strong>Transaction ID:</strong> <?= htmlspecialchars($txId) ?></div>
                      <?php endif; ?>

                      <?php if ($payDate): ?>
                        <div>
                          <strong>Paid on:</strong>
                          <?= htmlspecialchars(date('F j, Y \a\t g:i A', strtotime($payDate))) ?>
                        </div>
                      <?php endif; ?>

                    <?php else: ?>
                      <div class="text-danger-light fw-semibold" style="color: #f34a4a !important;">
                        <i class="bi bi-exclamation-circle me-1" style="color: #f34a4a !important;"></i>
                        Payment not yet completed
                      </div>
                    <?php endif; ?>

                    <div class="mt-1 text-muted" style="font-size:.75rem;">
                      Booking #<?= (int)$b['booking_id'] ?>
                    </div>
                  </div>

                  <div class="text-end">
                    <div style="font-size:.75rem;font-weight:600;letter-spacing:.8px;text-transform:uppercase;color:var(--muted);">Total Paid</div>
                    <div class="booking-total">&#8369;<?= number_format($b['total_price'], 2) ?></div>

                    <!-- Pay Now button if not yet paid -->
                    <?php if (!$isPaid && !$isConfirmed): ?>
                      <a href="cart.php" class="btn btn-sm btn-primary mt-2">
                        <i class="bi bi-credit-card me-1"></i>Complete Payment
                      </a>
                    <?php endif; ?>
                  </div>

                </div>
              </div><!-- /main info -->
            </div><!-- /top row -->

          </div><!-- /booking-card -->
        </div>
      <?php endforeach; ?>
    </div><!-- /row -->
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>