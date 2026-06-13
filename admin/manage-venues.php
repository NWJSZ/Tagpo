<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/session_config.php';
require_once dirname(__DIR__) . '/config/app.php';

$_SESSION['last_activity'] = time();
if (isLoggedIn()) {
    setcookie('user_session', getCurrentUser()['email'], time() + 86400 * 7, '/');
}
if (!isAdmin()) {
    header('Location: ../auth/login.php');
    exit();
}

$currentUser = getCurrentUser();
$currentPage = 'venues';

$errors  = [];
$flash   = null;

/* ── Helper: handle image upload ─────────────────────────────── */
function handleImageUpload(array &$errors): ?string {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // no new file uploaded
    }
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Image upload failed.';
        return null;
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $_FILES['image']['tmp_name']);
    finfo_close($finfo);
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowed)) {
        $errors[] = 'Only JPG, PNG, WEBP, or GIF images are allowed.';
        return null;
    }
    if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
        $errors[] = 'Image must be under 5 MB.';
        return null;
    }
    $ext   = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $fname = time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
    $dir   = dirname(__DIR__) . '/assets/images/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    if (move_uploaded_file($_FILES['image']['tmp_name'], $dir . $fname)) {
        return 'assets/images/' . $fname;
    }
    $errors[] = 'Failed to upload image. Check folder permissions on /assets/images/.';
    return null;
}

/* ── Handle Add / Edit / Delete ───────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM venues WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $flash = 'Venue deleted successfully.';
    }

    if ($formAction === 'save') {
        $id          = (int) ($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $location    = trim($_POST['location'] ?? '');
        $capacity    = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);
        $price       = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
        $description = trim($_POST['description'] ?? '');

        if ($name === '')                    $errors[] = 'Venue name is required.';
        if ($location === '')                $errors[] = 'Location is required.';
        if (!$capacity || $capacity < 1)     $errors[] = 'A valid capacity is required.';
        if ($price === false || $price < 0)  $errors[] = 'A valid price is required.';

        $imageUrl = handleImageUpload($errors);

        if (empty($errors)) {
            if ($id > 0) {
                // Update existing venue
                if ($imageUrl !== null) {
                    $stmt = $conn->prepare("UPDATE venues SET name=?, location=?, capacity=?, price=?, description=?, image_url=? WHERE id=?");
                    $stmt->bind_param('ssidssi', $name, $location, $capacity, $price, $description, $imageUrl, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE venues SET name=?, location=?, capacity=?, price=?, description=? WHERE id=?");
                    $stmt->bind_param('ssidsi', $name, $location, $capacity, $price, $description, $id);
                }
                $stmt->execute();
                $stmt->close();
                $flash = 'Venue updated successfully.';
            } else {
                // Insert new venue
                $imageUrl = $imageUrl ?? 'assets/images/default-venue.jpg';
                $stmt = $conn->prepare("INSERT INTO venues (name, location, capacity, price, description, image_url) VALUES (?,?,?,?,?,?)");
                $stmt->bind_param('ssidss', $name, $location, $capacity, $price, $description, $imageUrl);
                $stmt->execute();
                $stmt->close();
                $flash = 'Venue added successfully.';
            }
        }
    }
}

/* ── Fetch venues + amenities + booking counts ───────────────── */
$venues = $conn->query("SELECT * FROM venues ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

$venueIds = array_column($venues, 'id');
$amenitiesByVenue = [];
$bookingCountByVenue = [];
if (!empty($venueIds)) {
    $placeholders = implode(',', array_fill(0, count($venueIds), '?'));
    $types = str_repeat('i', count($venueIds));

    $stmt = $conn->prepare("SELECT venue_id, amenity_name FROM amenities WHERE venue_id IN ($placeholders)");
    $stmt->bind_param($types, ...$venueIds);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $amenitiesByVenue[$row['venue_id']][] = $row['amenity_name'];
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT venue_id, COUNT(*) AS c FROM bookings WHERE venue_id IN ($placeholders) GROUP BY venue_id");
    $stmt->bind_param($types, ...$venueIds);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $bookingCountByVenue[$row['venue_id']] = (int) $row['c'];
    }
    $stmt->close();
}

$totalVenues   = count($venues);
$totalCapacity = array_sum(array_column($venues, 'capacity'));
$avgPrice      = $totalVenues > 0 ? array_sum(array_column($venues, 'price')) / $totalVenues : 0;

// If editing, pre-load that venue for the form
$editVenue = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    foreach ($venues as $v) {
        if ((int)$v['id'] === $editId) { $editVenue = $v; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Venues | Tagpo Admin</title>
  <?php include 'includes/admin_style.php'; ?>
</head>
<body>

<?php include 'includes/admin_sidebar.php'; ?>

<div class="admin-main">

  <header class="admin-topbar">
    <div class="topbar-title">Venues</div>
    <div class="topbar-right">
      <div class="topbar-date"><i class="bi bi-calendar3"></i> <?= date('M j, Y (D)') ?></div>
      <div class="topbar-avatar"><?= htmlspecialchars(strtoupper(substr($currentUser['first_name'] ?? 'A', 0, 1))) ?></div>
    </div>
  </header>

  <div class="admin-content">

    <div class="page-header d-flex justify-between" style="align-items:flex-end;">
      <div>
        <h1>Venues</h1>
        <p>Add, edit, or remove venues available for booking.</p>
      </div>
      <button class="btn-action btn-primary-green" onclick="openVenueDrawer()"><i class="bi bi-plus-lg"></i> Add Venue</button>
    </div>

    <?php if ($flash): ?>
      <div class="alert-bar success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
      <div class="alert-bar error"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <!-- Stat cards -->
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="stat-card">
          <div class="stat-icon green"><i class="bi bi-building"></i></div>
          <div>
            <div class="stat-label">Total Venues</div>
            <div class="stat-value"><?= $totalVenues ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stat-card">
          <div class="stat-icon blue"><i class="bi bi-people"></i></div>
          <div>
            <div class="stat-label">Total Capacity</div>
            <div class="stat-value"><?= number_format($totalCapacity) ?></div>
            <div class="stat-sub">guests combined</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="stat-card">
          <div class="stat-icon orange"><i class="bi bi-tag"></i></div>
          <div>
            <div class="stat-label">Average Price</div>
            <div class="stat-value">&#8369;<?= number_format($avgPrice, 0) ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="panel-card">
      <div class="panel-card-header">
        <h2>All Venues</h2>
      </div>

      <?php if (empty($venues)): ?>
        <div class="panel-card-body text-muted-sm">No venues yet. Click "Add Venue" to create one.</div>
      <?php else: ?>
        <table class="data-table">
          <thead>
            <tr>
              <th>Venue</th>
              <th>Location</th>
              <th>Capacity</th>
              <th>Price</th>
              <th>Amenities</th>
              <th>Bookings</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($venues as $v): ?>
              <tr>
                <td>
                  <div class="d-flex align-center gap-8">
                    <img src="../<?= htmlspecialchars($v['image_url'] ?: 'assets/images/default-venue.jpg') ?>"
                         alt="" style="width:54px;height:54px;object-fit:cover;border-radius:8px;background:var(--bg);">
                    <div class="fw-600"><?= htmlspecialchars($v['name']) ?></div>
                  </div>
                </td>
                <td><?= htmlspecialchars($v['location']) ?></td>
                <td><?= (int)$v['capacity'] ?> guests</td>
                <td>&#8369;<?= number_format($v['price'], 2) ?></td>
                <td class="text-muted-sm">
                  <?= !empty($amenitiesByVenue[$v['id']]) ? htmlspecialchars(implode(', ', $amenitiesByVenue[$v['id']])) : '—' ?>
                </td>
                <td><?= $bookingCountByVenue[$v['id']] ?? 0 ?></td>
                <td>
                  <div class="d-flex gap-8">
                    <button class="icon-btn" title="Edit" onclick='openVenueDrawer(<?= json_encode([
                        "id" => $v["id"], "name" => $v["name"], "location" => $v["location"],
                        "capacity" => $v["capacity"], "price" => $v["price"],
                        "description" => $v["description"], "image_url" => $v["image_url"],
                    ]) ?>)'><i class="bi bi-pencil"></i></button>

                    <form method="post" onsubmit="return confirm('Delete this venue? This cannot be undone.');" style="display:inline;">
                      <input type="hidden" name="form_action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                      <button type="submit" class="icon-btn" title="Delete"><i class="bi bi-trash"></i></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <div class="pagination-bar">
        <span>Showing <?= $totalVenues ?> venues</span>
      </div>
    </div>

  </div>
</div>

<!-- Add/Edit Side Drawer -->
<div class="side-drawer-overlay" id="drawerOverlay" onclick="closeVenueDrawer()"></div>
<div class="side-drawer" id="venueDrawer">
  <div class="drawer-header">
    <div>
      <h3 id="venueDrawerTitle">Add Venue</h3>
      <p>Fill in the venue details below.</p>
    </div>
    <button class="drawer-close" onclick="closeVenueDrawer()"><i class="bi bi-x-lg"></i></button>
  </div>
  <form method="post" enctype="multipart/form-data" id="venueForm">
    <div class="drawer-body">
      <input type="hidden" name="form_action" value="save">
      <input type="hidden" name="id" id="venueId" value="0">

      <div class="mb-3">
        <label class="form-label-sm">Venue Name</label>
        <input type="text" name="name" id="venueName" class="form-ctrl" required>
      </div>
      <div class="mb-3">
        <label class="form-label-sm">Location</label>
        <input type="text" name="location" id="venueLocation" class="form-ctrl" required>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-6">
          <label class="form-label-sm">Capacity</label>
          <input type="number" name="capacity" id="venueCapacity" class="form-ctrl" min="1" required>
        </div>
        <div class="col-6">
          <label class="form-label-sm">Price (&#8369;)</label>
          <input type="number" step="0.01" name="price" id="venuePrice" class="form-ctrl" min="0" required>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label-sm">Description</label>
        <textarea name="description" id="venueDescription" class="form-ctrl" rows="4"></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label-sm">Cover Photo</label>
        <input type="file" name="image" accept="image/*" class="form-ctrl">
        <div class="text-muted-sm mt-1">Leave empty to keep the current image when editing.</div>
        <img id="venueImagePreview" src="" alt="" style="display:none;max-width:100%;border-radius:8px;margin-top:10px;">
      </div>
    </div>
    <div class="drawer-footer">
      <button type="submit" class="btn-full btn-full-green"><i class="bi bi-save me-1"></i> Save Venue</button>
      <button type="button" class="btn-full btn-full-outline" onclick="closeVenueDrawer()">Cancel</button>
    </div>
  </form>
</div>

<script>
function openVenueDrawer(venue) {
  const title = document.getElementById('venueDrawerTitle');
  const preview = document.getElementById('venueImagePreview');

  if (venue) {
    title.textContent = 'Edit Venue';
    document.getElementById('venueId').value          = venue.id;
    document.getElementById('venueName').value        = venue.name;
    document.getElementById('venueLocation').value    = venue.location;
    document.getElementById('venueCapacity').value    = venue.capacity;
    document.getElementById('venuePrice').value       = venue.price;
    document.getElementById('venueDescription').value = venue.description;
    if (venue.image_url) {
      preview.src = '../' + venue.image_url;
      preview.style.display = 'block';
    } else {
      preview.style.display = 'none';
    }
  } else {
    title.textContent = 'Add Venue';
    document.getElementById('venueForm').reset();
    document.getElementById('venueId').value = 0;
    preview.style.display = 'none';
  }

  document.getElementById('venueDrawer').classList.add('open');
  document.getElementById('drawerOverlay').classList.add('open');
}
function closeVenueDrawer() {
  document.getElementById('venueDrawer').classList.remove('open');
  document.getElementById('drawerOverlay').classList.remove('open');
}

<?php if ($editVenue): ?>
window.addEventListener('DOMContentLoaded', () => {
  openVenueDrawer(<?= json_encode([
      "id" => $editVenue["id"], "name" => $editVenue["name"], "location" => $editVenue["location"],
      "capacity" => $editVenue["capacity"], "price" => $editVenue["price"],
      "description" => $editVenue["description"], "image_url" => $editVenue["image_url"],
  ]) ?>);
});
<?php endif; ?>
</script>

</body>
</html>