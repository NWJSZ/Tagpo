<!-- admin_style.php — include inside <head> on every admin page -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
/* =============================================
   TAGPO ADMIN — GLOBAL STYLES
   ============================================= */
:root {
  --sidebar-w: 248px;
  --topbar-h: 64px;
  --green:   #3d7a3a;
  --green-lt:#e8f5e4;
  --green-md:#5a9e55;
  --olive:   #4a5c2f;
  --surface: #ffffff;
  --bg:      #f5f6f4;
  --border:  #e5e7e2;
  --text:    #1a1f17;
  --muted:   #6b7060;
  --danger:  #dc2626;
  --warning: #d97706;
  --info:    #0ea5e9;
  --radius:  10px;
  --radius-lg: 14px;
  --shadow:  0 2px 8px rgba(0,0,0,.07);
  --shadow-md:0 4px 20px rgba(0,0,0,.10);
  --transition: .18s ease;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: 'Inter', sans-serif;
  background: var(--bg);
  color: var(--text);
  font-size: 14px;
  min-height: 100vh;
}

/* ── Sidebar ──────────────────────────────── */
.admin-sidebar {
  position: fixed;
  top: 0; left: 0;
  width: var(--sidebar-w);
  height: 100vh;
  background: var(--surface);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  padding: 0 0 24px;
  overflow-y: auto;
  z-index: 200;
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 20px 20px 16px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 10px;
}

.brand-link { display: flex; align-items: center; gap: 8px; text-decoration: none; }
.brand-logo {
  width: 34px; height: 34px;
  background: var(--green);
  color: #fff;
  border-radius: 9px;
  display: grid; place-items: center;
  font-weight: 700; font-size: 16px;
}
.brand-name { font-weight: 700; font-size: 17px; color: var(--text); }
.brand-dot { color: var(--green); }
.admin-badge {
  margin-left: auto;
  background: var(--green-lt);
  color: var(--green);
  font-size: 10px;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 20px;
  letter-spacing: .4px;
  text-transform: uppercase;
}

.sidebar-section-label {
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .8px;
  color: var(--muted);
  padding: 10px 20px 4px;
  margin-top: 6px;
}

.sidebar-nav { list-style: none; padding: 0 10px; }
.sidebar-nav li { margin-bottom: 2px; }

.sidebar-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-radius: var(--radius);
  color: var(--muted);
  text-decoration: none;
  font-weight: 500;
  font-size: 13.5px;
  transition: background var(--transition), color var(--transition);
}
.sidebar-link i { font-size: 16px; width: 20px; text-align: center; }
.sidebar-link:hover { background: var(--bg); color: var(--text); }
.sidebar-link.active {
  background: var(--green-lt);
  color: var(--green);
  font-weight: 600;
}
.sidebar-link--danger { color: var(--danger); }
.sidebar-link--danger:hover { background: #fef2f2; color: var(--danger); }

/* ── Topbar ──────────────────────────────── */
.admin-topbar {
  position: fixed;
  top: 0;
  left: var(--sidebar-w);
  right: 0;
  height: var(--topbar-h);
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 28px;
  z-index: 100;
}

.topbar-title { font-weight: 700; font-size: 18px; }
.topbar-right { display: flex; align-items: center; gap: 14px; }

.topbar-date {
  display: flex;
  align-items: center;
  gap: 7px;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 6px 12px;
  font-size: 13px;
  color: var(--muted);
  font-weight: 500;
}

.topbar-avatar {
  width: 36px; height: 36px;
  border-radius: 50%;
  background: var(--green);
  color: #fff;
  font-weight: 700;
  font-size: 14px;
  display: grid; place-items: center;
}

/* ── Main content wrapper ───────────────── */
.admin-main {
  margin-left: var(--sidebar-w);
  padding-top: var(--topbar-h);
  min-height: 100vh;
}

.admin-content {
  padding: 28px 28px 40px;
}

/* ── Page header ────────────────────────── */
.page-header {
  margin-bottom: 24px;
}
.page-header h1 {
  font-size: 24px;
  font-weight: 700;
  color: var(--text);
}
.page-header p { color: var(--muted); font-size: 13.5px; margin-top: 3px; }

/* ── Stat cards ─────────────────────────── */
.stat-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
}
.stat-icon {
  width: 48px; height: 48px;
  border-radius: var(--radius);
  display: grid; place-items: center;
  font-size: 20px;
  flex-shrink: 0;
}
.stat-icon.green  { background: var(--green-lt); color: var(--green); }
.stat-icon.orange { background: #fff3e0; color: #e65100; }
.stat-icon.blue   { background: #e3f2fd; color: #1565c0; }
.stat-icon.purple { background: #f3e5f5; color: #6a1b9a; }

.stat-label { font-size: 12px; color: var(--muted); font-weight: 500; margin-bottom: 4px; }
.stat-value { font-size: 26px; font-weight: 700; line-height: 1; color: var(--text); }
.stat-sub   { font-size: 11px; color: var(--muted); margin-top: 3px; }

/* ── Panel card ─────────────────────────── */
.panel-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
}
.panel-card-header {
  padding: 18px 20px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 10px;
}
.panel-card-header h2 {
  font-size: 15px;
  font-weight: 600;
  color: var(--text);
  margin: 0;
}
.panel-card-body { padding: 20px; }

/* ── Filter toolbar ─────────────────────── */
.filter-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
  padding: 14px 20px;
  border-bottom: 1px solid var(--border);
  background: var(--bg);
}

.search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 7px 12px;
  min-width: 260px;
  flex: 1;
}
.search-box i { color: var(--muted); }
.search-box input {
  border: none;
  outline: none;
  background: transparent;
  font-size: 13.5px;
  width: 100%;
}

.filter-select {
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 7px 28px 7px 10px;
  font-size: 13px;
  background: var(--surface);
  color: var(--text);
  outline: none;
  cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236b7060' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 8px center;
}

/* ── Data table ─────────────────────────── */
.data-table {
  width: 100%;
  border-collapse: collapse;
}
.data-table thead th {
  padding: 11px 16px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .6px;
  color: var(--muted);
  background: var(--bg);
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
}
.data-table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: background var(--transition);
  cursor: pointer;
}
.data-table tbody tr:last-child { border-bottom: none; }
.data-table tbody tr:hover { background: var(--bg); }
.data-table tbody tr.row-selected { background: var(--green-lt); }
.data-table td {
  padding: 13px 16px;
  font-size: 13.5px;
  vertical-align: middle;
}

/* ── Badges ─────────────────────────────── */
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

.badge-pending   { background: #fff8e1; color: #b45309; }
.badge-pending::before   { background: #d97706; }
.badge-confirmed { background: #e8f5e4; color: #2d6a27; }
.badge-confirmed::before { background: #3d7a3a; }
.badge-completed { background: #e3f2fd; color: #1565c0; }
.badge-completed::before { background: #1565c0; }
.badge-cancelled { background: #fef2f2; color: #b91c1c; }
.badge-cancelled::before { background: #dc2626; }
.badge-paid      { background: #e8f5e4; color: #2d6a27; }
.badge-paid::before      { background: #3d7a3a; }
.badge-failed    { background: #fef2f2; color: #b91c1c; }
.badge-failed::before    { background: #dc2626; }
.badge-refunded  { background: #f3e5f5; color: #6a1b9a; }
.badge-refunded::before  { background: #6a1b9a; }
.badge-active    { background: #e8f5e4; color: #2d6a27; }
.badge-active::before    { background: #3d7a3a; }
.badge-inactive  { background: #f5f5f5; color: #6b7060; }
.badge-inactive::before  { background: #9e9e9e; }

/* ── Action buttons ─────────────────────── */
.btn-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all var(--transition);
  border: 1px solid transparent;
  text-decoration: none;
}
.btn-primary-green {
  background: var(--green);
  color: #fff;
  border-color: var(--green);
}
.btn-primary-green:hover { background: var(--olive); border-color: var(--olive); color: #fff; }
.btn-outline-green {
  background: transparent;
  color: var(--green);
  border-color: var(--green);
}
.btn-outline-green:hover { background: var(--green-lt); }
.btn-outline-gray {
  background: transparent;
  color: var(--muted);
  border-color: var(--border);
}
.btn-outline-gray:hover { background: var(--bg); color: var(--text); }
.btn-danger-soft {
  background: #fef2f2;
  color: var(--danger);
  border-color: #fecaca;
}
.btn-danger-soft:hover { background: #fee2e2; }

.icon-btn {
  width: 32px; height: 32px;
  border-radius: 7px;
  border: 1px solid var(--border);
  background: transparent;
  display: grid; place-items: center;
  cursor: pointer;
  transition: background var(--transition);
  color: var(--muted);
  font-size: 15px;
}
.icon-btn:hover { background: var(--bg); color: var(--text); }

/* ── Side drawer ────────────────────────── */
.side-drawer-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.35);
  z-index: 300;
  opacity: 0;
  pointer-events: none;
  transition: opacity .25s ease;
}
.side-drawer-overlay.open {
  opacity: 1;
  pointer-events: all;
}

.side-drawer {
  position: fixed;
  top: 0; right: 0;
  width: 420px;
  max-width: 95vw;
  height: 100vh;           /* Full viewport height */
  background: var(--surface);
  border-left: 1px solid var(--border);
  z-index: 400;
  transform: translateX(100%);
  transition: transform .28s cubic-bezier(.4,0,.2,1);

  /* KEY FIX: make the drawer itself a flex column so children
     can share the fixed height without overflowing */
  display: flex;
  flex-direction: column;
  overflow: hidden;         /* clip — children handle their own scroll */
}
.side-drawer.open { transform: translateX(0); }

/* Header: never shrinks */
.drawer-header {
  padding: 20px 22px 16px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  flex-shrink: 0;
}
.drawer-header h3 { font-size: 15px; font-weight: 700; color: var(--text); margin: 0 0 2px; }
.drawer-header p  { font-size: 12px; color: var(--muted); margin: 0; }

.drawer-close {
  width: 30px; height: 30px;
  border-radius: 7px;
  border: 1px solid var(--border);
  background: transparent;
  display: grid; place-items: center;
  cursor: pointer;
  font-size: 16px;
  color: var(--muted);
  flex-shrink: 0;
}
.drawer-close:hover { background: var(--bg); }

/* KEY FIX: The <form> tag wraps body + footer.
   It must be a flex column that fills remaining height. */
.drawer-form {
  display: flex;
  flex-direction: column;
  flex: 1;           /* take all space below the header */
  overflow: hidden;  /* contain children */
  min-height: 0;     /* allow flex shrink below content size */
}

/* Body: scrolls when content overflows */
.drawer-body {
  flex: 1;
  overflow-y: auto;
  padding: 20px 22px;
  min-height: 0;     /* critical for Firefox + Safari */
}

/* Footer: pinned at bottom, never scrolls away */
.drawer-footer {
  flex-shrink: 0;
  padding: 16px 22px;
  border-top: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  gap: 8px;
  background: var(--surface); /* ensure it covers scrolled content */
}

.drawer-section { margin-bottom: 22px; }
.drawer-section-title {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .7px;
  color: var(--muted);
  margin-bottom: 12px;
}
.info-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 8px 0;
  border-bottom: 1px solid var(--border);
  gap: 12px;
}
.info-row:last-child { border-bottom: none; }
.info-label { font-size: 12.5px; color: var(--muted); font-weight: 500; }
.info-value { font-size: 13px; font-weight: 500; color: var(--text); text-align: right; }

.btn-full {
  width: 100%;
  padding: 10px;
  border-radius: 9px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all var(--transition);
}
.btn-full-green  { background: var(--green);  color: #fff; border-color: var(--green); }
.btn-full-green:hover  { background: var(--olive); border-color: var(--olive); }
.btn-full-red    { background: #fef2f2; color: var(--danger); border-color: #fecaca; }
.btn-full-red:hover    { background: #fee2e2; }
.btn-full-outline{ background: transparent; color: var(--text); border-color: var(--border); }
.btn-full-outline:hover{ background: var(--bg); }

/* ── Forms ──────────────────────────────── */
.form-label-sm { font-size: 12.5px; font-weight: 600; color: var(--text); margin-bottom: 5px; display: block; }
.form-ctrl {
  width: 100%;
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 9px 12px;
  font-size: 13.5px;
  background: var(--surface);
  color: var(--text);
  outline: none;
  transition: border-color var(--transition);
  font-family: inherit;
}
.form-ctrl:focus { border-color: var(--green); box-shadow: 0 0 0 3px rgba(61,122,58,.12); }
.form-ctrl.is-invalid { border-color: var(--danger); }

/* ── Alert ──────────────────────────────── */
.alert-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 9px;
  font-size: 13.5px;
  font-weight: 500;
  margin-bottom: 18px;
}
.alert-bar.success { background: #e8f5e4; color: #2d6a27; border: 1px solid #a5d6a7; }
.alert-bar.error   { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

/* ── Tabs ───────────────────────────────── */
.view-tabs {
  display: flex;
  gap: 4px;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 9px;
  padding: 4px;
}
.view-tab {
  padding: 6px 14px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  color: var(--muted);
  border: none;
  background: transparent;
  transition: all var(--transition);
}
.view-tab.active { background: var(--surface); color: var(--green); box-shadow: var(--shadow); font-weight: 600; }

/* ── Calendar grid ──────────────────────── */
.cal-grid {
  display: grid;
  grid-template-columns: 140px 1fr;
  gap: 0;
}
.cal-venue-col { border-right: 1px solid var(--border); }
.cal-venue-cell {
  padding: 14px 16px;
  border-bottom: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.cal-venue-img {
  width: 100%;
  height: 60px;
  object-fit: cover;
  border-radius: 7px;
  background: var(--bg);
}
.cal-venue-name { font-size: 12.5px; font-weight: 600; color: var(--text); }
.cal-venue-cap  { font-size: 11px; color: var(--muted); }

.cal-timeline { overflow-x: auto; }
.cal-week-header {
  display: grid;
  grid-template-columns: 80px repeat(7, 1fr);
  border-bottom: 1px solid var(--border);
}
.cal-day-head {
  padding: 10px 8px;
  text-align: center;
  font-size: 11px;
  font-weight: 600;
  color: var(--muted);
  border-right: 1px solid var(--border);
}
.cal-day-head:first-child { background: var(--bg); }
.cal-day-head.today { color: var(--green); }

.cal-rows { }
.cal-time-row {
  display: grid;
  grid-template-columns: 80px repeat(7, 1fr);
  border-bottom: 1px solid var(--border);
  min-height: 52px;
}
.cal-time-label {
  padding: 6px 8px;
  font-size: 11px;
  color: var(--muted);
  border-right: 1px solid var(--border);
  background: var(--bg);
  white-space: nowrap;
}
.cal-time-cell {
  border-right: 1px solid var(--border);
  position: relative;
  padding: 2px;
}
.cal-time-cell.today-col { background: rgba(61,122,58,.03); }
.cal-booking-chip {
  background: var(--green);
  color: #fff;
  border-radius: 5px;
  padding: 3px 6px;
  font-size: 11px;
  font-weight: 500;
  cursor: pointer;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  display: block;
}
.cal-booking-chip:hover { background: var(--olive); }

/* ── Pagination ─────────────────────────── */
.pagination-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 20px;
  border-top: 1px solid var(--border);
  font-size: 12.5px;
  color: var(--muted);
}
.page-btns { display: flex; gap: 4px; }
.page-btn {
  width: 30px; height: 30px;
  border-radius: 7px;
  border: 1px solid var(--border);
  background: transparent;
  display: grid; place-items: center;
  font-size: 13px;
  cursor: pointer;
  color: var(--muted);
  transition: all var(--transition);
}
.page-btn:hover, .page-btn.active { background: var(--green-lt); color: var(--green); border-color: var(--green-lt); }

/* ── Avatar initials ─────────────────────── */
.user-avatar {
  width: 34px; height: 34px;
  border-radius: 50%;
  background: var(--green);
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  display: grid; place-items: center;
  flex-shrink: 0;
}
.user-name-block .name { font-weight: 600; font-size: 13.5px; }
.user-name-block .email { font-size: 11.5px; color: var(--muted); }

/* ── Gallery thumbnail strip ─────────────── */
.gallery-thumb-strip {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}
.gallery-thumb-item {
  position: relative;
  width: 72px;
  height: 72px;
  border-radius: 8px;
  overflow: hidden;
  border: 2px solid var(--border);
  flex-shrink: 0;
}
.gallery-thumb-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.gallery-thumb-remove {
  position: absolute;
  top: 2px; right: 2px;
  width: 18px; height: 18px;
  border-radius: 50%;
  background: rgba(0,0,0,.6);
  color: #fff;
  border: none;
  cursor: pointer;
  font-size: 11px;
  display: grid; place-items: center;
  line-height: 1;
}
.gallery-thumb-remove:hover { background: var(--danger); }

/* ── Utility ────────────────────────────── */
.text-muted-sm { font-size: 12px; color: var(--muted); }
.fw-600 { font-weight: 600; }
.gap-8  { gap: 8px; }
.d-flex { display: flex; }
.align-center { align-items: center; }
.justify-between { justify-content: space-between; }
.flex-wrap { flex-wrap: wrap; }
.mb-3 { margin-bottom: 16px; }
.mt-1 { margin-top: 4px; }
</style>

<script>
  // Enable search form submission on Enter key
  document.addEventListener('DOMContentLoaded', function() {
    const searchForms = document.querySelectorAll('.search-box');
    searchForms.forEach(form => {
      const input = form.querySelector('input[type="text"]');
      if (input) {
        input.addEventListener('keypress', function(e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            form.submit();
          }
        });
      }
    });
  });
</script>