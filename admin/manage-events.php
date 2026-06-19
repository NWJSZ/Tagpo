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

/* ── 1. PHP ACTION PROCESSING (Add, Edit, Archive, Restore) ────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // A. ADD EVENT TYPE
    if ($action === 'add_event') {
        $name = trim($_POST['event_name'] ?? '');
        if ($name !== '') {
            $stmt = $conn->prepare("INSERT INTO event (event_name) VALUES (?)");
            $stmt->bind_param('s', $name);
            
            if ($stmt->execute()) {
                $msg = "Event Type successfully added!";
            } else {
                die("<div style='color:red; padding:20px; background:#fee; font-family:sans-serif;'><strong>MySQL Insert Error (Event):</strong> " . $stmt->error . "</div>");
            }
            $stmt->close();
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
            
            // Auto-archive associated addons dynamically
            $stmtAddon = $conn->prepare("UPDATE addons SET archived = 1 WHERE event_id = ?");
            $stmtAddon->bind_param('i', $id);
            $stmtAddon->execute();
            $stmtAddon->close();

            $msg = "Event Type and its associated add-ons successfully archived!";
        }
    }

    // NEW: RESTORE EVENT TYPE
    if ($action === 'restore_event') {
        $id = (int)($_POST['event_id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE event SET archived = 0 WHERE event_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $msg = "Event Type successfully restored!";
        }
    }

    // D. ADD ADD-ON
    if ($action === 'add_addon') {
        $event_id = (int)($_POST['event_id'] ?? 0);
        $name = trim($_POST['addon_name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        if ($name !== '' && $event_id > 0) {
            $stmt = $conn->prepare("INSERT INTO addons (event_id, addon_name, price) VALUES (?, ?, ?)");
            $stmt->bind_param('isd', $event_id, $name, $price);
            
            if ($stmt->execute()) {
                $msg = "Add-on successfully added to the event category!";
            } else {
                die("<div style='color:red; padding:20px; background:#fee; font-family:sans-serif;'><strong>MySQL Insert Error (Addon):</strong> " . $stmt->error . "<br><br><em>Paki-double check ang Trigger or database table structures mo.</em></div>");
            }
            $stmt->close();
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

    // NEW: RESTORE ADD-ON
    if ($action === 'restore_addon') {
        $id = (int)($_POST['addon_id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE addons SET archived = 0 WHERE addon_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $msg = "Add-on successfully restored!";
        }
    }
}

/* ── 2. FETCH ACTIVE DATA ─────────────────────── */
$events = $conn->query("SELECT event_id, event_name FROM event WHERE archived = 0 ORDER BY event_name ASC")->fetch_all(MYSQLI_ASSOC);

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

/* ── 3. FETCH ARCHIVED DATA FOR THE VIEW TOGGLE ─────────────────────── */
$archived_events = $conn->query("SELECT event_id, event_name FROM event WHERE archived = 1 ORDER BY event_name ASC")->fetch_all(MYSQLI_ASSOC);
$archived_addons = $conn->query("
    SELECT a.addon_id, a.addon_name, a.price, e.event_name 
    FROM addons a
    LEFT JOIN event e ON a.event_id = e.event_id
    WHERE a.archived = 1
    ORDER BY a.addon_name ASC
")->fetch_all(MYSQLI_ASSOC);
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
    .top-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; flex-wrap: wrap; gap: 15px; }
    
    .archive-section { display: none; background: #fafafa; border: 2px dashed #ccc; border-radius: var(--radius-lg); padding: 20px; margin-top: 20px; }
    .archive-section.show { display: block; }
    .badge-archived { background: #6c757d; color: #fff; padding: 3px 8px; font-size: 11px; border-radius: 4px; }
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
      <div class="d-flex gap-2">
        <button class="btn-action btn-outline-muted" onclick="toggleArchiveSection()" id="archiveToggleBtn">
          <i class="bi bi-archive-fill"></i> View Archive Vault
        </button>
        <button class="btn-action btn-primary-green" onclick="openEventDrawer('add')"><i class="bi bi-plus-lg"></i> New Event Category</button>
      </div>
    </div>

    <div class="archive-section" id="archiveVault">
      <h3 style="font-size: 16px; font-weight:700; color:#495057;" class="mb-3"><i class="bi bi-safe2"></i> Archived Content Recovery</h3>
      
      <div class="row" style="display: flex; gap: 20px; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 280px; background: #fff; padding: 15px; border-radius: var(--radius); box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
          <h4 style="font-size:13px; text-transform: uppercase; color:var(--muted);" class="mb-2">Archived Categories</h4>
          <?php if (empty($archived_events)): ?>
            <p style="font-size:12px; color:#999; font-style:italic;">No archived event types.</p>
          <?php else: ?>
            <table class="addon-table" style="font-size:13px;">
              <?php foreach ($archived_events as $ae): ?>
                <tr>
                  <td><s><?= htmlspecialchars($ae['event_name']) ?></s></td>
                  <td style="text-align: right;">
                    <button class="btn-action btn-outline-green" style="padding:2px 8px; font-size:11px;" onclick="submitRestore('event', <?= $ae['event_id'] ?>)">
                      <i class="bi bi-arrow-counterclockwise"></i> Restore
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </table>
          <?php endif; ?>
        </div>

        <div style="flex: 1; min-width: 320px; background: #fff; padding: 15px; border-radius: var(--radius); box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
          <h4 style="font-size:13px; text-transform: uppercase; color:var(--muted);" class="mb-2">Archived Individual Add-ons</h4>
          <?php if (empty($archived_addons)): ?>
            <p style="font-size:12px; color:#999; font-style:italic;">No archived add-ons.</p>
          <?php else: ?>
            <table class="addon-table" style="font-size:13px;">
              <thead>
                <tr>
                  <th>Add-on Name</th>
                  <th>Former Category</th>
                  <th style="text-align: right;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($archived_addons as $aa): ?>
                  <tr>
                    <td><s><?= htmlspecialchars($aa['addon_name']) ?></s></td>
                    <td><span class="badge-archived"><?= htmlspecialchars($aa['event_name'] ?? 'Unlinked') ?></span></td>
                    <td style="text-align: right;">
                      <button class="btn-action btn-outline-green" style="padding:2px 8px; font-size:11px;" onclick="submitRestore('addon', <?= $aa['addon_id'] ?>)">
                        <i class="bi bi-arrow-counterclockwise"></i> Restore
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
      <hr style="border: 0; border-top: 1px solid #ddd; margin: 15px 0;">
    </div>

    <?php if (empty($events)): ?>
      <div class="panel-card" style="padding: 40px; text-align: center; color: var(--muted);">
        <i class="bi bi-card-list" style="font-size: 48px; color: var(--border);"></i>
        <p class="mt-2">No active event categories established yet.</p>
      </div>
    <?php else: ?>
      
      <?php foreach ($events as $e): ?>
        <div class="event-card-wrapper">
          <div class="event-card-header">
            <h2>
              <i class="bi bi-stars"></i> <?= htmlspecialchars($e['event_name']) ?>
            </h2>
            <div class="d-flex gap-2">
              <button class="btn-action btn-outline-green" style="font-size:12px; padding: 5px 12px;" onclick="openAddonDrawer('add', null, null, null, <?= $e['event_id'] ?>)"><i class="bi bi-plus-lg"></i> Add Add-on</button>
              <button class="icon-btn" onclick="openEventDrawer('edit', <?= $e['event_id'] ?>, '<?= htmlspecialchars($e['event_name'], ENT_QUOTES) ?>')"><i class="bi bi-pencil"></i></button>
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
                        <button class="icon-btn" onclick="openAddonDrawer('edit', <?= $a['addon_id'] ?>, '<?= htmlspecialchars($a['addon_name'], ENT_QUOTES) ?>', <?= $a['price'] ?>, <?= $a['event_id'] ?>)"><i class="bi bi-pencil-square"></i></button>
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

<div class="side-drawer-overlay" id="drawerOverlay" onclick="closeAllDrawers()"></div>

<div class="side-drawer" id="eventDrawer">
  <div class="drawer-header">
    <div>
      <h3 id="eventDrawerTitle">Category Configuration</h3>
      <p style="margin:0; font-size:13px; color:var(--muted);">Global classification setups</p>
    </div>
    <button class="drawer-close" onclick="closeAllDrawers()"><i class="bi bi-x-lg"></i></button>
  </div>
  <div class="drawer-body">
    <form method="POST" id="eventForm">
      <input type="hidden" name="action" id="eventActionField" value="add_event">
      <input type="hidden" name="event_id" id="event_id_field">
      
      <div class="mb-3">
        <label class="form-label-sm">Event Category Name</label>
        <input type="text" class="form-ctrl" name="event_name" id="event_name_field" required placeholder="e.g. Wedding, Birthday, Corporate">
      </div>
    </form>
  </div>
  <div class="drawer-footer">
    <button type="button" class="btn-full btn-full-outline" onclick="closeAllDrawers()">Cancel</button>
    <button type="submit" form="eventForm" class="btn-full btn-full-green" id="eventSubmitBtn">Save Category</button>
  </div>
</div>

<div class="side-drawer" id="addonDrawer">
  <div class="drawer-header">
    <div>
      <h3 id="addonDrawerTitle">Add-on Item Configuration</h3>
      <p style="margin:0; font-size:13px; color:var(--muted);">Sub-service itemizations</p>
    </div>
    <button class="drawer-close" onclick="closeAllDrawers()"><i class="bi bi-x-lg"></i></button>
  </div>
  <div class="drawer-body">
    <form method="POST" id="addonForm">
      <input type="hidden" name="action" id="addonActionField" value="add_addon">
      <input type="hidden" name="addon_id" id="addon_id_field">
      
      <div class="mb-3" id="addonCategorySelectorBlock">
        <label class="form-label-sm">Linked Event Category</label>
        <select name="event_id" id="addon_event_id_field" class="form-ctrl" required>
          <?php foreach ($events as $e): ?>
            <option value="<?= $e['event_id'] ?>"><?= htmlspecialchars($e['event_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="mb-3">
        <label class="form-label-sm">Add-on Item Name</label>
        <input type="text" class="form-ctrl" name="addon_name" id="addon_name_field" required placeholder="e.g. Photo Booth Setup, Sound System Pro">
      </div>
      
      <div class="mb-3">
        <label class="form-label-sm">Standard Price (₱)</label>
        <input type="number" class="form-ctrl" name="price" id="addon_price_field" step="0.01" required placeholder="0.00">
      </div>
    </form>
  </div>
  <div class="drawer-footer">
    <button type="button" class="btn-full btn-full-outline" onclick="closeAllDrawers()">Cancel</button>
    <button type="submit" form="addonForm" class="btn-full btn-full-green" id="addonSubmitBtn">Save Add-on</button>
  </div>
</div>

<form id="deleteEventForm" method="POST" style="display:none;"><input type="hidden" name="action" value="delete_event"><input type="hidden" name="event_id" id="delete_ev_id"></form>
<form id="deleteAddonForm" method="POST" style="display:none;"><input type="hidden" name="action" value="delete_addon"><input type="hidden" name="addon_id" id="delete_ad_id"></form>
<form id="restoreForm" method="POST" style="display:none;"><input type="hidden" name="action" id="restoreAction"><input type="hidden" name="event_id" id="restoreEventId"><input type="hidden" name="addon_id" id="restoreAddonId"></form>

<script>
function closeAllDrawers() {
  document.getElementById('eventDrawer').classList.remove('open');
  document.getElementById('addonDrawer').classList.remove('open');
  document.getElementById('drawerOverlay').classList.remove('open');
}

function openEventDrawer(mode, id = null, name = '') {
  closeAllDrawers();
  if (mode === 'add') {
    document.getElementById('eventDrawerTitle').textContent = "Add New Event Category";
    document.getElementById('eventActionField').value       = "add_event";
    document.getElementById('event_id_field').value         = "";
    document.getElementById('event_name_field').value       = "";
    document.getElementById('eventSubmitBtn').textContent   = "Save Category";
  } else {
    document.getElementById('eventDrawerTitle').textContent = "Edit Event Category Title";
    document.getElementById('eventActionField').value       = "edit_event";
    document.getElementById('event_id_field').value         = id;
    document.getElementById('event_name_field').value       = name;
    document.getElementById('eventSubmitBtn').textContent   = "Update Name";
  }
  document.getElementById('eventDrawer').classList.add('open');
  document.getElementById('drawerOverlay').classList.add('open');
}

function openAddonDrawer(mode, id = null, name = '', price = '', eventId = null) {
  closeAllDrawers();
  if (mode === 'add') {
    document.getElementById('addonDrawerTitle').textContent = "Add New Add-on";
    document.getElementById('addonActionField').value       = "add_addon";
    document.getElementById('addon_id_field').value         = "";
    document.getElementById('addon_name_field').value       = "";
    document.getElementById('addon_price_field').value      = "";
    document.getElementById('addon_event_id_field').value   = eventId;
    document.getElementById('addonSubmitBtn').textContent   = "Save Add-on";
  } else {
    document.getElementById('addonDrawerTitle').textContent = "Edit Add-on Details";
    document.getElementById('addonActionField').value       = "edit_addon";
    document.getElementById('addon_id_field').value         = id;
    document.getElementById('addon_name_field').value       = name;
    document.getElementById('addon_price_field').value      = price;
    document.getElementById('addon_event_id_field').value   = eventId;
    document.getElementById('addonSubmitBtn').textContent   = "Update Add-on";
  }
  document.getElementById('addonDrawer').classList.add('open');
  document.getElementById('drawerOverlay').classList.add('open');
}

function openDeleteEvent(id) {
  if (confirm('Are you sure you want to delete this event category? Doing so will softly archive all associated add-ons.')) {
    document.getElementById('delete_ev_id').value = id;
    document.getElementById('deleteEventForm').submit();
  }
}

function openDeleteAddon(id) {
  if (confirm('Are you sure you want to delete this add-on item?')) {
    document.getElementById('delete_ad_id').value = id;
    document.getElementById('deleteAddonForm').submit();
  }
}

function toggleArchiveSection() {
  const vault = document.getElementById('archiveVault');
  const btn = document.getElementById('archiveToggleBtn');
  vault.classList.toggle('show');
  
  if(vault.classList.contains('show')) {
    btn.innerHTML = '<i class="bi bi-eye-slash-fill"></i> Hide Archive Vault';
  } else {
    btn.innerHTML = '<i class="bi bi-archive-fill"></i> View Archive Vault';
  }
}

function submitRestore(type, id) {
  if(confirm('Do you want to restore this item back to active services?')) {
    if(type === 'event') {
      document.getElementById('restoreAction').value = 'restore_event';
      document.getElementById('restoreEventId').value = id;
    } else {
      document.getElementById('restoreAction').value = 'restore_addon';
      document.getElementById('restoreAddonId').value = id;
    }
    document.getElementById('restoreForm').submit();
  }
}
</script>

</body>
</html>