<?php
require_once 'config/database.php';
require_once 'config/session_config.php';
require_once 'config/app.php';

$baseUrl = getBaseUrl();

// Update activity
$_SESSION['last_activity'] = time();

// Refresh cookie
if (isset($_SESSION['current_user'])) {
    setcookie('user_session', $_SESSION['current_user']['email'], time() + (60 * 60 * 24 * 7), '/');
}

// All-in-One Logic para sa pagbura ng item
if (isset($_GET['action']) && $_GET['action'] === 'remove' && isset($_GET['id'])) {
    $indexToRemove = (int)$_GET['id'];

    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        if (isset($_SESSION['cart'][$indexToRemove])) {
            unset($_SESSION['cart'][$indexToRemove]);
            $_SESSION['cart'] = array_values($_SESSION['cart']); 
        }
    }
    header("Location: cart.php");
    exit();
}

$cart = $_SESSION['cart'] ?? [];

// DITO NATIN PROPROSESO YUNG BACK TO LAST VENUE LOGIC:
$backToVenueUrl = $_SESSION['last_venue_visited'] ?? 'search.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Your Booking Cart | Tagpo</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/styles.css">
  <link rel="stylesheet" href="assets/css/cart.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    /* ==========================================
       TAGPO PREMIUM LIVE SELECTION CUSTOM UI
       ========================================== */
    
    /* Elegant and Minimalist Back Button Style */
    .btn-back-navigation {
      display: inline-flex;
      align-items: center;
      background: transparent;
      border: 1px solid rgba(0, 0, 0, 0.1);
      padding: 6px 14px;
      border-radius: 30px;
      color: #4a5568;
      font-size: 0.85rem;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .btn-back-navigation i {
      transition: transform 0.2s ease;
    }
    .btn-back-navigation:hover {
      background-color: #f8fafc;
      border-color: var(--gold, #b7791f);
      color: var(--gold, #b7791f);
      box-shadow: 0 2px 5px rgba(0,0,0,0.03);
    }
    .btn-back-navigation:hover i {
      transform: translateX(-3px);
    }

    /* Select All Bar Style */
    .select-all-bar {
      background: #ffffff;
      border: 1px solid rgba(0, 0, 0, 0.08);
      border-radius: 12px;
      padding: 14px 20px;
      margin-bottom: 16px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.02);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    /* Professional Custom Checkbox Container */
    .custom-chk-container {
      display: block;
      position: relative;
      padding-left: 32px;
      cursor: pointer;
      font-size: 0.95rem;
      font-weight: 500;
      color: #343a40;
      user-select: none;
    }

    /* Itago ang default browser checkbox */
    .custom-chk-container input {
      position: absolute;
      opacity: 0;
      cursor: pointer;
      height: 0;
      width: 0;
    }

    /* Ang pasadyang modern checkbox box */
    .checkmark {
      position: absolute;
      top: 1px;
      left: 0;
      height: 20px;
      width: 20px;
      background-color: #fff;
      border: 2px solid #ced4da;
      border-radius: 6px;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Kapag naka-hover */
    .custom-chk-container:hover input ~ .checkmark {
      border-color: #1e40af;
    }

    /* Kapag naka-checked na (Premium Brand Blue fill) */
    .custom-chk-container input:checked ~ .checkmark {
      background-color: #1e40af;
      border-color: #1e40af;
      box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.15);
    }

    /* Ang check icon mark indicator */
    .checkmark:after {
      content: "";
      position: absolute;
      display: none;
    }

    /* Ipakita kapag naka-checked */
    .custom-chk-container input:checked ~ .checkmark:after {
      display: block;
    }

    /* Porma ng check mark indicator icon */
    .custom-chk-container .checkmark:after {
      left: 6px;
      top: 2px;
      width: 5px;
      height: 10px;
      border: solid white;
      border-width: 0 2px 2px 0;
      transform: rotate(45deg);
    }

    /* Smooth Visual Cards Transition */
    .cart-item-card {
      position: relative;
      border: 1px solid rgba(0, 0, 0, 0.06) !important;
      border-radius: 14px !important;
      background: #ffffff;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
      overflow: hidden;
    }
    
    /* Kapag naka-select ang card, bigyan ng subtle premium glow outline */
    .cart-item-card.selected-active {
      border-color: rgba(30, 64, 175, 0.25) !important;
      box-shadow: 0 4px 20px rgba(30, 64, 175, 0.04);
    }

    /* Kapag hindi naka-select (Elegant gray fade out) */
    .cart-item-card.unselected-fade {
      opacity: 0.55;
      background-color: #fcfcfc;
      transform: scale(0.995);
    }

    .cart-checkbox-aside {
      display: flex;
      align-items: flex-start;
      padding-top: 4px;
    }

    /* Premium Look Modal Style */
    .tagpo-modal .modal-content { border: none; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
    .tagpo-modal .modal-header { border-bottom: none; padding-top: 24px; }
    .tagpo-modal .modal-footer { border-top: none; padding-bottom: 24px; }
    .btn-cancel { background: #f1f3f5; color: #495057; border: none; border-radius: 8px; padding: 10px 20px; font-weight: 600; transition: all 0.2s; }
    .btn-cancel:hover { background: #e9ecef; color: #212529; }
    .btn-confirm-delete { background: #dc3545; color: #fff; border: none; border-radius: 8px; padding: 10px 20px; font-weight: 600; transition: all 0.2s; }
    .btn-confirm-delete:hover { background: #bd2130; box-shadow: 0 4px 12px rgba(220, 53, 69, 0.2); }
  </style>
</head>

<body>

  <?php include 'includes/header.php'; ?>

  <!-- Breadcrumbs at ang Naka-fix na Back Button papunta sa Tiyak na huling Venue Page -->
  <div class="breadcrumb-bar">
    <div class="container d-flex justify-content-between align-items-center">
      <div>
        <a href="index.php">Home</a> / <span class="text-muted">Booking Cart</span>
      </div>
      <a href="<?php echo htmlspecialchars($backToVenueUrl); ?>" class="btn-back-navigation" aria-label="Go back to the last venue page">
        <i class="fa-solid fa-arrow-left me-2"></i> Back to Venue
      </a>
    </div>
  </div>

  <div class="container mt-5 mb-5">
    <div class="row">
      <div class="col-12 mb-4">
        <span class="section-eyebrow">Reservations</span>
        <h2 class="section-heading">🛒 Your Booking Cart</h2>
        <p class="section-sub">Review your selected venues and event details before confirming.</p>
      </div>

      <?php if (empty($cart)): ?>
        <div class="col-12 text-center py-5">
          <div class="mb-4" style="font-size: 4rem; opacity: 0.3;">📂</div>
          <h3 class="h4">Your cart is currently empty.</h3>
          <p class="text-muted mb-4">It looks like you haven't picked a venue yet.</p>
          <a href="search.php" class="btn-book">Browse Venues</a>
        </div>
      <?php else: ?>

        <!-- Master Form linked to payment.php -->
        <form action="payment.php" method="POST" id="cart-checkout-form" class="row">
          
          <div class="col-lg-8">
            
            <!-- SELECTION HEADER BAR: SELECT ALL SECTOR -->
            <div class="select-all-bar fade-up">
              <label class="custom-chk-container mb-0">
                Select All Bookings
                <input type="checkbox" id="master-select-checkbox" checked>
                <span class="checkmark"></span>
              </label>
              <span class="text-muted small fw-medium" id="selected-items-counter">0 item(s) selected</span>
            </div>

            <?php foreach ($cart as $index => $item): 
              $addonsTotal = 0;
              $eventType = $item['event_type'] ?? $item['event_id'] ?? '';
              if (!empty($item['addons'])):
                foreach ($item['addons'] as $addon):
                  $addonPrice = 0;
                  if ($eventType === 'Wedding') {
                      if ($addon === "Catering Service") $addonPrice = 8000;
                      elseif ($addon === "Bridal Car") $addonPrice = 3500;
                      elseif ($addon === "Floral Arrangement Package") $addonPrice = 2500;
                      elseif ($addon === "Wedding Stage Decoration") $addonPrice = 4000;
                      elseif ($addon === "Photo Booth") $addonPrice = 2500;
                  } elseif ($eventType === 'Birthday / Debut') {
                      if ($addon === "Catering Service") $addonPrice = 6000;
                      elseif ($addon === "Balloon & Themed Setup") $addonPrice = 2000;
                      elseif ($addon === "Photo Booth") $addonPrice = 2500;
                      elseif ($addon === "Clown / Event Host") $addonPrice = 1500;
                      elseif ($addon === "Cake Styling Setup") $addonPrice = 1000;
                  } elseif ($eventType === 'Prom / Ball') {
                      if ($addon === "DJ Booth") $addonPrice = 3000;
                      elseif ($addon === "LED Lights Setup") $addonPrice = 2500;
                      elseif ($addon === "Red Carpet Entrance Setup") $addonPrice = 1500;
                      elseif ($addon === "Photo Booth") $addonPrice = 2500;
                      elseif ($addon === "Emcee / Host") $addonPrice = 2000;
                  } elseif ($eventType === 'Corporate Event') {
                      if ($addon === "Projector & Screen Setup") $addonPrice = 2000;
                      elseif ($addon === "Sound System") $addonPrice = 3000;
                      elseif ($addon === "Microphones & Stage Setup") $addonPrice = 2500;
                      elseif ($addon === "Coffee Break Catering") $addonPrice = 5000;
                      elseif ($addon === "LED Display Wall") $addonPrice = 8000;
                  } elseif ($eventType === 'Reunion') {
                      if ($addon === "Buffet Catering") $addonPrice = 7000;
                      elseif ($addon === "Photo Booth") $addonPrice = 2500;
                      elseif ($addon === "Memory Slideshow / Projector") $addonPrice = 1500;
                      elseif ($addon === "Event Host / Emcee") $addonPrice = 2000;
                  } elseif ($eventType === 'Anniversary') {
                      if ($addon === "Romantic Venue Styling") $addonPrice = 3000;
                      elseif ($addon === "Floral Arrangement Package") $addonPrice = 2000;
                      elseif ($addon === "Candle & Lights Setup") $addonPrice = 1500;
                      elseif ($addon === "Live Acoustic Music") $addonPrice = 5000;
                  }
                  $addonsTotal += $addonPrice;
                endforeach;
              endif;

              // Prefer the authoritative total stored in session (calculated at add-to-cart),
              // which already includes add-ons. Fallback to recomputing if not present.
              $venuePrice = $item['venue_price'] ?? 35000;
              if (isset($item['total_price'])) {
                  $itemTotal = (float) $item['total_price'];
              } else {
                  $itemTotal = $venuePrice + $addonsTotal;
              }
            ?>
              <!-- Row Item Card -->
              <div class="cart-item-card p-4 mb-3 fade-up d-flex gap-2" id="card-<?php echo $index; ?>">
                
                <!-- CUSTOM CHECKBOX SELECTION -->
                <div class="cart-checkbox-aside pr-2">
                  <label class="custom-chk-container">
                    <input type="checkbox" 
                           name="selected_items[]" 
                           value="<?php echo $index; ?>" 
                           class="cart-item-checkbox" 
                           data-price="<?php echo $itemTotal; ?>" 
                           checked>
                    <span class="checkmark"></span>
                  </label>
                </div>

                <div class="d-md-flex gap-4 w-100">
                  <div class="item-image-sm flex-shrink-0 mb-3 mb-md-0">
                    <i class="fa-solid fa-hotel fa-2x"></i>
                  </div>

                  <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                      <h5 class="mb-1 fw-bold" style="color: #2d3748;"><?php echo htmlspecialchars($item['venue_name']); ?></h5>
                      
                      <button type="button" 
                              class="btn text-danger text-decoration-none small p-0 remove-btn-trigger" 
                              data-bs-toggle="modal" 
                              data-bs-target="#confirmDeleteModal" 
                              data-index="<?php echo $index; ?>"
                              data-venuename="<?php echo htmlspecialchars($item['venue_name']); ?>">
                        <i class="fa-solid fa-trash-can me-1"></i> Remove
                      </button>
                    </div>

                    <p class="mb-2 text-muted small">
                      <i class="fa-solid fa-calendar-day me-1"></i> <?php echo htmlspecialchars($item['event_date']); ?>
                      <span class="mx-2" style="color: #e2e8f0;">|</span>
                      <i class="fa-solid fa-star me-1"></i> <?php echo htmlspecialchars($item['event_type'] ?? $item['event_id']); ?>
                    </p>

                    <div class="mb-3">
                      <span class="d-block small fw-bold text-uppercase tracking-wider mb-2" style="font-size: 0.65rem; color: var(--gold); font-weight:700;">Add-ons Included:</span>
                      <?php
                      if (!empty($item['addons'])):
                        foreach ($item['addons'] as $addon):
                          echo '<span class="addon-badge">' . htmlspecialchars($addon) . '</span> ';
                        endforeach;
                      else:
                        echo '<span class="text-muted small">Standard Package (No Add-ons)</span>';
                      endif;
                      ?>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top" style="border-top-color: #f7fafc !important;">
                      <span class="text-muted small">Venue + Extras</span>
                      <span class="fw-bold text-deep" style="font-size: 1.05rem;">₱<?php echo number_format($itemTotal); ?></span>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- ORDER SUMMARY SIDEBAR -->
          <div class="col-lg-4 mt-4 mt-lg-0">
            <div class="summary-card shadow-sm position-sticky" style="top: 20px; border-radius: 14px;">
              <h5 class="mb-4 fw-bold" style="color: #2d3748;">Order Summary</h5>
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Subtotal</span>
                <span id="summary-subtotal" class="fw-medium">₱0</span>
              </div>
              <div class="d-flex justify-content-between mb-3">
                <span class="text-muted">Service Fee</span>
                <span class="text-success fw-bold">FREE</span>
              </div>
              <hr style="opacity: 0.08;">
              <div class="d-flex justify-content-between align-items-center mb-4">
                <span class="h6 mb-0 fw-bold" style="color: #2d3748;">Total Amount</span>
                <span class="h4 mb-0 text-deep fw-bold" id="summary-total" style="font-weight: 800;">₱0</span>
              </div>

              <button type="submit" id="btn-proceed" class="btn-book w-100 text-center py-3" style="border-radius: 10px; font-weight: 600;">
                Proceed to Payment <i class="fa-solid fa-arrow-right ms-2"></i>
              </button>

              <div class="mt-4 p-3 bg-surface rounded" style="border: 1px dashed var(--border);">
                <p class="small text-muted mb-0">
                  <i class="fa-solid fa-shield-halved text-gold me-2"></i>
                  Secure booking guaranteed by <strong>Tagpo</strong>.
                </p>
              </div>
            </div>
          </div>

        </form>

      <?php endif; ?>
    </div>
  </div>

  <!-- CONFIRM ACTION MODAL DIALOG -->
  <div class="modal fade tagpo-modal" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header d-flex justify-content-center text-center">
          <div class="text-danger mb-2" style="font-size: 3rem;">
            <i class="bi bi-exclamation-circle-fill"></i>
          </div>
        </div>
        <div class="modal-body text-center pt-0 px-4">
          <h4 class="fw-bold mb-2 text-dark">Remove Booking?</h4>
          <p class="text-muted mb-0">Are you sure you want to remove this booking from your cart?</p>
          <p class="fw-bold text-secondary mt-2" id="target-venue-display"></p>
        </div>
        <div class="modal-footer d-flex justify-content-center gap-2">
          <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
          <a href="#" id="final-delete-btn" class="btn-confirm-delete text-decoration-none text-center">Yes, Remove</a>
        </div>
      </div>
    </div>
  </div>

  <?php include 'includes/footer.php'; ?>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      
      const masterCheckbox = document.getElementById('master-select-checkbox');
      const checkboxes = document.querySelectorAll('.cart-item-checkbox');
      const subtotalDisplay = document.getElementById('summary-subtotal');
      const totalDisplay = document.getElementById('summary-total');
      const counterDisplay = document.getElementById('selected-items-counter');
      const proceedBtn = document.getElementById('btn-proceed');

      function calculateTotals() {
        let currentTotal = 0;
        let checkedCount = 0;

        checkboxes.forEach(checkbox => {
          const cardId = 'card-' + checkbox.value;
          const parentCard = document.getElementById(cardId);

          if (checkbox.checked) {
            currentTotal += parseFloat(checkbox.getAttribute('data-price'));
            checkedCount++;
            if(parentCard) {
              parentCard.classList.add('selected-active');
              parentCard.classList.remove('unselected-fade');
            }
          } else {
            if(parentCard) {
              parentCard.classList.remove('selected-active');
              parentCard.classList.add('unselected-fade');
            }
          }
        });

        const formattedPrice = '₱' + currentTotal.toLocaleString('en-US');
        subtotalDisplay.textContent = formattedPrice;
        totalDisplay.textContent = formattedPrice;
        if(counterDisplay) {
          counterDisplay.textContent = `${checkedCount} item(s) selected`;
        }

        if (checkedCount === 0) {
          proceedBtn.disabled = true;
          proceedBtn.style.opacity = "0.4";
          proceedBtn.style.cursor = "not-allowed";
        } else {
          proceedBtn.disabled = false;
          proceedBtn.style.opacity = "1";
          proceedBtn.style.cursor = "pointer";
        }

        if(masterCheckbox) {
          if (checkedCount === 0) {
            masterCheckbox.checked = false;
            masterCheckbox.indeterminate = false;
          } else if (checkedCount === checkboxes.length) {
            masterCheckbox.checked = true;
            masterCheckbox.indeterminate = false;
          } else {
            masterCheckbox.checked = false;
            masterCheckbox.indeterminate = true;
          }
        }
      }

      if(masterCheckbox) {
        masterCheckbox.addEventListener('change', function() {
          const state = this.checked;
          checkboxes.forEach(checkbox => {
            checkbox.checked = state;
          });
          calculateTotals();
        });
      }

      checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', calculateTotals);
      });

      calculateTotals();

      const confirmModal = document.getElementById('confirmDeleteModal');
      if (confirmModal) {
        confirmModal.addEventListener('show.bs.modal', function (event) {
          const button = event.relatedTarget;
          const index = button.getAttribute('data-index');
          const venueName = button.getAttribute('data-venuename');
          
          const display = confirmModal.querySelector('#target-venue-display');
          if(display) display.textContent = `"${venueName}"`;
          
          const deleteAnchor = confirmModal.querySelector('#final-delete-btn');
          deleteAnchor.setAttribute('href', `cart.php?action=remove&id=${index}`);
        });
      }
    });
  </script>
</body>

</html>