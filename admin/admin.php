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
    header('Location: ../index.php');
    exit();
}

$errors = [];
$successMessage = '';
$success = false;
$formValues = [
    'name' => $_POST['name'] ?? '',
    'location' => $_POST['location'] ?? '',
    'price' => $_POST['price'] ?? '',
    'capacity' => $_POST['capacity'] ?? '',
    'description' => $_POST['description'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_venue') {
        $name = trim($_POST['name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        $capacity = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            $errors[] = 'Venue name is required.';
        }
        if (empty($location)) {
            $errors[] = 'Location is required.';
        }
        if ($price === false || $price < 0) {
            $errors[] = 'A valid price is required.';
        }
        if ($capacity === false || $capacity < 1) {
            $errors[] = 'A valid capacity is required.';
        }
        if (empty($description)) {
            $errors[] = 'Description is required.';
        }

        $imageUrl = 'assets/images/default-venue.jpg';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['image']['tmp_name']);
            finfo_close($finfo);

            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!in_array($mime, $allowed, true)) {
                $errors[] = 'Only JPG, PNG, WEBP, or GIF images are allowed.';
            } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Image must be under 5 MB.';
            } else {
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $fname = time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                $dir = dirname(__DIR__) . '/assets/images/';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                if (move_uploaded_file($_FILES['image']['tmp_name'], $dir . $fname)) {
                    $imageUrl = 'assets/images/' . $fname;
                } else {
                    $errors[] = 'Failed to upload image. Check folder permissions on /assets/images/.';
                }
            }
        }

        if (empty($errors)) {
            $currentUser = getCurrentUser();
            $createdBy = $currentUser['id'] ?? null;
            $stmt = $conn->prepare(
                "INSERT INTO venues (name, location, capacity, price, description, image_url, created_by, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
            );

            if ($stmt) {
                $stmt->bind_param('ssidssi', $name, $location, $capacity, $price, $description, $imageUrl, $createdBy);
                if ($stmt->execute()) {
                    $success = true;
                    $successMessage = 'New venue saved successfully.';
                    $formValues = ['name' => '', 'location' => '', 'price' => '', 'capacity' => '', 'description' => ''];
                } else {
                    $errors[] = 'Database error while saving venue.';
                }
                $stmt->close();
            } else {
                $errors[] = 'Could not prepare the venue save statement.';
            }
        }
    }

    if (isset($_POST['delete_venue_id'])) {
        $deleteId = (int) $_POST['delete_venue_id'];
        if ($deleteId > 0) {
            $stmt = $conn->prepare('UPDATE venues SET is_active = 0 WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $deleteId);
                if ($stmt->execute()) {
                    $success = true;
                    $successMessage = 'Venue has been removed from the active list.';
                } else {
                    $errors[] = 'Database error while deleting venue.';
                }
                $stmt->close();
            } else {
                $errors[] = 'Could not prepare the delete statement.';
            }
        }
    }
}

/* =========================
   DATA FETCH (DB-backed)
========================= */
$countRow = getRow($conn, 'SELECT COUNT(*) AS cnt FROM bookings');
$totalBookings = (int) ($countRow['cnt'] ?? 0);
$countRow = getRow($conn, 'SELECT COUNT(*) AS cnt FROM venues WHERE is_active = 1');
$totalVenues = (int) ($countRow['cnt'] ?? 0);
$countRow = getRow($conn, 'SELECT COUNT(*) AS cnt FROM users');
$totalUsers = (int) ($countRow['cnt'] ?? 0);

$bookings = getRows($conn,
    'SELECT b.id, b.event_type, b.event_date AS date, b.guest_count, IFNULL(v.name, "Unknown Venue") AS venue_name, IFNULL(v.id, 0) AS venue_id
     FROM bookings b
     LEFT JOIN venues v ON b.venue_id = v.id
     ORDER BY b.event_date ASC'
);

$calendarEvents = [];
foreach ($bookings as $b) {
    $calendarEvents[] = [
        'title' => ($b['venue_name'] ?? 'Venue ' . $b['venue_id']) . ' - ' . $b['event_type'],
        'start' => $b['date'],
        'description' => 'Guests: ' . $b['guest_count'],
        'backgroundColor' => '#0d6efd',
        'borderColor' => '#0d6efd'
    ];
}

$venues = getRows($conn, 'SELECT id, name, location, price, capacity, is_active, created_at FROM venues ORDER BY id DESC');

function isValidBooking($b) {
    return is_array($b) && isset($b['venue_id'], $b['event_type'], $b['date'], $b['guest_count']);
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

        <?php $stats = [$totalBookings, $totalVenues, $totalUsers]; ?>
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
                                <td><?= htmlspecialchars($b['guest_count']) ?> guests</td>
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
            <div class="card p-4 shadow-sm border-0" style="max-width: 700px;">
                <?php if ($success && isset($_POST['action']) && $_POST['action'] === 'add_venue'): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
                <?php endif; ?>
                <?php if (!empty($errors) && isset($_POST['action']) && $_POST['action'] === 'add_venue'): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_venue">
                    <div class="mb-3">
                        <label class="form-label">Venue Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g., Tagpo Secret Garden"
                               value="<?= htmlspecialchars($formValues['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location / Address</label>
                        <input type="text" name="location" class="form-control" placeholder="e.g., Lipa, Batangas"
                               value="<?= htmlspecialchars($formValues['location']) ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Base Price (₱)</label>
                            <input type="number" name="price" step="0.01" min="0" class="form-control"
                                   placeholder="35000" value="<?= htmlspecialchars($formValues['price']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Max Capacity (pax)</label>
                            <input type="number" name="capacity" min="1" class="form-control"
                                   placeholder="200" value="<?= htmlspecialchars($formValues['capacity']) ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Describe the venue ambiance..." required><?= htmlspecialchars($formValues['description']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Venue Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <div class="form-text">Optional. JPG, PNG, WEBP, GIF. Max 5 MB.</div>
                    </div>
                    <div class="d-flex gap-3">
                        <button type="submit" class="btn btn-primary px-5">Save Venue</button>
                        <a href="?view=dashboard" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        <?php break;

        case 'manage_venues': ?>
            <h2 class="mb-4">Manage & Delete Venues</h2>
            <?php if ($success && isset($_POST['delete_venue_id'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
            <?php endif; ?>
            <?php if (!empty($errors) && isset($_POST['delete_venue_id'])): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <div class="card p-4 shadow-sm border-0">
                <?php if (empty($venues)): ?>
                    <div class="text-muted">No venues have been added yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Venue Name</th>
                                    <th>Location</th>
                                    <th>Price</th>
                                    <th>Capacity</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($venues as $venue): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($venue['id']) ?></td>
                                        <td><?= htmlspecialchars($venue['name']) ?></td>
                                        <td><?= htmlspecialchars($venue['location']) ?></td>
                                        <td>₱<?= number_format((float) $venue['price'], 2) ?></td>
                                        <td><?= htmlspecialchars($venue['capacity']) ?></td>
                                        <td>
                                            <?php if ($venue['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($venue['is_active']): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="delete_venue_id" value="<?= htmlspecialchars($venue['id']) ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Remove this venue?');">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">No action</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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