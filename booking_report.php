<?php
// 1. DATABASE CONNECTION
$host     = "localhost";
$username = "root";
$password = ""; // Palitan kung may password ang MySQL mo
$dbname   = "tagpo_db";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. FILTERS & SEARCH LOGIC
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); 
$end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');    
$search     = isset($_GET['search']) ? trim($_GET['search']) : '';

$start_date_clean = $conn->real_escape_string($start_date);
$end_date_clean   = $conn->real_escape_string($end_date);
$search_clean     = $conn->real_escape_string($search);

// 3. MASTER QUERY (Main Records)
$query = "SELECT 
            b.booking_id,
            b.event_date,
            b.event_time,
            b.guest_count,
            b.total_price,
            b.status AS booking_status,
            CONCAT(u.first_name, ' ', u.last_name) AS client_name,
            v.name AS venue_name,
            e.event_name,
            p.payment_status,
            p.payment_method
          FROM bookings b
          JOIN users u ON b.user_id = u.id
          JOIN venues v ON b.venue_id = v.id AND v.archived = 0
          JOIN event e ON b.event_id = e.event_id AND e.archived = 0
          LEFT JOIN payments p ON b.cart_id = p.cart_id
          WHERE b.event_date BETWEEN '$start_date_clean' AND '$end_date_clean'";

if (!empty($search)) {
    $query .= " AND (v.name LIKE '%$search_clean%' OR u.first_name LIKE '%$search_clean%' OR e.event_name LIKE '%$search_clean%')";
}
$query .= " ORDER BY b.event_date ASC";
$result = $conn->query($query);

// 4. CHART QUERY 1: Revenue per Venue
$venue_chart_query = "SELECT v.name AS venue_name, SUM(b.total_price) AS venue_revenue
                      FROM bookings b
                      JOIN venues v ON b.venue_id = v.id AND v.archived = 0
                      WHERE b.event_date BETWEEN '$start_date_clean' AND '$end_date_clean'
                      AND b.status != 'cancelled'
                      GROUP BY b.venue_id";
$venue_chart_result = $conn->query($venue_chart_query);
$venue_data = [];
$max_venue_revenue = 1; 
while($row = $venue_chart_result->fetch_assoc()) {
    $venue_data[] = $row;
    if($row['venue_revenue'] > $max_venue_revenue) {
        $max_venue_revenue = $row['venue_revenue'];
    }
}

// 5. CHART QUERY 2: Bookings Count per Event Type
$event_chart_query = "SELECT e.event_name, COUNT(b.booking_id) AS event_count
                      FROM bookings b
                      JOIN event e ON b.event_id = e.event_id AND e.archived = 0
                      WHERE b.event_date BETWEEN '$start_date_clean' AND '$end_date_clean'
                      GROUP BY b.event_id";
$event_chart_result = $conn->query($event_chart_query);
$event_data = [];
$max_event_count = 1;
while($row = $event_chart_result->fetch_assoc()) {
    $event_data[] = $row;
    if($row['event_count'] > $max_event_count) {
        $max_event_count = $row['event_count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tagpo Booking Analytics Report</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        /* =============================================
           TAGPO THEME COHESION (From admin_style.php)
           ============================================= */
        :root {
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
          --radius-lg:  14px;
          --shadow:     0 2px 8px rgba(0,0,0,.07);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
        }

        /* Pure CSS Crystal-Dashboard Progress Bars */
        .report-bar-track {
            background: var(--bg);
            height: 12px;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        .report-bar-fill-primary {
            background: var(--green);
            height: 100%;
            border-radius: 20px;
        }
        .report-bar-fill-secondary {
            background: var(--olive);
            height: 100%;
            border-radius: 20px;
        }

        /* Accounting Double-Underline for Grand Totals */
        .accounting-double {
            border-bottom: 4px double var(--text) !important;
            font-weight: 700;
        }

        /* =============================================
           PRINT & BOOTSTRAP PDF OPTIMIZATION
           ============================================= */
        @media print {
            body {
                background: #fff !important;
                color: #000 !important;
            }
            /* Itago ang control panels at interactive web buttons */
            .filter-toolbar, .btn-action, .btn, form, hr.no-print {
                display: none !important;
            }
            /* Puwersahing punuin ang buong papel (A4/Letter) nang walang bawas sa margins */
            .container-fluid {
                padding: 0 !important;
                margin: 0 !important;
            }
            .card {
                border: 1px solid var(--border) !important;
                box-shadow: none !important;
                background: #fff !important;
                page-break-inside: avoid;
            }
            /* Panatilihin ang kulay ng Progress/Bar graphs at Badges kahit i-save sa PDF */
            .report-bar-track {
                background: #f0f0f0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .report-bar-fill-primary {
                background-color: #3d7a3a !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .report-bar-fill-secondary {
                background-color: #4a5c2f !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .badge-status {
                border: 1px solid #ccc !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .table th {
                background-color: #f5f6f4 !important;
                color: #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body class="py-4">

<div class="container-fluid px-4" style="max-width: 1400px;">

    <div class="card border-0 shadow-sm rounded-3 mb-4 filter-toolbar">
        <div class="card-body p-3">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 11px;">Date From</label>
                    <input type="date" name="start_date" class="form-control form-control-sm border-secondary-subtle" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 11px;">Date To</label>
                    <input type="date" name="end_date" class="form-control form-control-sm border-secondary-subtle" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-uppercase fw-bold text-muted" style="font-size: 11px;">Search Keyword</label>
                    <input type="text" name="search" class="form-control form-control-sm border-secondary-subtle" placeholder="Client, Venue, Event..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm text-white w-100 fw-medium" style="background: var(--green);">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh Data
                    </button>
                    <button type="button" onclick="window.print();" class="btn btn-sm btn-outline-dark w-100 fw-medium">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Save PDF / Print
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4 border-2 border-dark">
        <div>
            <h2 class="fw-bold text-uppercase m-0 tracking-wide" style="color: var(--text);">Tagpo Events & Venues</h2>
            <p class="text-muted m-0 mt-1">System-Generated Financial & Volume Analytics Summary</p>
            <small class="fw-medium text-secondary">
                Coverage: <?php echo date('F d, Y', strtotime($start_date)); ?> — <?php echo date('F d, Y', strtotime($end_date)); ?>
            </small>
        </div>
        <div class="text-end">
            <div class="px-3 py-1 rounded fw-bold text-white text-uppercase" style="background: var(--green); font-size: 14px; letter-spacing: 1px;">
                Tagpo Admin
            </div>
            <small class="text-muted d-block mt-2">Generated: <?php echo date('Y-m-d H:i'); ?></small>
        </div>
    </div>

    <div class="row g-4 mb-4">
        
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h6 class="text-uppercase fw-bold text-muted mb-4 small" style="letter-spacing: .6px;">
                        <i class="bi bi-cash-coin me-2 text-success"></i>Revenue Generated by Venue
                    </h6>
                    <?php
                    if (!empty($venue_data)) {
                        foreach ($venue_data as $v_row) {
                            $percentage = ($v_row['venue_revenue'] / $max_venue_revenue) * 100;
                            ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-semibold text-secondary" style="font-size: 13px;"><?php echo htmlspecialchars($v_row['venue_name']); ?></span>
                                    <span class="fw-bold text-dark" style="font-size: 13.5px;">₱<?php echo number_format($v_row['venue_revenue'], 2); ?></span>
                                </div>
                                <div class="report-bar-track">
                                    <div class="report-bar-fill-primary" style="width: <?php echo $percentage; ?>%;"></div>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo "<div class='text-muted small py-3 text-center border rounded border-dashed'>No venue revenue data found.</div>";
                    }
                    ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h6 class="text-uppercase fw-bold text-muted mb-4 small" style="letter-spacing: .6px;">
                        <i class="bi bi-calendar-check me-2 text-primary"></i>Booking Volume by Event Type
                    </h6>
                    <?php
                    if (!empty($event_data)) {
                        foreach ($event_data as $e_row) {
                            $percentage = ($e_row['event_count'] / $max_event_count) * 100;
                            ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-semibold text-secondary" style="font-size: 13px;"><?php echo htmlspecialchars($e_row['event_name']); ?></span>
                                    <span class="fw-bold text-dark" style="font-size: 13.5px;"><?php echo $e_row['event_count']; ?> Bookings</span>
                                </div>
                                <div class="report-bar-track">
                                    <div class="report-bar-fill-secondary" style="width: <?php echo $percentage; ?>%;"></div>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo "<div class='text-muted small py-3 text-center border rounded border-dashed'>No event booking data found.</div>";
                    }
                    ?>
                </div>
            </div>
        </div>

    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="p-3 border-bottom bg-white d-flex align-items-center justify-content-between">
                <span class="text-uppercase fw-bold text-muted small" style="letter-spacing: .5px;">Detailed Transaction Ledger</span>
                <span class="badge rounded-pill px-2 py-1 text-dark text-opacity-75 bg-light border" style="font-size: 11px;">Tabular View</span>
            </div>
            
            <div class="table-responsive">
                <table class="table align-middle mb-0 text-nowrap" style="font-size: 13.5px;">
                    <thead class="table-light text-uppercase text-muted" style="font-size: 11px; letter-spacing: .5px;">
                        <tr>
                            <th class="ps-4 py-3 text-center">ID</th>
                            <th class="py-3">Target Date/Time</th>
                            <th class="py-3">Client Details</th>
                            <th class="py-3">Assigned Venue</th>
                            <th class="py-3">Classification</th>
                            <th class="py-3 text-center">Attendance</th>
                            <th class="py-3 text-center">Status</th>
                            <th class="py-3 text-end pe-4">Total Net Worth</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $count_rows = 0;
                        $sum_pax = 0;
                        $sum_revenue = 0;

                        if ($result && $result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                $count_rows++;
                                $sum_pax += $row['guest_count'];
                                $sum_revenue += $row['total_price'];
                                
                                // Tagpo CSS Status Adapter
                                $status_class = "badge-pending";
                                if($row['booking_status'] == 'confirmed') $status_class = "badge-confirmed";
                                if($row['booking_status'] == 'completed') $status_class = "badge-completed";
                                if($row['booking_status'] == 'cancelled') $status_class = "badge-cancelled";
                                ?>
                                <tr class="border-bottom border-light">
                                    <td class="ps-4 text-center text-muted fw-medium">#<?php echo $row['booking_id']; ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo date('Y-m-d', strtotime($row['event_date'])); ?></div>
                                        <small class="text-muted" style="font-size: 11.5px;"><?php echo date('g:i A', strtotime($row['event_time'])); ?></small>
                                    </td>
                                    <td class="fw-medium text-secondary"><?php echo htmlspecialchars($row['client_name']); ?></td>
                                    <td><span class="badge bg-light text-dark border px-2 py-1" style="font-size:12px; font-weight:500;"><?php echo htmlspecialchars($row['venue_name']); ?></span></td>
                                    <td class="text-secondary"><?php echo htmlspecialchars($row['event_name']); ?></td>
                                    <td class="text-center text-dark fw-medium"><?php echo number_format($row['guest_count']); ?> pax</td>
                                    <td class="text-center">
                                        <span class="badge-status <?php echo $status_class; ?>">
                                            <?php echo ucfirst($row['booking_status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4 fw-bold text-dark">₱<?php echo number_format($row['total_price'], 2); ?></td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='8' class='text-center text-muted py-5 border-0'>No ledger entries matched during this scope filter.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end mb-5">
        <div class="card border-0 shadow-sm rounded-4" style="width: 360px;">
            <div class="card-body p-3" style="font-size: 13.5px;">
                <div class="d-flex justify-content-between py-2 border-bottom border-light">
                    <span class="text-muted fw-medium">Total Settled Records:</span>
                    <span class="fw-bold text-dark"><?php echo $count_rows; ?> transactions</span>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom border-light">
                    <span class="text-muted fw-medium">Accumulated Attendance:</span>
                    <span class="fw-bold text-dark"><?php echo number_format($sum_pax); ?> pax</span>
                </div>
                <div class="d-flex justify-content-between pt-3 pb-2 accounting-double">
                    <span class="text-uppercase fw-bold text-dark">Gross Revenue:</span>
                    <span class="text-dark">₱<?php echo number_format($sum_revenue, 2); ?></span>
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