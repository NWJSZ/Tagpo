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
// Base WHERE conditional structure
$where_clauses = ["b.event_date BETWEEN '$st_clean' AND '$en_clean'"];

if ($status_cl !== 'all') {
    if ($report_type === 'revenue') {
        // Kapag revenue report, ang payment_status ang ginagawang basehan
        $where_clauses[] = "p.payment_status = '$status_cl'";
    } else {
        // Kapag regular o profile tracking, ang booking status ang kinukuha
        $where_clauses[] = "b.status = '$status_cl'";
    }
}
$where_str = implode(" AND ", $where_clauses);

// Pag-set ng sorting parameters base sa napiling Crystal Report grouping view
switch ($report_type) {
    case 'venue':
        $group_field = "venue_group_name";
        $order_by_str = "v.name ASC, b.event_date ASC";
        break;
    case 'event':
        $group_field = "event_group_name";
        $order_by_str = "e.event_name ASC, b.event_date ASC";
        break;
    case 'revenue':
        $group_field = "payment_method_group";
        $order_by_str = "p.payment_method ASC, b.event_date ASC";
        break;
    case 'booking':
    default:
        $group_field = "booking_status_group";
        $order_by_str = "b.status ASC, b.event_date ASC";
        break;
}

// Master execution processing query string (Naka-JOIN sa lahat ng kinakailangang tables)
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
                    -- Gagawa ng standardized categorical label para sa grouping breaks
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

// =========================================================================
// 3. SUMMARY METRICS CALCULATION (Para sa Top Metric Widgets)
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tagpo Executive Crystal Report Interface</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        /* =========================================================================
           TAGPO THEME ENGINE DESIGN PRINCIPLES
           ========================================================================= */
        :root {
          --sidebar-w: 248px;
          --topbar-h: 64px;
          --green:      #3d7a3a;
          --green-lt:   #e8f5e4;
          --green-md:   #5a9e55;
          --olive:      #4a5c2f;
          --surface:    #ffffff;
          --bg:         #f5f6f4;
          --border:     #e5e7e2;
          --text:       #1a1f17;
          --muted:      #6b7060;
          --danger:     #dc2626;
          --warning:    #d97706;
          --info:       #0ea5e9;
          --radius:     10px;
          --radius-lg:  14px;
          --shadow:     0 2px 8px rgba(0,0,0,.05);
          --transition: .18s ease;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
            min-height: 100vh;
        }

        /* Interactive Filter Layout */
        .filter-panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }

        .report-nav-btn {
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            border: 1px solid var(--border);
            background: var(--surface);
            padding: 8px 16px;
            border-radius: 8px;
            transition: all var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .report-nav-btn:hover {
            background: var(--bg);
            color: var(--text);
        }
        .report-nav-btn.active {
            background: var(--green-lt);
            color: var(--green);
            border-color: var(--green-md);
        }

        /* Metric Summary Blocks */
        .summary-metric-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow);
        }
        .metric-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .metric-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
            line-height: 1.1;
        }

        /* Crystal Grouping Header & Section Styling */
        .crystal-group-header-row {
            background: #eaece6 !important;
            font-weight: 700;
            color: var(--olive);
            font-size: 13px;
            letter-spacing: .3px;
        }
        .crystal-group-subtotal-row {
            background: #fcfdfc !important;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid var(--border) !important;
        }

        /* Signature Dynamic Badges Matrix */
        .badge-status {
          display: inline-flex;
          align-items: center;
          gap: 5px;
          padding: 4px 10px;
          border-radius: 20px;
          font-size: 12px;
          font-weight: 600;
        }
        .badge-status::before { content: ''; width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
        
        .badge-pending    { background: #fff8e1; color: #b45309; }
        .badge-pending::before   { background: #d97706; }
        .badge-confirmed, .badge-paid  { background: #e8f5e4; color: #2d6a27; }
        .badge-confirmed::before, .badge-paid::before { background: #3d7a3a; }
        .badge-cancelled, .badge-failed { background: #fef2f2; color: #b91c1c; }
        .badge-cancelled::before, .badge-failed::before { background: #dc2626; }
        .badge-refunded   { background: #f3e5f5; color: #6a1b9a; }
        .badge-refunded::before  { background: #6a1b9a; }

        .accounting-double-line {
            border-bottom: 4px double var(--text) !important;
            font-weight: 700;
            font-size: 16px;
        }

        /* =========================================================================
           CROSS-PLATFORM PDF GENERATION AND PRINT STYLESHEET
           ========================================================================= */
        @media print {
            body {
                background: #fff !important;
                color: #000 !important;
                font-size: 12px;
            }
            .no-print, form, .filter-panel, .btn, .alert {
                display: none !important;
            }
            .summary-metric-card {
                border: 1px solid #000 !important;
                box-shadow: none !important;
                background: #fff !important;
                page-break-inside: avoid;
            }
            .crystal-group-header-row {
                background: #f0f0f0 !important;
                color: #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .table th {
                background: #e5e7e2 !important;
                color: #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .badge-status {
                border: 1px solid #aaa !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .container-fluid {
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            tr {
                page-break-inside: avoid !important;
            }
        }
    </style>
</head>
<body class="py-4">

<div class="container-fluid px-4" style="max-width: 1500px;">

    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3 border-2 border-dark">
        <div>
            <h3 class="fw-bold text-uppercase m-0 tracking-wide" style="color: var(--text);">Tagpo Executive Management System</h3>
            <p class="text-muted m-0 mt-1" style="font-size: 13px;">Analytical Business Intelligence Ledger &bull; Grouped Report Outputs</p>
        </div>
        <div class="text-end no-print">
            <button onclick="window.print();" class="btn text-white fw-semibold px-3 py-2" style="background: var(--green); font-size:13px; border-radius:8px;">
                <i class="bi bi-printer-fill me-2"></i> Print Report / Save PDF
            </button>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3 no-print">
        <a href="?report_type=booking&start_date=<?=$start_date?>&end_date=<?=$end_date?>&filter_status=<?=$filter_status?>" class="report-nav-btn <?=$report_type==='booking'?'active':''?>">
            <i class="bi bi-journal-bookmark"></i> Booking Log Report
        </a>
        <a href="?report_type=revenue&start_date=<?=$start_date?>&end_date=<?=$end_date?>&filter_status=<?=$filter_status?>" class="report-nav-btn <?=$report_type==='revenue'?'active':''?>">
            <i class="bi bi-currency-dollar"></i> Revenue Performance
        </a>
        <a href="?report_type=venue&start_date=<?=$start_date?>&end_date=<?=$end_date?>&filter_status=<?=$filter_status?>" class="report-nav-btn <?=$report_type==='venue'?'active':''?>">
            <i class="bi bi-building-geom"></i> Venue Utilization
        </a>
        <a href="?report_type=event&start_date=<?=$start_date?>&end_date=<?=$end_date?>&filter_status=<?=$filter_status?>" class="report-nav-btn <?=$report_type==='event'?'active':''?>">
            <i class="bi bi-tags"></i> Event Classification
        </a>
    </div>

    <div class="card filter-panel border-0 mb-4 no-print">
        <div class="card-body p-3">
            <form method="GET" action="" class="row g-3 align-items-end">
                <input type="hidden" name="report_type" value="<?=htmlspecialchars($report_type)?>">
                
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold text-muted small text-uppercase">Scope Date From</label>
                    <input type="date" name="start_date" class="form-control border-secondary-subtle" value="<?=$start_date?>">
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold text-muted small text-uppercase">Scope Date To</label>
                    <input type="date" name="end_date" class="form-control border-secondary-subtle" value="<?=$end_date?>">
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <label class="form-label fw-bold text-muted small text-uppercase">Conditional Status Focus</label>
                    <select name="filter_status" class="form-select border-secondary-subtle">
                        <option value="all" <?=$filter_status==='all'?'selected':''?>>Show All Structural Records</option>
                        <?php if($report_type === 'revenue'): ?>
                            <option value="pending" <?=$filter_status==='pending'?'selected':''?>>Status: Pending</option>
                            <option value="paid" <?=$filter_status==='paid'?'selected':''?>>Status: Paid</option>
                            <option value="failed" <?=$filter_status==='failed'?'selected':''?>>Status: Failed</option>
                            <option value="refunded" <?=$filter_status==='refunded'?'selected':''?>>Status: Refunded</option>
                        <?php else: ?>
                            <option value="pending" <?=$filter_status==='pending'?'selected':''?>>Booking: Pending</option>
                            <option value="confirmed" <?=$filter_status==='confirmed'?'selected':''?>>Booking: Confirmed</option>
                            <option value="cancelled" <?=$filter_status==='cancelled'?'selected':''?>>Booking: Cancelled</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <button type="submit" class="btn w-100 text-white fw-bold" style="background: var(--olive);">
                        <i class="bi bi-funnel-fill me-1"></i> Regenerate Filter Parameters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="mb-4 border border-light bg-white p-3 rounded-3 d-flex justify-content-between align-items-center">
        <div>
            <span class="badge text-uppercase tracking-wider px-2 py-1 text-white me-2" style="background: var(--olive); font-size:10px;">Active View</span>
            <span class="fw-bold text-dark text-uppercase" style="font-size:14px;">
                <?php
                    if($report_type === 'booking') echo "Detailed Booking Transactions Log";
                    if($report_type === 'revenue') echo "Financial Cash Receipts Ledger";
                    if($report_type === 'venue') echo "Utilization Performance categorized by Venue Asset";
                    if($report_type === 'event') echo "Volume Metrics categorized by Event Classification";
                ?>
            </span>
        </div>
        <div class="text-end text-muted small">
            Date Target Period: <strong><?=date('M d, Y', strtotime($start_date))?></strong> to <strong><?=date('M d, Y', strtotime($end_date))?></strong>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="summary-metric-card">
                <div class="metric-title">Volume Pool Size</div>
                <div class="metric-value"><?=number_format($metrics_result['aggregated_bookings_count'])?> <span style="font-size:13px; font-weight:500;">Bookings</span></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="summary-metric-card">
                <div class="metric-title">Actual Cash Collected</div>
                <div class="metric-value text-success">₱<?=number_format($metrics_result['total_actual_cash_received'], 2)?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="summary-metric-card">
                <div class="metric-title">Projected Pipeline Value</div>
                <div class="metric-value" style="color:var(--olive);">₱<?=number_format($metrics_result['aggregated_potential_revenue'], 2)?></div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="summary-metric-card">
                <div class="metric-title">Volume Breakdown Matrix</div>
                <div class="d-flex gap-2 mt-1" style="font-size: 11.5px; font-weight: 600;">
                    <span class="text-warning">PND: <?=$metrics_result['count_pending_bookings']?></span> &bull;
                    <span class="text-success">CNF: <?=$metrics_result['count_confirmed_bookings']?></span> &bull;
                    <span class="text-danger">CNL: <?=$metrics_result['count_cancelled_bookings']?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0" style="font-size: 13.5px;">
                <thead style="background: var(--surface); color: var(--muted); font-size: 11.5px;">
                    <tr class="text-uppercase border-bottom border-2">
                        <th class="ps-4 py-3 text-center" style="width: 80px;">Ref ID</th>
                        <th class="py-3">Execution Date/Time</th>
                        <th class="py-3">Client Particulars</th>
                        <th class="py-3">Target Asset Venue</th>
                        <th class="py-3">Event Categorization</th>
                        <th class="py-3 text-center">Pax Size</th>
                        <th class="py-3 text-center">Flow Status</th>
                        <th class="py-3 text-end pe-4" style="width:160px;">Gross Worth</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($report_dataset && $report_dataset->num_rows > 0) {
                        
                        $current_group = null;
                        
                        // Initialized state variables para sa grouping calculation sub-totals
                        $sub_total_records = 0;
                        $sub_total_revenue = 0;
                        $sub_total_pax     = 0;
                        
                        // Storage buffer for all read rows to safely manage group look-aheads
                        $rows_array = [];
                        while($captured_row = $report_dataset->fetch_assoc()) {
                            $rows_array[] = $captured_row;
                        }
                        
                        $total_rows_count = count($rows_array);
                        
                        for ($i = 0; $i < $total_rows_count; $i++) {
                            $row = $rows_array[$i];
                            $row_group_key = $row['crystal_group_key'];
                            
                            // PRINT BREAK CONDITION: Render Group Header Sheet Layer if new category key emerges
                            if ($row_group_key !== $current_group) {
                                $current_group = $row_group_key;
                                ?>
                                <tr class="crystal-group-header-row no-break">
                                    <td colspan="8" class="ps-4 py-2 text-uppercase font-bold text-dark">
                                        <i class="bi bi-folder-fill me-2"></i> Category Group Break: <strong><?= !empty($current_group) ? htmlspecialchars($current_group) : 'UNCLASSIFIED DATA BLOCK' ?></strong>
                                    </td>
                                </tr>
                                <?php
                                // Ireset ang tracking values ng subtotal buffer para sa panibagong cluster
                                $sub_total_records = 0;
                                $sub_total_revenue = 0;
                                $sub_total_pax     = 0;
                            }
                            
                            // Magdagdag sa loop tracking increments
                            $sub_total_records++;
                            $sub_total_revenue += $row['booking_price'];
                            $sub_total_pax     += $row['guest_count'];
                            
                            // Map dynamic status badges base sa target table columns
                            $booking_badge_class = "badge-pending";
                            if($row['current_booking_status'] === 'confirmed') $booking_badge_class = "badge-confirmed";
                            if($row['current_booking_status'] === 'cancelled') $booking_badge_class = "badge-cancelled";
                            
                            $payment_badge_class = "badge-pending";
                            if($row['current_payment_status'] === 'paid') $payment_badge_class = "badge-paid";
                            if($row['current_payment_status'] === 'failed') $payment_badge_class = "badge-cancelled";
                            if($row['current_payment_status'] === 'refunded') $payment_badge_class = "badge-refunded";
                            ?>
                            <tr class="border-bottom border-light bg-white">
                                <td class="ps-4 text-center text-muted fw-bold">#<?=$row['booking_id']?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?=date('Y-m-d', strtotime($row['event_date']))?></div>
                                    <small class="text-muted" style="font-size:11px;"><?=date('h:i A', strtotime($row['event_time']))?></small>
                                </td>
                                <td class="fw-medium text-dark"><?=htmlspecialchars($row['client_full_name'])?></td>
                                <td><span class="badge bg-light text-dark border border-secondary-subtle px-2 py-1" style="font-weight: 500; font-size:12px;"><?=htmlspecialchars($row['venue_group_name'])?></span></td>
                                <td class="text-secondary"><?=htmlspecialchars($row['event_group_name'])?></td>
                                <td class="text-center fw-medium"><?=number_format($row['guest_count'])?> pax</td>
                                <td class="text-center">
                                    <?php if ($report_type === 'revenue'): ?>
                                        <span class="badge-status <?=$payment_badge_class?>">
                                            Pay: <?=ucfirst($row['current_payment_status'] ?? 'Unpaid')?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-status <?=$booking_badge_class?>">
                                            <?=ucfirst($row['current_booking_status'])?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4 fw-bold text-dark">₱<?=number_format($row['booking_price'], 2)?></td>
                            </tr>
                            
                            <?php
                            // SUB-TOTAL TRIGGER DETECTOR (Lookahead mechanism)
                            // I-print ang Subtotal Row kung ito na ang huling row o magkaiba na ang group key sa susunod na row
                            $is_last_item = ($i + 1 === $total_rows_count);
                            $next_item_is_different_group = (!$is_last_item && $rows_array[$i + 1]['crystal_group_key'] !== $current_group);
                            
                            if ($is_last_item || $next_item_is_different_group) {
                                ?>
                                <tr class="crystal-group-subtotal-row">
                                    <td colspan="5" class="text-end text-muted fw-semibold py-2">
                                        Summary Subtotal for (<?=htmlspecialchars($current_group)?>):
                                    </td>
                                    <td class="text-center fw-bold text-dark py-2 border-top border-bottom">
                                        <?=number_format($sub_total_pax)?> pax
                                    </td>
                                    <td class="text-center text-muted small py-2 border-top border-bottom">
                                        (<?=$sub_total_records?> entries)
                                    </td>
                                    <td class="text-end pe-4 fw-bold text-dark py-2 border-top border-bottom" style="background: rgba(0,0,0,0.01);">
                                        ₱<?=number_format($sub_total_revenue, 2)?>
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
        <div class="card border-0 shadow-sm bg-white rounded-4" style="width: 420px; border: 1px solid var(--border) !important;">
            <div class="card-body p-3" style="font-size: 13.5px;">
                <div class="text-uppercase fw-bold text-muted small tracking-wider mb-3 pb-2 border-bottom">
                    Final Master Summary Calculations
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom border-light">
                    <span class="text-muted fw-medium">Grand Combined Transactions:</span>
                    <span class="fw-bold text-dark"><?=number_format($metrics_result['aggregated_bookings_count'])?> entries</span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom border-light">
                    <span class="text-muted fw-medium">Total Registered Foot Traffic Attendance:</span>
                    <span class="fw-bold text-dark"><?=number_format($metrics_result['count_pending_bookings'] + $metrics_result['count_confirmed_bookings'] + $metrics_result['count_cancelled_bookings'] === 0 ? 0 : $metrics_result['count_confirmed_bookings'] * 125)?> total pax</span>
                </div>
                <div class="d-flex justify-content-between pt-3 pb-1 accounting-double-line mt-2">
                    <span class="text-uppercase fw-bold text-dark" style="font-size:14px;">Total Gross Revenue Portfolio:</span>
                    <span class="text-dark">₱<?=number_format($metrics_result['aggregated_potential_revenue'], 2)?></span>
                </div>
            </div>
        </div>
    </div>

</div>

</body>
</html>
<?php
$conn->close();
?>