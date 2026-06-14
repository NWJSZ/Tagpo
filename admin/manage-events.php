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
$currentPage = 'events';
$msg = '';

/* ── 1. PHP ACTION PROCESSING (Add, Edit, Delete) ────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // A. ADD EVENT TYPE
    if ($action === 'add_event') {
        $name = trim($_POST['event_name'] ?? '');
        if ($name !== '') {
            $stmt = $conn->prepare("INSERT INTO event (event_name) VALUES (?)");
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $stmt->close();
            $msg = "Event Type successfully added!";
        }
    }

    // B. EDIT EVENT TYPE
    if ($action === 'edit_event') {
        $id = (int)($_POST['event_id'] ?? 0);
        $name = trim($_POST['event_name'] ?? '');
        if ($id > 0 && $name !== '') {
            $stmt = $conn->prepare("UPDATE event SET event_name = ? WHERE event_id = ?");
            $stmt->bind_param('si', $name, $id);
            $stmt->execute();
            $stmt->close();
            $msg = "Event Type successfully updated!";
        }
    }

    // C. DELETE EVENT TYPE (Soft Delete - Archive)
    if ($action === 'delete_event') {
        $id = (int)($_POST['event_id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE event SET archived = 1 WHERE event_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $msg = "Event Type successfully archived!";
        }
    }

    // D. ADD ADD-ON (Tied to an Event Type)
    if ($action === 'add_addon') {
        $event_id = (int)($_POST['event_id'] ?? 0);
        $name = trim($_POST['addon_name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        if ($name !== '' && $event_id > 0) {
            $stmt = $conn->prepare("INSERT INTO addons (event_id, addon_name, price) VALUES (?, ?, ?)");
            $stmt->bind_param('isd', $event_id, $name, $price);
            $stmt->execute();
            $stmt->close();
            $msg = "Add-on successfully added to the event category!";
        }
    }

    // E. EDIT ADD-ON
    if ($action === 'edit_addon') {
        $id = (int)($_POST['addon_id'] ?? 0);
        $event_id = (int)($_POST['event_id'] ?? 0);
        $name = trim($_POST['addon_name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        if ($id > 0 && $name !== '' && $event_id > 0) {
            $stmt = $conn->prepare("UPDATE addons SET event_id = ?, addon_name = ?, price = ? WHERE addon_id = ?");
            $stmt->bind_param('isdi', $event_id, $name, $price, $id);
            $stmt->execute();
            $stmt->close();
            $msg = "Add-on successfully updated!";
        }
    }

    // F. DELETE ADD-ON (Soft Delete - Archive)
    if ($action === 'delete_addon') {
        $id = (int)($_POST['addon_id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE addons SET archived = 1 WHERE addon_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $msg = "Add-on successfully archived!";
        }
    }
}

/* ── 2. FETCH DATA FOR EVENT-CENTRIC DISPLAY ─────────────────────── */
$events = $conn->query("SELECT event_id, event_name FROM event WHERE archived = 0 ORDER BY event_name ASC")->fetch_all(MYSQLI_ASSOC);

// Kinukuha natin ang addons at igru-grupo natin base sa kanilang event_id
$addons_raw = $conn->query("
    SELECT addon_id, event_id, addon_name, price 
    FROM addons 
    WHERE archived = 0
    ORDER BY event_id ASC, addon_name ASC
")->fetch_all(MYSQLI_ASSOC);

$addons_by_event = [];
foreach ($addons_raw as $addon) {
    $addons_by_event[$addon['event_id']][] = $addon;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Events & Add-ons | Tagpo Admin</title>
  <?php include 'includes/admin_style.php'; ?>
  <style>
    .event-card-wrapper {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 24px;
        margin-bottom: 24px;
        box-shadow: var(--shadow);
    }
    .event-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid var(--border);
        padding-bottom: 14px;
        margin-bottom: 18px;
    }
    .event-card-header h2 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .event-card-header h2 i { color: var(--green); }

    .addon-table { width: 100%; border-collapse: collapse; }
    .addon-table th { text-align: left; padding: 10px; font-size: 13px; font-weight: 600; color: var(--muted); background: var(--bg); border-radius: var(--radius); }
    .addon-table td { padding: 12px 10px; border-bottom: 1px solid var(--border); font-size: 14px; vertical-align: middle; }
    .addon-table tr:last-child td { border-bottom: none; }

    .custom-modal { 
        display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
        background: rgba(0,0,0,0.4); z-index: 1000; justify-content: center; align-items: center; backdrop-filter: blur(2px);
    }
    .modal-content { 
        background: var(--surface); padding: 24px; border-radius: var(--radius-lg); width: 100%; max-width: 440px; 
        box-shadow: var(--shadow-md); border: 1px solid var(--border);
    }
    .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 12px; margin-bottom: 20px; }
    .modal-header h3 { font-size: 16px; font-weight: 700; margin: 0; }
    .modal-close { background: transparent; border: none; font-size: 22px; cursor: pointer; color: var(--muted); }
    .top-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; flex-wrap: wrap; gap: 15px; }
  </style>
</head>
<body>

<?php include 'includes/admin_sidebar.php'; ?>

<div class="admin-main">
  <header class="admin-topbar">
    <div class="topbar-title">Event Service Configurations</div>
    <div class="topbar-right">
      <div class="topbar-date"><i class="bi bi-calendar3"></i> <?= date('M j, Y (D)') ?></div>
      <div class="topbar-avatar"><?= htmlspecialchars(strtoupper(substr($currentUser['first_name'] ?? 'A', 0, 1))) ?></div>
    </div>
  </header>

  <div class="admin-content">
    <?php if ($msg !== ''): ?>
      <div class="alert-bar success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="top-controls">
      <div>
        <h1 style="margin:0; font-size:24px; font-weight:700;">Events & Add-ons</h1>
        <p style="margin:4px 0 0 0; color:var(--muted); font-size:13.5px;">Manage global event styles and their dedicated sub-services (applicable across all 3 venues).</p>
      </div>
      <div>
        <button class="btn-action btn-primary-green" onclick="openModal('addEventModal')"><i class="bi bi-plus-lg"></i> New Event Category</button>
      </div>
    </div>

    <?php if (empty($events)): ?>
      <div class="panel-card" style="padding: 40px; text-align: center; color: var(--muted);">
        <i class="bi bi-card-list" style="font-size: 48px; color: var(--border);"></i>
        <p class="mt-2">No event categories established yet.</p>
      </div>
    <?php else: ?>
      
      <?php foreach ($events as $e): ?>
        <div class="event-card-wrapper">
          <div class="event-card-header">
            <h2>
              <i class="bi bi-stars"></i> <?= htmlspecialchars($e['event_name']) ?>
            </h2>
            <div class="d-flex gap-2">
              <button class="btn-action btn-outline-green" style="font-size:12px; padding: 5px 12px;" onclick="triggerAddAddon(<?= $e['event_id'] ?>)"><i class="bi bi-plus-lg"></i> Add Add-on</button>
              <button class="icon-btn" onclick="openEditEvent(<?= $e['event_id'] ?>, '<?= htmlspecialchars($e['event_name'], ENT_QUOTES) ?>')"><i class="bi bi-pencil"></i></button>
              <button class="icon-btn" style="color:var(--danger);" onclick="openDeleteEvent(<?= $e['event_id'] ?>)"><i class="bi bi-trash"></i></button>
            </div>
          </div>

          <?php 
          $current_addons = $addons_by_event[$e['event_id']] ?? [];
          if (empty($current_addons)): 
          ?>
            <div class="text-center py-3 text-muted" style="font-size:13px; font-style:italic;">
                No add-ons created for this event style yet.
            </div>
          <?php else: ?>
            <table class="addon-table">
              <thead>
                <tr>
                  <th>Add-on Name</th>
                  <th>Standard Price</th>
                  <th style="text-align: right; padding-right: 15px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($current_addons as $a): ?>
                  <tr>
                    <td class="fw-500 text-dark" style="width: 60%;"><?= htmlspecialchars($a['addon_name']) ?></td>
                    <td><span class="badge-status badge-active">₱<?= number_format($a['price'], 2) ?></span></td>
                    <td style="text-align:right; padding-right: 15px;">
                      <div class="d-flex gap-2 justify-content-end">
                        <button class="icon-btn" onclick="openEditAddon(<?= $a['addon_id'] ?>, '<?= htmlspecialchars($a['addon_name'], ENT_QUOTES) ?>', <?= $a['price'] ?>, <?= $a['event_id'] ?>)"><i class="bi bi-pencil-square"></i></button>
                        <button class="icon-btn" style="color:var(--danger);" onclick="openDeleteAddon(<?= $a['addon_id'] ?>)"><i class="bi bi-trash"></i></button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>

        </div>
      <?php endforeach; ?>

    <?php endif; ?>
  </div>
</div>

<div class="custom-modal" id="addEventModal">
  <div class="modal-content">
    <div class="modal-header"><h3>Add New Event Category</h3><button class="modal-close" onclick="closeModal('addEventModal')">&times;</button></div>
    <form method="POST"><input type="hidden" name="action" value="add_event">
      <div class="mb-3"><label class="form-label-sm">Event Name</label><input type="text" class="form-ctrl" name="event_name" required placeholder="e.g. Wedding, Birthday"></div>
      <div class="d-flex justify-content-end gap-2 mt-4"><button type="button" class="btn-action btn-outline-gray" onclick="closeModal('addEventModal')">Cancel</button><button type="submit" class="btn-action btn-primary-green">Save Category</button></div>
    </form>
  </div>
</div>

<div class="custom-modal" id="editEventModal">
  <div class="modal-content">
    <div class="modal-header"><h3>Edit Event Title</h3><button class="modal-close" onclick="closeModal('editEventModal')">&times;</button></div>
    <form method="POST"><input type="hidden" name="action" value="edit_event"><input type="hidden" name="event_id" id="edit_ev_id">
      <div class="mb-3"><label class="form-label-sm">Event Name</label><input type="text" class="form-ctrl" name="event_name" id="edit_ev_name" required></div>
      <div class="d-flex justify-content-end gap-2 mt-4"><button type="button" class="btn-action btn-outline-gray" onclick="closeModal('editEventModal')">Cancel</button><button type="submit" class="btn-action btn-primary-green">Update Name</button></div>
    </form>
  </div>
</div>

<div class="custom-modal" id="addAddonModal">
  <div class="modal-content">
    <div class="modal-header"><h3>Add New Add-on</h3><button class="modal-close" onclick="closeModal('addAddonModal')">&times;</button></div>
    <form method="POST"><input type="hidden" name="action" value="add_addon"><input type="hidden" name="event_id" id="add_addon_event_id">
      <div class="mb-3"><label class="form-label-sm">Add-on Name</label><input type="text" class="form-ctrl" name="addon_name" required placeholder="e.g. Photo Booth, Catering Combo"></div>
      <div class="mb-3"><label class="form-label-sm">Price (₱)</label><input type="number" class="form-ctrl" name="price" step="0.01" required placeholder="0.00"></div>
      <div class="d-flex justify-content-end gap-2 mt-4"><button type="button" class="btn-action btn-outline-gray" onclick="closeModal('addAddonModal')">Cancel</button><button type="submit" class="btn-action btn-primary-green">Save Add-on</button></div>
    </form>
  </div>
</div>

<div class="custom-modal" id="editAddonModal">
  <div class="modal-content">
    <div class="modal-header"><h3>Edit Add-on Details</h3><button class="modal-close" onclick="closeModal('editAddonModal')">&times;</button></div>
    <form method="POST"><input type="hidden" name="action" value="edit_addon"><input type="hidden" name="addon_id" id="edit_ad_id">
      <div class="mb-3">
        <label class="form-label-sm">Move to Event Category</label>
        <select name="event_id" id="edit_ad_event_id" class="form-ctrl" required>
          <?php foreach ($events as $e): ?>
            <option value="<?= $e['event_id'] ?>"><?= htmlspecialchars($e['event_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3"><label class="form-label-sm">Add-on Name</label><input type="text" class="form-ctrl" name="addon_name" id="edit_ad_name" required></div>
      <div class="mb-3"><label class="form-label-sm">Price (₱)</label><input type="number" class="form-ctrl" name="price" id="edit_ad_price" step="0.01" required></div>
      <div class="d-flex justify-content-end gap-2 mt-4"><button type="button" class="btn-action btn-outline-gray" onclick="closeModal('editAddonModal')">Cancel</button><button type="submit" class="btn-action btn-primary-green">Update Add-on</button></div>
    </form>
  </div>
</div>

<form id="deleteEventForm" method="POST" style="display:none;"><input type="hidden" name="action" value="delete_event"><input type="hidden" name="event_id" id="delete_ev_id"></form>
<form id="deleteAddonForm" method="POST" style="display:none;"><input type="hidden" name="action" value="delete_addon"><input type="hidden" name="addon_id" id="delete_ad_id"></form>

<script>
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

function openEditEvent(id, name) {
  document.getElementById('edit_ev_id').value = id;
  document.getElementById('edit_ev_name').value = name;
  openModal('editEventModal');
}

function openDeleteEvent(id) {
  if (confirm('Are you sure you want to delete this event category? Doing so will permanently delete all associated add-ons.')) {
    document.getElementById('delete_ev_id').value = id;
    document.getElementById('deleteEventForm').submit();
  }
}

function triggerAddAddon(eventId) {
    document.getElementById('add_addon_event_id').value = eventId;
    openModal('addAddonModal');
}

function openEditAddon(id, name, price, eventId) {
  document.getElementById('edit_ad_id').value = id;
  document.getElementById('edit_ad_name').value = name;
  document.getElementById('edit_ad_price').value = price;
  document.getElementById('edit_ad_event_id').value = eventId;
  openModal('editAddonModal');
}

function openDeleteAddon(id) {
  if (confirm('Are you sure you want to delete this add-on item?')) {
    document.getElementById('delete_ad_id').value = id;
    document.getElementById('deleteAddonForm').submit();
  }
}
</script>

</body>
</html>