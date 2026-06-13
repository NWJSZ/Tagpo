<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/session_config.php';
require_once dirname(__DIR__) . '/config/app.php';

$baseUrl = '../';

// Update activity
$_SESSION['last_activity'] = time();

// Refresh cookie if logged in
if (isLoggedIn()) {
    $currentUser = getCurrentUser();
    setcookie('user_session', $currentUser['email'], time() + (60 * 60 * 24 * 7), '/');
}

/* =========================
   SECURITY CHECK (ADMIN ONLY)
========================= */
if (!isAdmin()) {
    die("Access denied. Admin only.");
}

/* =========================
   SAMPLE DATA (BOOKINGS)
   Kung walang laman ang session, mag-inject tayo ng kunwaring bookings para may makita sa calendar
========================= */
if (empty($_SESSION['bookings'])) {
    $_SESSION['bookings'] = [
        [
            'venue_id' => '1',
            'venue_name' => 'Glass House Pavilion',
            'event_type' => 'Wedding Reception',
            'date' => date('Y-m-d'), // Ngayong araw
            'guests' => 150
        ],
        [
            'venue_id' => '2',
            'venue_name' => 'Cozy Garden Studio',
            'event_type' => 'Birthday Party',
            'date' => date('Y-m-d', strtotime('+2 days')), // Sa makalawa
            'guests' => 50
        ],
        [
            'venue_id' => '1',
            'venue_name' => 'Glass House Pavilion',
            'event_type' => 'Corporate Seminar',
            'date' => date('Y-m-d', strtotime('+5 days')),
            'guests' => 80
        ]
    ];
}

$bookings = $_SESSION['bookings'] ?? [];

// I-format ang bookings para sa FullCalendar JSON format
$calendarEvents = [];
foreach ($bookings as $b) {
    $calendarEvents[] = [
        'title' => ($b['venue_name'] ?? 'Venue ' . $b['venue_id']) . " - " . $b['event_type'],
        'start' => $b['date'],
        'description' => "Guests: " . $b['guests'],
        'backgroundColor' => '#0d6efd', // Bootstrap Primary Blue
        'borderColor' => '#0d6efd'
    ];
}

/* =========================
   DATA TYPE TEST (basic validation)
========================= */
function isValidBooking($b) {
    return is_array($b) && isset($b['venue_id'], $b['event_type'], $b['date']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | Tagpo</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 260px;
        }
        body {
            background-color: #f8f9fa;
        }
        /* Sidebar Styling */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #fff;
            border-right: 1px solid #dee2e6;
            z-index: 100;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
        }
        .nav-link {
            color: #495057;
            padding: 0.8rem 1rem;
            border-radius: 0.375rem;
            margin-bottom: 0.2rem;
        }
        .nav-link:hover, .nav-link.active {
            background-color: #e9ecef;
            color: #0d6efd;
        }
        /* Calendar UI Adjustments */
        .fc {
            background: #fff;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .fc-event {
            cursor: pointer;
            padding: 2px 5px;
        }
    </style>
</head>
<body>

<?php $view = $_GET['view'] ?? 'dashboard'; ?>

<div class="sidebar d-flex flex-column p-3">
    <a class="navbar-brand fw-bold fs-4 mb-4 px-2" href="../index.php">Tagpo<span class="text-primary">.</span> <span class="badge bg-dark fs-6 align-middle">Admin</span></a>
    
    <ul class="nav nav-pills flex-column mb-auto">
        <li>
            <a href="?view=dashboard" class="nav-link <?= $view == 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard (Calendar)
            </a>
        </li>
        <li>
            <a href="?view=bookings" class="nav-link <?= $view == 'bookings' ? 'active' : '' ?>">
                <i class="bi bi-calendar-check me-2"></i> View All Bookings
            </a>
        </li>
        <li>
            <a href="?view=add_venue" class="nav-link <?= $view == 'add_venue' ? 'active' : '' ?>">
                <i class="bi bi-plus-circle me-2"></i> Add New Venue
            </a>
        </li>
        <li>
            <a href="?view=manage_venues" class="nav-link <?= $view == 'manage_venues' ? 'active' : '' ?>">
                <i class="bi bi-sliders me-2"></i> Manage / Delete Venues
            </a>
        </li>
        <li>
            <a href="?view=payments" class="nav-link <?= $view == 'payments' ? 'active' : '' ?>">
                <i class="bi bi-cash-stack me-2"></i> Payments
            </a>
        </li>
    </ul>
    
    <hr>
    <div class="d-grid gap-2">
        <a href="../index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-house me-1"></i> Client Home</a>
        <a href="../auth/logout.php" class="btn btn-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
    </div>
</div>

<div class="main-content">

    <?php if ($view == 'dashboard'): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Welcome Back, Admin!</h2>
            <div class="text-muted"><?= date('F j, Y') ?></div>
        </div>

        <?php $stats = [count($bookings), 3, 12]; ?>
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card p-3 shadow-sm border-0">
                    <div class="d-flex align-items-center">
                        <div class="p-3 bg-primary-subtle text-primary rounded-3 me-3"><i class="bi bi-calendar-event fs-3"></i></div>
                        <div>
                            <h6 class="mb-0 text-muted">Total Bookings</h6>
                            <h3><?= $stats[0] ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3 shadow-sm border-0">
                    <div class="d-flex align-items-center">
                        <div class="p-3 bg-success-subtle text-success rounded-3 me-3"><i class="bi bi-building fs-3"></i></div>
                        <div>
                            <h6 class="mb-0 text-muted">Total Venues</h6>
                            <h3><?= $stats[1] ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3 shadow-sm border-0">
                    <div class="d-flex align-items-center">
                        <div class="p-3 bg-info-subtle text-info rounded-3 me-3"><i class="bi bi-people fs-3"></i></div>
                        <div>
                            <h6 class="mb-0 text-muted">Total Users</h6>
                            <h3><?= $stats[2] ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <h4 class="mb-3">Bookings Schedule</h4>
        <div id="calendar" class="mb-5"></div>

    <?php endif; ?>

    <?php switch ($view): 
        case 'bookings': ?>
            <h2 class="mb-4">All Bookings (Table View)</h2>
            <div class="card p-4 shadow-sm border-0">
                <?php if (empty($bookings)): ?>
                    <p class="text-muted mb-0">No bookings yet.</p>
                <?php else: ?>
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Venue</th>
                                <th>Event Type</th>
                                <th>Event Date</th>
                                <th>Guest Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $i = 0;
                            while ($i < count($bookings)):
                                $b = $bookings[$i];
                                if (isValidBooking($b)):
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($b['venue_name'] ?? 'Venue '.$b['venue_id']) ?></strong></td>
                                <td><?= htmlspecialchars($b['event_type']) ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($b['date']) ?></span></td>
                                <td><?= htmlspecialchars($b['guests']) ?> guests</td>
                            </tr>
                            <?php
                                endif;
                                $i++;
                            endwhile;
                            ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php break;

        case 'add_venue': ?>
            <h2 class="mb-4">Add New Venue</h2>
            <div class="card p-4 shadow-sm border-0" style="max-width: 600px;">
                <p class="text-muted small">Mock Form: Papaganahin natin ito kapag may database setup na.</p>
                <form action="#" method="POST">
                    <div class="mb-3">
                        <label class="form-label">Venue Name</label>
                        <input type="text" class="form-control" placeholder="e.g., Tagpo Secret Garden" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location / Address</label>
                        <input type="text" class="form-control" placeholder="e.g., Lipa, Batangas" disabled>
                    </div>
                    <button type="button" class="btn btn-primary" disabled>Save Venue (Soon)</button>
                </form>
            </div>
        <?php break;

        case 'manage_venues': ?>
            <h2 class="mb-4">Manage & Delete Venues</h2>
            <div class="card p-4 shadow-sm border-0">
                <table class="table align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Venue Name</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td>Glass House Pavilion</td>
                            <td><span class="badge bg-success">Active</span></td>
                            <td class="text-end"><button class="btn btn-danger btn-sm" disabled><i class="bi bi-trash"></i> Delete</button></td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td>Cozy Garden Studio</td>
                            <td><span class="badge bg-success">Active</span></td>
                            <td class="text-end"><button class="btn btn-danger btn-sm" disabled><i class="bi bi-trash"></i> Delete</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php break;
    endswitch; ?>

    

    <?php
    $count = 1;
    echo "<hr class='mt-5'><h6 class='text-muted'>Quick System Check</h6>";
    do {
        echo "<small class='text-muted-50'>• System check #" . $count . " OK</small><br>";
        $count++;
    } while ($count <= 3);
    ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    
    // Kung hindi 'dashboard' ang view, huwag i-render ang calendar para walang console error
    if (calendarEl) {
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            themeSystem: 'bootstrap5',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek'
            },
            // Dito natin pinasa ang binuo nating PHP array data papuntang JavaScript JSON!
            events: <?= json_encode($calendarEvents); ?>,
            eventClick: function(info) {
                alert('Event: ' + info.event.title + '\n' + info.event.extendedProps.description);
            }
        });
        calendar.render();
    }
});
</script>

</body>
</html>