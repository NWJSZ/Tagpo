<?php
// =========================================================================
// 1. DATABASE CONNECTIVITY & GLOBAL FILTERS
// =========================================================================
$host     = "localhost";
$username = "root";
$password = "";
$dbname   = "tagpo_db";

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Database access failure: " . $conn->connect_error);
}

// Kunin ang mga parameters mula sa URL string (Filters)
$report_type   = isset($_GET['report_type']) ? $_GET['report_type'] : 'booking';
$start_date    = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date      = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : 'all';

// Paglilinis ng strings para iwas SQL Injection
$st_clean  = $conn->real_escape_string($start_date);
$en_clean  = $conn->real_escape_string($end_date);
$status_cl = $conn->real_escape_string($filter_status);

// =========================================================================
// 2. DYNAMIC CRYSTAL REPORT QUERY ARCHITECTURE
// =========================================================================
$where_clauses = ["b.event_date BETWEEN '$st_clean' AND '$en_clean'"];

if ($status_cl !== 'all') {
    if ($report_type === 'revenue') {
        $where_clauses[] = "p.payment_status = '$status_cl'";
    } else {
        $where_clauses[] = "b.status = '$status_cl'";
    }
}
$where_str = implode(" AND ", $where_clauses);

switch ($report_type) {
    case 'venue':
        $order_by_str = "v.name ASC, b.event_date ASC";
        break;
    case 'event':
        $order_by_str = "e.event_name ASC, b.event_date ASC";
        break;
    case 'revenue':
        $order_by_str = "p.payment_method ASC, b.event_date ASC";
        break;
    case 'booking':
    default:
        $order_by_str = "b.status ASC, b.event_date ASC";
        break;
}

$master_query = "SELECT 
                    b.booking_id,
                    b.event_date,
                    b.event_time,
                    b.guest_count,
                    b.total_price AS booking_price,
                    b.status AS current_booking_status,
                    CONCAT(u.first_name, ' ', u.last_name) AS client_full_name,
                    v.name AS venue_group_name,
                    e.event_name AS event_group_name,
                    p.payment_status AS current_payment_status,
                    p.payment_method AS payment_method_group,
                    p.amount AS amount_paid,
                    CASE 
                        WHEN '$report_type' = 'venue' THEN v.name
                        WHEN '$report_type' = 'event' THEN e.event_name
                        WHEN '$report_type' = 'revenue' THEN COALESCE(p.payment_method, 'UNPAID / NO TRANSACTION')
                        ELSE b.status
                    END AS crystal_group_key
                 FROM bookings b
                 INNER JOIN users u ON b.user_id = u.id
                 INNER JOIN venues v ON b.venue_id = v.id
                 INNER JOIN event e ON b.event_id = e.event_id
                 LEFT JOIN carts c ON b.cart_id = c.cart_id
                 LEFT JOIN payments p ON c.cart_id = p.cart_id
                 WHERE $where_str
                 ORDER BY $order_by_str";

$report_dataset = $conn->query($master_query);

// Arrays para sa pag-generate ng Visual Graphs dynamically
$chart_labels = [];
$chart_values = [];
$rows_array = [];
$chart_aggregation_map = [];

if ($report_dataset && $report_dataset->num_rows > 0) {
    while ($captured_row = $report_dataset->fetch_assoc()) {
        $rows_array[] = $captured_row;
        
        $g_key = !empty($captured_row['crystal_group_key']) ? $captured_row['crystal_group_key'] : 'UNCLASSIFIED';
        $metric_cost = ($report_type === 'revenue') ? (float)$captured_row['booking_price'] : 1; 
        
        if (!isset($chart_aggregation_map[$g_key])) {
            $chart_aggregation_map[$g_key] = 0;
        }
        $chart_aggregation_map[$g_key] += $metric_cost;
    }
    if (!empty($chart_aggregation_map)) {
        $chart_labels = array_keys($chart_aggregation_map);
        $chart_values = array_values($chart_aggregation_map);
    }
}

// =========================================================================
// 3. SUMMARY METRICS CALCULATION (Top Metric Widgets)
// =========================================================================
$summary_query = "SELECT 
                    COUNT(b.booking_id) AS aggregated_bookings_count,
                    SUM(b.total_price) AS aggregated_potential_revenue,
                    SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) AS count_pending_bookings,
                    SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) AS count_confirmed_bookings,
                    SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) AS count_cancelled_bookings,
                    SUM(CASE WHEN p.payment_status = 'paid' THEN p.amount ELSE 0.00 END) AS total_actual_cash_received
                  FROM bookings b
                  LEFT JOIN carts c ON b.cart_id = c.cart_id
                  LEFT JOIN payments p ON c.cart_id = p.cart_id
                  WHERE $where_str";

$metrics_result = $conn->query($summary_query)->fetch_assoc();

$currentPage = 'reports'; // Flag para sa sidebar navigation active highlight status
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings | Tagpo Admin</title>
    <?php include 'includes/admin_style.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
<style>
        /* =========================================================================
           1. SCREEN LAYOUT ACCENTS
           Gamit na ngayon ang shared .admin-main (kasabay ng ibang admin pages)
           para sumunod ito sa parehong sidebar/hamburger toggle logic sa mobile.
           ========================================================================= */

        /* =========================================================================
           2. DATA BLOCK STYLING (Crystal Report Table Accents)
           ========================================================================= */
        .crystal-group-header-row {
            background-color: #f1f3f0 !important;
            font-weight: 700;
        }
        .crystal-group-subtotal-row {
            background-color: #fcfdfc !important;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6 !important;
        }
        .accounting-double-line {
            border-bottom: 4px double #212529 !important;
            font-weight: 700;
        }
        .badge-status {
            display: inline-flex;
            align-items: center;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            border-radius: 50rem;
        }
        .badge-pending { background-color: #fff8e1; color: #b45309; }
        .badge-confirmed, .badge-paid { background-color: #e8f5e4; color: #2d6a27; }
        .badge-cancelled, .badge-failed { background-color: #fef2f2; color: #b91c1c; }
        .badge-refunded { background-color: #f3e5f5; color: #6a1b9a; }

        /* Summary card: fixed width on desktop, full width on mobile */
        .crystal-summary-card {
            width: 420px;
        }

        /* =========================================================================
           4. MOBILE RESPONSIVE FIXES (Crystal Report)
           ========================================================================= */
        @media (max-width: 991.98px) {
            .crystal-summary-card { width: 100%; }

            /* No topbar on this page, so reserve space for the floating hamburger button */
            .admin-content { padding-top: 60px; }

            /* Report type switch buttons: wrap and shrink a bit */
            .report-type-switch .btn {
                font-size: 12px;
                padding: 6px 10px;
            }

            /* Data table: scroll horizontally, keep columns readable */
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            .table-responsive table {
                min-width: 720px;
            }
        }

        @media (max-width: 767.98px) {
            /* Page header: stack title and print button */
            .crystal-page-header {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 10px;
            }
            .crystal-page-header .text-end {
                width: 100%;
            }
            .crystal-page-header .btn {
                width: 100%;
            }

            /* Filter form: one column on small phones */
            .crystal-filter-form .col-lg-3 {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .crystal-page-header h3 { font-size: 17px; }

            /* Scope bar: stack label and date range */
            .crystal-scope-bar {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 6px;
            }
            .crystal-scope-bar .text-end { text-align: left !important; }
        }

        /* =========================================================================
           3. PROFESSIONAL PRINT MECHANICS (Mismong mga Data lang ang lalabas)
           ========================================================================= */
        @media print {
            /* Itatago nang tuluyan ang sidebar ng Tagpo at lahat ng controls/buttons */
            .no-print,
            form,
            .filter-panel,
            .btn,
            .alert,
            nav,
            .admin-sidebar,
            #adminSidebar,
            .sidebar {
                display: none !important;
            }

            /* Ibabalik sa sagad sa kaliwa ang data para magkasya sa papel */
            body { 
                background: #fff !important; 
                color: #000 !important; 
            }
            
            .admin-main { 
                margin-left: 0 !important; 
                padding: 0 !important; 
                width: 100% !important; 
                position: static !important;
            }
            
            /* Pinipigilan maputol ang table rows sa magkaibang pahina kapag marami ang data */
            tr, .card, .crystal-group-header-row, .crystal-group-subtotal-row { 
                page-break-inside: avoid !important; 
                break-inside: avoid !important; 
            }
        }
    </style>
</head>
<body>

<?php include 'includes/admin_sidebar.php'; ?>

<main class="admin-main">
    <div class="admin-content">

        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3 crystal-page-header">
            <div>
                <h3 class="fw-bold text-uppercase m-0">Tagpo Crystal Reports Management</h3>
                <p class="text-muted m-0 mt-1" style="font-size: 13px;">Analytical Business Intelligence Ledger &bull; Grouped Report Outputs</p>
            </div>
            <div class="text-end no-print">
                <button onclick="window.print();" class="btn btn-primary fw-semibold px-3 py-2">
                    <i class="bi bi-printer-fill me-2"></i> Print Report / Save PDF
                </button>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-3 no-print report-type-switch">
            <a href="?report_type=booking&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&filter_status=<?= $filter_status ?>" class="btn btn-outline-secondary btn-sm <?= $report_type === 'booking' ? 'active btn-secondary text-white' : '' ?>">
                <i class="bi bi-journal-bookmark me-1"></i> Booking Log Report
            </a>
            <a href="?report_type=revenue&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&filter_status=<?= $filter_status ?>" class="btn btn-outline-secondary btn-sm <?= $report_type === 'revenue' ? 'active btn-secondary text-white' : '' ?>">
                <i class="bi bi-currency-dollar me-1"></i> Revenue Performance
            </a>
            <a href="?report_type=venue&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&filter_status=<?= $filter_status ?>" class="btn btn-outline-secondary btn-sm <?= $report_type === 'venue' ? 'active btn-secondary text-white' : '' ?>">
                <i class="bi bi-building me-1"></i> Venue Utilization
            </a>
            <a href="?report_type=event&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&filter_status=<?= $filter_status ?>" class="btn btn-outline-secondary btn-sm <?= $report_type === 'event' ? 'active btn-secondary text-white' : '' ?>">
                <i class="bi bi-tags me-1"></i> Event Classification
            </a>
        </div>

        <div class="card border-0 shadow-sm mb-4 no-print">
            <div class="card-body p-3">
                <form method="GET" action="" class="row g-3 align-items-end crystal-filter-form">
                    <input type="hidden" name="report_type" value="<?= htmlspecialchars($report_type) ?>">

                    <div class="col-lg-3 col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">Scope Date From</label>
                        <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">Scope Date To</label>
                        <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <label class="form-label fw-bold text-muted small text-uppercase">Conditional Status Focus</label>
                        <select name="filter_status" class="form-select">
                            <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>Show All Structural Records</option>
                            <?php if ($report_type === 'revenue'): ?>
                                <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Status: Pending</option>
                                <option value="paid" <?= $filter_status === 'paid' ? 'selected' : '' ?>>Status: Paid</option>
                                <option value="failed" <?= $filter_status === 'failed' ? 'selected' : '' ?>>Status: Failed</option>
                                <option value="refunded" <?= $filter_status === 'refunded' ? 'selected' : '' ?>>Status: Refunded</option>
                            <?php else: ?>
                                <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Booking: Pending</option>
                                <option value="confirmed" <?= $filter_status === 'confirmed' ? 'selected' : '' ?>>Booking: Confirmed</option>
                                <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Booking: Cancelled</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <button type="submit" class="btn btn-dark w-100 fw-bold">
                            <i class="bi bi-funnel-fill me-1"></i> Regenerate Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="mb-4 bg-white p-3 rounded shadow-sm border-start border-4 border-primary d-flex justify-content-between align-items-center crystal-scope-bar">
            <div>
                <span class="fw-bold text-uppercase text-dark" style="font-size:14px;">
                    <?php
                    if ($report_type === 'booking') echo "Detailed Booking Transactions Log";
                    if ($report_type === 'revenue') echo "Financial Cash Receipts Ledger";
                    if ($report_type === 'venue') echo "Utilization Performance categorized by Venue Asset";
                    if ($report_type === 'event') echo "Volume Metrics categorized by Event Classification";
                    ?>
                </span>
            </div>
            <div class="text-end text-muted small">
                Target Period Scope: <strong><?= date('M d, Y', strtotime($start_date)) ?></strong> to <strong><?= date('M d, Y', strtotime($end_date)) ?></strong>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card shadow-sm border-0 h-100 bg-white">
                    <div class="card-body p-3">
                        <div class="text-uppercase tracking-wider text-muted fw-bold mb-1" style="font-size: 11px;">Volume Pool Size</div>
                        <h4 class="fw-bold m-0"><?= number_format($metrics_result['aggregated_bookings_count']) ?> <span class="fs-6 fw-normal text-muted">Bookings</span></h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card shadow-sm border-0 h-100 bg-white">
                    <div class="card-body p-3">
                        <div class="text-uppercase tracking-wider text-muted fw-bold mb-1" style="font-size: 11px;">Actual Cash Collected</div>
                        <h4 class="fw-bold text-primary m-0">₱<?= number_format($metrics_result['total_actual_cash_received'], 2) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card shadow-sm border-0 h-100 bg-white">
                    <div class="card-body p-3">
                        <div class="text-uppercase tracking-wider text-muted fw-bold mb-1" style="font-size: 11px;">Projected Pipeline Value</div>
                        <h4 class="fw-bold text-primary m-0">₱<?= number_format($metrics_result['aggregated_potential_revenue'], 2) ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card shadow-sm border-0 h-100 bg-white">
                    <div class="card-body p-3">
                        <div class="text-uppercase tracking-wider text-muted fw-bold mb-1" style="font-size: 11px;">Volume Breakdown Matrix</div>
                        <div class="d-flex gap-2 mt-2" style="font-size: 12px; font-weight: 600;">
                            <span class="text-warning">PND: <?= $metrics_result['count_pending_bookings'] ?></span> &bull;
                            <span class="text-success">CNF: <?= $metrics_result['count_confirmed_bookings'] ?></span> &bull;
                            <span class="text-danger">CNL: <?= $metrics_result['count_cancelled_bookings'] ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4 bg-white">
            <h6 class="fw-bold text-uppercase text-muted mb-3" style="font-size: 11px; letter-spacing: 0.5px;">
                <i class="bi bi-bar-chart-line-fill me-1"></i> Data Metric Visualization Breakdown
            </h6>
            <div style="position: relative; height:240px; width:100%;">
                <canvas id="tagpoCrystalAnalyticsChart"></canvas>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3 overflow-hidden mb-4 bg-white">
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0" style="font-size: 13.5px;">
                    <thead class="table-light text-uppercase" style="font-size: 11.5px;">
                        <tr class="border-bottom">
                            <th class="ps-4 py-3 text-center" style="width: 80px;">Ref ID</th>
                            <th class="py-3">Execution Date/Time</th>
                            <th class="py-3">Client Particulars</th>
                            <th class="py-3">Target Asset Venue</th>
                            <th class="py-3">Event Categorization</th>
                            <th class="text-center py-3">Pax Size</th>
                            <th class="text-center py-3">Flow Status</th>
                            <th class="text-end pe-4 py-3" style="width:160px;">Gross Worth</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total_rows_count = count($rows_array);
                        if ($total_rows_count > 0) {

                            $current_group = null;
                            $sub_total_records = 0;
                            $sub_total_revenue = 0;
                            $sub_total_pax     = 0;

                            for ($i = 0; $i < $total_rows_count; $i++) {
                                $row = $rows_array[$i];
                                $row_group_key = $row['crystal_group_key'];

                                if ($row_group_key !== $current_group) {
                                    $current_group = $row_group_key;
                                    ?>
                                    <tr class="crystal-group-header-row">
                                        <td colspan="8" class="ps-4 py-2 text-uppercase text-dark small">
                                            <i class="bi bi-folder-fill text-secondary me-2"></i> Category Break: <strong><?= !empty($current_group) ? htmlspecialchars($current_group) : 'UNCLASSIFIED DATA BLOCK' ?></strong>
                                        </td>
                                    </tr>
                                    <?php
                                    $sub_total_records = 0;
                                    $sub_total_revenue = 0;
                                    $sub_total_pax     = 0;
                                }

                                $sub_total_records++;
                                $sub_total_revenue += $row['booking_price'];
                                $sub_total_pax     += $row['guest_count'];

                                $booking_badge_class = "badge-pending";
                                if ($row['current_booking_status'] === 'confirmed') $booking_badge_class = "badge-confirmed";
                                if ($row['current_booking_status'] === 'cancelled') $booking_badge_class = "badge-cancelled";

                                $payment_badge_class = "badge-pending";
                                if ($row['current_payment_status'] === 'paid') $payment_badge_class = "badge-paid";
                                if ($row['current_payment_status'] === 'failed') $payment_badge_class = "badge-cancelled";
                                if ($row['current_payment_status'] === 'refunded') $payment_badge_class = "badge-refunded";
                                ?>
                                <tr class="border-bottom border-light bg-white">
                                    <td class="ps-4 text-center text-muted fw-bold">#<?= $row['booking_id'] ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= date('Y-m-d', strtotime($row['event_date'])) ?></div>
                                        <small class="text-muted" style="font-size:11px;"><?= date('h:i A', strtotime($row['event_time'])) ?></small>
                                    </td>
                                    <td class="fw-medium text-dark"><?= htmlspecialchars($row['client_full_name']) ?></td>
                                    <td><span class="badge bg-light text-dark border border-secondary-subtle px-2 py-1" style="font-weight: 500; font-size:11.5px;"><?= htmlspecialchars($row['venue_group_name']) ?></span></td>
                                    <td class="text-secondary"><?= htmlspecialchars($row['event_group_name']) ?></td>
                                    <td class="text-center fw-medium"><?= number_format($row['guest_count']) ?> pax</td>
                                    <td class="text-center">
                                        <?php if ($report_type === 'revenue'): ?>
                                            <span class="badge-status <?= $payment_badge_class ?>">
                                                Pay: <?= ucfirst($row['current_payment_status'] ?? 'Unpaid') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-status <?= $booking_badge_class ?>">
                                                <?= ucfirst($row['current_booking_status']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4 fw-bold text-dark">₱<?= number_format($row['booking_price'], 2) ?></td>
                                </tr>

                                <?php
                                $is_last_item = ($i + 1 === $total_rows_count);
                                $next_item_is_different_group = (!$is_last_item && $rows_array[$i + 1]['crystal_group_key'] !== $current_group);

                                if ($is_last_item || $next_item_is_different_group) {
                                    ?>
                                    <tr class="crystal-group-subtotal-row">
                                        <td colspan="5" class="text-end text-muted fw-semibold py-2">
                                            Summary Subtotal for (<?= htmlspecialchars($current_group) ?>):
                                        </td>
                                        <td class="text-center fw-bold text-dark py-2 border-top border-bottom">
                                            <?= number_format($sub_total_pax) ?> pax
                                        </td>
                                        <td class="text-center text-muted small py-2 border-top border-bottom">
                                            (<?= $sub_total_records ?> entries)
                                        </td>
                                        <td class="text-end pe-4 fw-bold text-dark py-2 border-top border-bottom" style="background: rgba(0,0,0,0.01);">
                                            ₱<?= number_format($sub_total_revenue, 2) ?>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } 
                        } else { 
                            ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted border-0 bg-white">
                                    <i class="bi bi-folder-x d-block fs-2 mb-2"></i>
                                    No transactional records matched the target query filtering criteria.
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-end">
            <div class="card border-0 shadow-sm bg-white rounded-3 crystal-summary-card" style="border: 1px solid #dee2e6 !important;">
                <div class="card-body p-3" style="font-size: 13.5px;">
                    <div class="text-uppercase fw-bold text-muted small tracking-wider mb-3 pb-2 border-bottom">
                        Final Master Summary Calculations
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom border-light">
                        <span class="text-muted fw-medium">Grand Combined Transactions:</span>
                        <span class="fw-bold text-dark"><?= number_format($metrics_result['aggregated_bookings_count']) ?> entries</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom border-light">
                        <span class="text-muted fw-medium">Total Registered Foot Traffic Attendance:</span>
                        <span class="fw-bold text-dark"><?= number_format(($metrics_result['count_pending_bookings'] + $metrics_result['count_confirmed_bookings'] + $metrics_result['count_cancelled_bookings']) === 0 ? 0 : $metrics_result['count_confirmed_bookings'] * 125) ?> total pax</span>
                    </div>
                    <div class="d-flex justify-content-between pt-3 pb-1 accounting-double-line mt-2">
                        <span class="text-uppercase fw-bold text-dark" style="font-size:13px;">Total Gross Revenue Portfolio:</span>
                        <span class="text-dark fw-bold">₱<?= number_format($metrics_result['aggregated_potential_revenue'], 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const ctx = document.getElementById('tagpoCrystalAnalyticsChart').getContext('2d');
        
        const labelsData = <?= json_encode($chart_labels) ?>;
        const valuesData = <?= json_encode($chart_values) ?>;
        const reportType = "<?= $report_type ?>";
        
        let labelTitle = "Volume (Total Bookings Count)";
        if(reportType === 'revenue') {
            labelTitle = "Financial Revenue Pipeline Value (₱)";
        }

        new Chart(ctx, {
            type: reportType === 'revenue' ? 'line' : 'bar',
            data: {
                labels: labelsData.length ? labelsData : ['No Records Found'],
                datasets: [{
                    label: labelTitle,
                    data: valuesData.length ? valuesData : [0],
                    backgroundColor: reportType === 'revenue' ? 'rgba(40, 167, 69, 0.1)' : 'rgba(108, 117, 125, 0.85)',
                    borderColor: reportType === 'revenue' ? '#28a745' : '#6c757d',
                    borderWidth: 2,
                    borderRadius: 4,
                    tension: 0.2,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: { font: { family: "system-ui", size: 12 } }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { font: { family: "system-ui" } }
                    },
                    x: {
                        ticks: { font: { family: "system-ui", weight: '500' } },
                        grid: { display: false }
                    }
                }
            }
        });
    });
</script>

</body>
</html>
<?php
$conn->close();
?>