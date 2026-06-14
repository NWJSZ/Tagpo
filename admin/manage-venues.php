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

$errors = [];
$flash  = null;

/* ── Helper: upload a single image ──────────────────────────────── */
function handleImageUpload(string $field, array &$errors): ?string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Upload error on field '{$field}'.";
        return null;
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $_FILES[$field]['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ['image/jpeg','image/png','image/webp','image/gif'])) {
        $errors[] = 'Only JPG, PNG, WEBP, or GIF images are allowed.';
        return null;
    }
    if ($_FILES[$field]['size'] > 5 * 1024 * 1024) {
        $errors[] = 'Image must be under 5 MB.';
        return null;
    }
    $ext   = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    $fname = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dir   = dirname(__DIR__) . '/assets/images/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $dir . $fname)) {
        return 'assets/images/' . $fname;
    }
    $errors[] = "Failed to save image. Check folder permissions on /assets/images/.";
    return null;
}

/* ── Helper: safe delete of an uploaded image (never delete seeded assets) */
function safeDeleteImage(?string $relPath): void {
    if (!$relPath) return;
    $base = basename($relPath);
    // Never delete the original seeded images
    if (preg_match('/^(paradiso|gardens|lounge|default-venue)/i', $base)) return;
    $abs = dirname(__DIR__) . '/' . $relPath;
    if (file_exists($abs)) @unlink($abs);
}

/* ── Handle POST ─────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['form_action'] ?? '';

    /* ── DELETE (Soft Delete - Archive) ── */
    if ($formAction === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        // Archive the venue instead of hard delete
        $stmt = $conn->prepare("UPDATE venues SET archived = 1 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $flash = 'Venue archived successfully.';
    }

    /* ── RESTORE (Unarchive) ── */
    if ($formAction === 'restore') {
      $id = (int)($_POST['id'] ?? 0);
      $stmt = $conn->prepare("UPDATE venues SET archived = 0 WHERE id = ?");
      $stmt->bind_param('i', $id);
      $stmt->execute();
      $stmt->close();
      $flash = 'Venue restored successfully.';
    }

    /* ── SAVE (Add or Edit) ── */
    if ($formAction === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $location    = trim($_POST['location'] ?? '');
        $capacity    = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT);
        $price       = filter_input(INPUT_POST, 'price',    FILTER_VALIDATE_FLOAT);
        $description = trim($_POST['description'] ?? '');

        if ($name === '')                   $errors[] = 'Venue name is required.';
        if ($location === '')               $errors[] = 'Location is required.';
        if (!$capacity || $capacity < 1)    $errors[] = 'A valid capacity is required.';
        if ($price === false || $price < 0) $errors[] = 'A valid price is required.';

        // Cover image
        $coverUrl = handleImageUpload('image', $errors);

        // Gallery images (multiple)
        $galleryUrls = [];
        if (!empty($_FILES['gallery_images']['name'][0])) {
            $fileCount = count($_FILES['gallery_images']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['gallery_images']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                // Reconstruct single-file array for the helper
                $_FILES['_gimg_tmp'] = [
                    'name'     => $_FILES['gallery_images']['name'][$i],
                    'type'     => $_FILES['gallery_images']['type'][$i],
                    'tmp_name' => $_FILES['gallery_images']['tmp_name'][$i],
                    'error'    => $_FILES['gallery_images']['error'][$i],
                    'size'     => $_FILES['gallery_images']['size'][$i],
                ];
                $url = handleImageUpload('_gimg_tmp', $errors);
                if ($url) $galleryUrls[] = $url;
            }
        }

        // Gallery labels sent as parallel array
        $galleryLabels = $_POST['gallery_labels'] ?? [];

        // Amenities: array of "icon|label" strings
        $rawAmenities = $_POST['amenities'] ?? [];
        $amenities = array_values(array_filter(array_map('trim', $rawAmenities)));

        // Gallery images to delete (admin ticked remove on existing ones)
        $galleryToRemove = array_filter(array_map('intval', $_POST['gallery_remove'] ?? []));

        if (empty($errors)) {
            /* ── UPDATE existing venue ── */
            if ($id > 0) {
                if ($coverUrl !== null) {
                    // Delete old cover image
                    $oldRow = $conn->query("SELECT image_url FROM venues WHERE id = $id")->fetch_assoc();
                    if ($oldRow) safeDeleteImage($oldRow['image_url']);

                    $stmt = $conn->prepare("UPDATE venues SET name=?,location=?,capacity=?,price=?,description=?,image_url=? WHERE id=?");
                    $stmt->bind_param('ssidssi', $name, $location, $capacity, $price, $description, $coverUrl, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE venues SET name=?,location=?,capacity=?,price=?,description=? WHERE id=?");
                    $stmt->bind_param('ssidsi', $name, $location, $capacity, $price, $description, $id);
                }
                $stmt->execute();
                $stmt->close();

                // Remove gallery images the admin ticked for deletion
                foreach ($galleryToRemove as $gid) {
                    $grow = $conn->query("SELECT image_url FROM venue_gallery WHERE gallery_id = $gid AND venue_id = $id")->fetch_assoc();
                    if ($grow) {
                        safeDeleteImage($grow['image_url']);
                        $conn->query("DELETE FROM venue_gallery WHERE gallery_id = $gid");
                    }
                }

                $flash = 'Venue updated successfully.';

            /* ── INSERT new venue ── */
            } else {
                $coverUrl = $coverUrl ?? 'assets/images/default-venue.jpg';
                $stmt = $conn->prepare("INSERT INTO venues (name,location,capacity,price,description,image_url) VALUES (?,?,?,?,?,?)");
                $stmt->bind_param('ssidss', $name, $location, $capacity, $price, $description, $coverUrl);
                $stmt->execute();
                $id = (int)$conn->insert_id;
                $stmt->close();
                $flash = 'Venue added successfully.';
            }

            /* ── Sync amenities (delete all, re-insert) ── */
            $del = $conn->prepare("DELETE FROM amenities WHERE venue_id = ?");
            $del->bind_param('i', $id);
            $del->execute();
            $del->close();

            if (!empty($amenities)) {
                $ins = $conn->prepare("INSERT INTO amenities (venue_id, amenity_name) VALUES (?,?)");
                foreach ($amenities as $amenity) {
                    $amenity = substr($amenity, 0, 100);
                    $ins->bind_param('is', $id, $amenity);
                    $ins->execute();
                }
                $ins->close();
            }

            /* ── Insert new gallery images ── */
            if (!empty($galleryUrls)) {
                // Determine next sort_order
                $maxOrder = (int)$conn->query("SELECT COALESCE(MAX(sort_order),0) FROM venue_gallery WHERE venue_id = $id")->fetch_row()[0];
                $ins = $conn->prepare("INSERT INTO venue_gallery (venue_id, image_url, label, sort_order) VALUES (?,?,?,?)");
                foreach ($galleryUrls as $i => $gUrl) {
                    $gLabel = trim($galleryLabels[$i] ?? '');
                    $gLabel = $gLabel !== '' ? substr($gLabel, 0, 100) : null;
                    $order  = ++$maxOrder;
                    $ins->bind_param('issi', $id, $gUrl, $gLabel, $order);
                    $ins->execute();
                }
                $ins->close();
            }

            /* ── Sync highlights ("Why This Venue") ── */
            $rawHighlights = $_POST['highlights'] ?? [];
            $highlights = array_values(array_filter(array_map('trim', $rawHighlights)));

            $del = $conn->prepare("DELETE FROM venue_highlights WHERE venue_id = ?");
            $del->bind_param('i', $id);
            $del->execute();
            $del->close();

            if (!empty($highlights)) {
                $ins = $conn->prepare("INSERT INTO venue_highlights (venue_id, highlight, sort_order) VALUES (?,?,?)");
                foreach ($highlights as $idx => $hl) {
                    $hl    = substr($hl, 0, 255);
                    $order = $idx + 1;
                    $ins->bind_param('isi', $id, $hl, $order);
                    $ins->execute();
                }
                $ins->close();
            }
        }
    }
}

/* ── Fetch venues + amenities + gallery + booking counts ─────────── */
$showArchived = isset($_GET['show_archived']) && $_GET['show_archived'] === '1';
$archivedFilter = $showArchived ? '1' : '0';
$venues = $conn->query("SELECT * FROM venues WHERE archived = $archivedFilter ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

$venueIds = array_column($venues, 'id');
$amenitiesByVenue     = [];
$galleryByVenue       = [];
$highlightsByVenue    = [];
$bookingCountByVenue  = [];

if (!empty($venueIds)) {
    $ph    = implode(',', array_fill(0, count($venueIds), '?'));
    $types = str_repeat('i', count($venueIds));

    $stmt = $conn->prepare("SELECT venue_id, amenity_name FROM amenities WHERE venue_id IN ($ph)");
    $stmt->bind_param($types, ...$venueIds);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $amenitiesByVenue[$row['venue_id']][] = $row['amenity_name'];
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT gallery_id, venue_id, image_url, label, sort_order FROM venue_gallery WHERE venue_id IN ($ph) ORDER BY sort_order ASC");
    $stmt->bind_param($types, ...$venueIds);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $galleryByVenue[$row['venue_id']][] = $row;
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT venue_id, highlight FROM venue_highlights WHERE venue_id IN ($ph) ORDER BY sort_order ASC");
    $stmt->bind_param($types, ...$venueIds);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $highlightsByVenue[$row['venue_id']][] = $row['highlight'];
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT venue_id, COUNT(*) AS c FROM bookings WHERE venue_id IN ($ph) GROUP BY venue_id");
    $stmt->bind_param($types, ...$venueIds);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $bookingCountByVenue[$row['venue_id']] = (int)$row['c'];
    }
    $stmt->close();
}

$totalVenues   = count($venues);
$totalCapacity = array_sum(array_column($venues, 'capacity'));
$avgPrice      = $totalVenues > 0 ? array_sum(array_column($venues, 'price')) / $totalVenues : 0;
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
      <div style="display:flex;gap:8px;align-items:center;">
        <button class="btn-action btn-primary-green" onclick="openVenueDrawer()">
          <i class="bi bi-plus-lg"></i> Add Venue
        </button>
        <?php if (!empty($showArchived)): ?>
          <a class="btn-action btn-outline-gray" href="manage-venues.php">Show Active</a>
        <?php else: ?>
          <a class="btn-action btn-outline-gray" href="manage-venues.php?show_archived=1">Show Archived</a>
        <?php endif; ?>
      </div>
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
                    <?php
                    // Build the data payload for the edit drawer
                    $editPayload = [
                        'id'          => $v['id'],
                        'name'        => $v['name'],
                        'location'    => $v['location'],
                        'capacity'    => $v['capacity'],
                        'price'       => $v['price'],
                        'description' => $v['description'],
                        'image_url'   => $v['image_url'],
                        'amenities'   => $amenitiesByVenue[$v['id']]  ?? [],
                        'gallery'     => $galleryByVenue[$v['id']]    ?? [],
                        'highlights'  => $highlightsByVenue[$v['id']] ?? [],
                    ];
                    ?>
                    <button class="icon-btn" title="Edit"
                      onclick='openVenueDrawer(<?= json_encode($editPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                      <i class="bi bi-pencil"></i>
                    </button>

                    <?php if (!empty($showArchived)): ?>
                      <form method="post" onsubmit="return confirm('Restore this venue?');" style="display:inline;">
                        <input type="hidden" name="form_action" value="restore">
                        <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                        <button type="submit" class="icon-btn" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button>
                      </form>
                    <?php else: ?>
                      <form method="post" onsubmit="return confirm('Archive this venue?');" style="display:inline;">
                        <input type="hidden" name="form_action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                        <button type="submit" class="icon-btn" title="Archive"><i class="bi bi-trash"></i></button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <div class="pagination-bar">
        <span>Showing <?= $totalVenues ?> venue<?= $totalVenues !== 1 ? 's' : '' ?></span>
      </div>
    </div>

  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     Add / Edit Side Drawer
     Structure: .side-drawer > drawer-header + .drawer-form
                .drawer-form > .drawer-body (scrolls) + .drawer-footer (pinned)
     ═══════════════════════════════════════════════════════ -->
<div class="side-drawer-overlay" id="drawerOverlay" onclick="closeVenueDrawer()"></div>

<div class="side-drawer" id="venueDrawer">

  <!-- Header — never scrolls -->
  <div class="drawer-header">
    <div>
      <h3 id="venueDrawerTitle">Add Venue</h3>
      <p>Fill in the venue details below.</p>
    </div>
    <button class="drawer-close" onclick="closeVenueDrawer()"><i class="bi bi-x-lg"></i></button>
  </div>

  <!-- Form wraps scrollable body + pinned footer -->
  <form method="post" enctype="multipart/form-data" id="venueForm" class="drawer-form">
    <input type="hidden" name="form_action" value="save">
    <input type="hidden" name="id" id="venueId" value="0">

    <!-- ↓ Scrollable body -->
    <div class="drawer-body">

      <!-- Basic fields -->
      <div class="mb-3">
        <label class="form-label-sm">Venue Name <span style="color:var(--danger)">*</span></label>
        <input type="text" name="name" id="venueName" class="form-ctrl" required>
      </div>
      <div class="mb-3">
        <label class="form-label-sm">Location <span style="color:var(--danger)">*</span></label>
        <input type="text" name="location" id="venueLocation" class="form-ctrl" required>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-6">
          <label class="form-label-sm">Capacity <span style="color:var(--danger)">*</span></label>
          <input type="number" name="capacity" id="venueCapacity" class="form-ctrl" min="1" required>
        </div>
        <div class="col-6">
          <label class="form-label-sm">Price (&#8369;) <span style="color:var(--danger)">*</span></label>
          <input type="number" step="0.01" name="price" id="venuePrice" class="form-ctrl" min="0" required>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label-sm">Description</label>
        <textarea name="description" id="venueDescription" class="form-ctrl" rows="4"></textarea>
      </div>

      <!-- Cover Photo -->
      <div class="mb-3">
        <label class="form-label-sm">Cover Photo</label>
        <input type="file" name="image" id="venueCoverInput" accept="image/*" class="form-ctrl">
        <div class="text-muted-sm mt-1">Leave empty to keep the current image when editing.</div>
        <img id="venueImagePreview" src="" alt=""
             style="display:none;max-width:100%;border-radius:8px;margin-top:10px;max-height:160px;object-fit:cover;">
      </div>

      <!-- Gallery Images -->
      <div class="mb-3">
        <label class="form-label-sm">Gallery Images</label>
        <div class="text-muted-sm mt-1" style="margin-bottom:8px;">
          Existing gallery images are shown below — tick to remove. Upload new ones to add.
        </div>

        <!-- Existing gallery thumbnails (populated by JS when editing) -->
        <div class="gallery-thumb-strip" id="existingGallery"></div>

        <!-- New gallery uploads -->
        <div id="newGalleryRows" style="display:flex;flex-direction:column;gap:8px;margin-top:10px;"></div>
        <button type="button" class="btn-action btn-outline-gray mt-1" onclick="addGalleryRow()" style="font-size:12px;">
          <i class="bi bi-plus-lg"></i> Add Gallery Image
        </button>
      </div>

      <!-- Amenities -->
      <div class="mb-3">
        <label class="form-label-sm">Amenities</label>
        <div id="amenityList" style="display:flex;flex-direction:column;gap:6px;margin-bottom:8px;"></div>
        <div class="d-flex gap-2">
          <input type="text" id="amenityIcon"  class="form-ctrl" placeholder="Icon e.g. 🅿️" style="width:80px;flex-shrink:0;">
          <input type="text" id="amenityLabel" class="form-ctrl" placeholder="Label e.g. Free Parking">
          <button type="button" class="btn-action btn-primary-green" onclick="addAmenityRow()" style="white-space:nowrap;padding:0 12px;">+ Add</button>
        </div>
        <div class="text-muted-sm mt-1">Format: <code>icon|label</code> — enter them separately above.</div>
      </div>

      <!-- Why This Venue highlights -->
      <div class="mb-3">
        <label class="form-label-sm">Why This Venue (Highlights)</label>
        <div id="highlightList" style="display:flex;flex-direction:column;gap:6px;margin-bottom:8px;"></div>
        <div class="d-flex gap-2">
          <input type="text" id="highlightInput" class="form-ctrl" placeholder="e.g. Easy access to major highways">
          <button type="button" class="btn-action btn-primary-green" onclick="addHighlightRow()" style="white-space:nowrap;padding:0 12px;">+ Add</button>
        </div>
        <div class="text-muted-sm mt-1">Bullet points shown in the "Why This Venue" card on the frontend.</div>
      </div>

    </div><!-- end .drawer-body -->

    <!-- ↓ Pinned footer — always visible -->
    <div class="drawer-footer">
      <button type="submit" class="btn-full btn-full-green"><i class="bi bi-save me-1"></i> Save Venue</button>
      <button type="button" class="btn-full btn-full-outline" onclick="closeVenueDrawer()">Cancel</button>
    </div>

  </form>
</div><!-- end .side-drawer -->

<script>
/* ══════════════════════════════════════════════════════════════
   AMENITY HELPERS
   ══════════════════════════════════════════════════════════════ */
function renderAmenityRow(icon, label) {
  const list = document.getElementById('amenityList');
  const row  = document.createElement('div');
  row.style.cssText = 'display:flex;align-items:center;gap:6px;';
  const val = (icon + '|' + label).replace(/"/g, '&quot;');
  row.innerHTML = `
    <input type="hidden" name="amenities[]" value="${val}">
    <span style="font-size:1.2em;min-width:28px;text-align:center;">${icon}</span>
    <span style="flex:1;font-size:13px;">${label}</span>
    <button type="button" onclick="this.closest('div').remove()"
      style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:1.1em;" title="Remove">✕</button>`;
  list.appendChild(row);
}

function addAmenityRow() {
  const icon  = document.getElementById('amenityIcon').value.trim();
  const label = document.getElementById('amenityLabel').value.trim();
  if (!label) return;
  renderAmenityRow(icon || '📌', label);
  document.getElementById('amenityIcon').value  = '';
  document.getElementById('amenityLabel').value = '';
}

/* ══════════════════════════════════════════════════════════════
   HIGHLIGHT HELPERS
   ══════════════════════════════════════════════════════════════ */
function renderHighlightRow(text) {
  const list = document.getElementById('highlightList');
  const row  = document.createElement('div');
  row.style.cssText = 'display:flex;align-items:center;gap:6px;';
  const safeText = text.replace(/"/g, '&quot;');
  row.innerHTML = `
    <input type="hidden" name="highlights[]" value="${safeText}">
    <span style="flex:1;font-size:13px;">✦ ${text}</span>
    <button type="button" onclick="this.closest('div').remove()"
      style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:1.1em;" title="Remove">✕</button>`;
  list.appendChild(row);
}

function addHighlightRow() {
  const input = document.getElementById('highlightInput');
  const text  = input.value.trim();
  if (!text) return;
  renderHighlightRow(text);
  input.value = '';
}

/* ══════════════════════════════════════════════════════════════
   GALLERY HELPERS
   ══════════════════════════════════════════════════════════════ */

// Render existing gallery thumbs with a "remove" checkbox
function renderExistingGallery(galleryItems) {
  const strip = document.getElementById('existingGallery');
  strip.innerHTML = '';
  if (!galleryItems || galleryItems.length === 0) {
    strip.innerHTML = '<span class="text-muted-sm">No gallery images yet.</span>';
    return;
  }
  galleryItems.forEach(function(img) {
    const item = document.createElement('div');
    item.className = 'gallery-thumb-item';
    item.title = img.label || '';
    item.innerHTML = `
      <img src="../${img.image_url}" alt="${img.label || ''}">
      <button type="button" class="gallery-thumb-remove" title="Remove this image"
        onclick="markGalleryForRemoval(this, ${img.gallery_id})">✕</button>
      <input type="hidden" name="gallery_keep[]" value="${img.gallery_id}">`;
    strip.appendChild(item);
  });
}

function markGalleryForRemoval(btn, galleryId) {
  const item  = btn.closest('.gallery-thumb-item');
  const input = item.querySelector('input[type=hidden]');
  // Toggle: if already marked, un-mark
  const existing = item.querySelector('input[name="gallery_remove[]"]');
  if (existing) {
    existing.remove();
    item.style.opacity = '1';
    item.style.outline = '';
    btn.style.background = 'rgba(0,0,0,.6)';
  } else {
    const del = document.createElement('input');
    del.type  = 'hidden';
    del.name  = 'gallery_remove[]';
    del.value = galleryId;
    item.appendChild(del);
    item.style.opacity = '0.4';
    item.style.outline = '2px solid var(--danger)';
    btn.style.background = 'var(--danger)';
  }
}

// Add a row for a new gallery image upload
let galleryRowIndex = 0;
function addGalleryRow() {
  const idx = galleryRowIndex++;
  const container = document.getElementById('newGalleryRows');
  const row = document.createElement('div');
  row.style.cssText = 'display:flex;align-items:center;gap:6px;';
  row.innerHTML = `
    <input type="file" name="gallery_images[${idx}]" accept="image/*"
           class="form-ctrl" style="flex:1;" onchange="previewGalleryThumb(this, ${idx})">
    <input type="text"  name="gallery_labels[${idx}]" class="form-ctrl" placeholder="Label (optional)" style="width:130px;flex-shrink:0;">
    <button type="button" onclick="this.closest('div').remove()"
      style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:1.2em;" title="Remove">✕</button>`;
  container.appendChild(row);
}

// NOTE: gallery images use array notation with explicit index.
// We rewrite the name attributes to be a clean numeric array on the server.
// To simplify server-side handling we use a flat multi-file field instead:
// The form uses name="gallery_images[]" and name="gallery_labels[]".
// Let's override addGalleryRow to use that simpler approach.
(function() {
  document.getElementById('newGalleryRows').innerHTML = ''; // clear any old rows
  galleryRowIndex = 0;
  window.addGalleryRow = function() {
    const container = document.getElementById('newGalleryRows');
    const row = document.createElement('div');
    row.style.cssText = 'display:flex;align-items:center;gap:6px;';
    row.innerHTML = `
      <input type="file" name="gallery_images[]" accept="image/*"
             class="form-ctrl" style="flex:1;">
      <input type="text" name="gallery_labels[]" class="form-ctrl" placeholder="Label (optional)" style="width:130px;flex-shrink:0;">
      <button type="button" onclick="this.closest('div').remove()"
        style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:1.2em;" title="Remove">✕</button>`;
    container.appendChild(row);
  };
})();

/* ══════════════════════════════════════════════════════════════
   COVER PHOTO PREVIEW
   ══════════════════════════════════════════════════════════════ */
document.getElementById('venueCoverInput').addEventListener('change', function() {
  const preview = document.getElementById('venueImagePreview');
  if (this.files && this.files[0]) {
    const reader = new FileReader();
    reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
    reader.readAsDataURL(this.files[0]);
  }
});

/* ══════════════════════════════════════════════════════════════
   DRAWER OPEN / CLOSE
   ══════════════════════════════════════════════════════════════ */
function openVenueDrawer(venue) {
  const preview      = document.getElementById('venueImagePreview');
  const amenityList  = document.getElementById('amenityList');
  const highlightList= document.getElementById('highlightList');
  const newGallery   = document.getElementById('newGalleryRows');

  // Reset form and lists
  document.getElementById('venueForm').reset();
  amenityList.innerHTML   = '';
  highlightList.innerHTML = '';
  newGallery.innerHTML    = '';
  preview.style.display   = 'none';
  preview.src = '';

  if (venue) {
    /* ── EDIT mode ── */
    document.getElementById('venueDrawerTitle').textContent = 'Edit Venue';
    document.getElementById('venueId').value            = venue.id;
    document.getElementById('venueName').value          = venue.name;
    document.getElementById('venueLocation').value      = venue.location;
    document.getElementById('venueCapacity').value      = venue.capacity;
    document.getElementById('venuePrice').value         = venue.price;
    document.getElementById('venueDescription').value   = venue.description;

    // Cover image preview
    if (venue.image_url) {
      preview.src           = '../' + venue.image_url;
      preview.style.display = 'block';
    }

    // Existing gallery
    renderExistingGallery(venue.gallery || []);

    // Amenities
    (venue.amenities || []).forEach(function(a) {
      if (a.includes('|')) {
        const parts = a.split('|');
        renderAmenityRow(parts[0].trim(), parts.slice(1).join('|').trim());
      } else {
        renderAmenityRow('📌', a);
      }
    });

    // Highlights
    (venue.highlights || []).forEach(function(h) { renderHighlightRow(h); });

  } else {
    /* ── ADD mode ── */
    document.getElementById('venueDrawerTitle').textContent = 'Add Venue';
    document.getElementById('venueId').value = 0;
    document.getElementById('existingGallery').innerHTML =
      '<span class="text-muted-sm">No gallery images yet.</span>';
  }

  document.getElementById('venueDrawer').classList.add('open');
  document.getElementById('drawerOverlay').classList.add('open');
}

function closeVenueDrawer() {
  document.getElementById('venueDrawer').classList.remove('open');
  document.getElementById('drawerOverlay').classList.remove('open');
}
</script>

</body>
</html>