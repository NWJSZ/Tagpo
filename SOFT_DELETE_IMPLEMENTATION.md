# Soft Delete (Archiving) Implementation Guide
**Tagpo Event Venue Booking System**

---

## Overview
✅ **Soft delete (archiving) has been implemented** - When admins delete venues, events, or add-ons, they are now archived instead of permanently deleted from the database.

### Benefits
- **Data preservation**: Deleted items can be recovered if needed
- **Audit trail**: Historical records remain intact
- **Business continuity**: Maintains referential integrity with past bookings
- **Compliance**: Supports data retention requirements

---

## Database Changes

### Migration Script
**File**: `database/migration_add_archived.sql`

Three new columns have been added:
```sql
-- venues table
ALTER TABLE venues ADD COLUMN archived TINYINT(1) DEFAULT 0;

-- event table
ALTER TABLE event ADD COLUMN archived TINYINT(1) DEFAULT 0;

-- addons table
ALTER TABLE addons ADD COLUMN archived TINYINT(1) DEFAULT 0;
```

**To apply the migration**:
```bash
# Using MySQL command line
mysql -u root tagpo_db < database/migration_add_archived.sql

# Or manually run each ALTER TABLE statement in phpMyAdmin
```

---

## How Soft Delete Works

### Before (Hard Delete)
```php
// OLD: Permanently removes data
DELETE FROM venues WHERE id = ?;
```

### After (Soft Delete / Archive)
```php
// NEW: Just marks as archived
UPDATE venues SET archived = 1 WHERE id = ?;
```

### Viewing Records
```php
// Only shows active (non-archived) records
SELECT * FROM venues WHERE archived = 0;

// To view archived items (admin recovery):
SELECT * FROM venues WHERE archived = 1;
```

---

## Modified Files

### 1. Admin Delete Operations

#### `admin/manage-venues.php`
- **Before**: Hard deleted venues from database
- **After**: Archives venues with `UPDATE venues SET archived = 1`
- **Flash message**: "Venue archived successfully."

#### `admin/manage-events.php`
- **Before**: Hard deleted events and add-ons
- **After**: Archives with `UPDATE event SET archived = 1` and `UPDATE addons SET archived = 1`
- **Flash messages**: "Event Type successfully archived!" / "Add-on successfully archived!"

### 2. Admin View Queries (Exclude Archived)

Files updated to hide archived records:

| File | Change |
|------|--------|
| `admin/manage-venues.php` | `SELECT * FROM venues WHERE archived = 0` |
| `admin/manage-events.php` | `SELECT FROM event WHERE archived = 0` |
| `admin/manage-bookings.php` | `SELECT FROM venues WHERE archived = 0` |
| `admin/dashboard.php` | `SELECT COUNT FROM venues WHERE archived = 0` |

### 3. Public-Facing Queries (Exclude Archived)

| File | Change |
|------|--------|
| `index.php` | Main venue display filters archived |
| `add_to_cart.php` | Event/addon selection excludes archived |
| `payment.php` | Payment processing uses active records only |
| `submit_review.php` | Reviews can only be submitted on active venues |
| `booking_report.php` | Analytics excludes archived venues/events |

---

## User Experience

### For Admin Users

**When deleting a venue:**
1. Admin clicks delete button on venue
2. System shows: "Venue archived successfully."
3. Venue disappears from main admin view
4. Data remains in database (no data loss)

**Advantages:**
- No accidental permanent loss
- Can recover data if needed by querying `WHERE archived = 1`
- Maintains booking history integrity

### For Regular Users

- Archived venues automatically hidden from search and booking interfaces
- No change in user experience
- Can't book archived venues

---

## Recovery / Unarchive (Future Enhancement)

To restore an archived item in the future:

```sql
-- Restore a venue
UPDATE venues SET archived = 0 WHERE id = ?;

-- Restore an event type
UPDATE event SET archived = 0 WHERE event_id = ?;

-- Restore an add-on
UPDATE addons SET archived = 0 WHERE addon_id = ?;
```

To add admin restore functionality, add this to the admin panel:
```php
if ($action === 'restore_venue') {
    $stmt = $conn->prepare("UPDATE venues SET archived = 0 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $flash = 'Venue restored successfully.';
}
```

---

## Testing Checklist

- [ ] Run the migration script to add archived columns
- [ ] Test deleting a venue → should see "archived successfully" message
- [ ] Verify deleted venue doesn't appear in admin venues list
- [ ] Test deleting an event → should see "archived successfully" message
- [ ] Verify deleted event doesn't appear in event management UI
- [ ] Test booking flow → archived items shouldn't be available
- [ ] Verify database still contains archived records (check with `SELECT WHERE archived = 1`)
- [ ] Check analytics reports (booking_report.php) don't include archived venues

---

## Database Queries Reference

### View Active Records
```sql
-- Active venues
SELECT * FROM venues WHERE archived = 0;

-- Active events
SELECT * FROM event WHERE archived = 0;

-- Active add-ons
SELECT * FROM addons WHERE archived = 0;
```

### View Archived Records (Recovery)
```sql
-- Archived venues
SELECT * FROM venues WHERE archived = 1;

-- Archived events
SELECT * FROM event WHERE archived = 1;

-- Archived add-ons
SELECT * FROM addons WHERE archived = 1;
```

### Batch Recovery
```sql
-- Unarchive all venues deleted before a certain date
UPDATE venues SET archived = 0 WHERE archived = 1 AND /* date condition */;
```

---

## Summary

✅ **What's Done**:
- Database schema updated with `archived` columns
- All DELETE operations converted to soft delete (UPDATE archived=1)
- All SELECT queries filter out archived records by default
- 12+ files updated across admin and public sections
- User-facing features automatically exclude archived data

🔄 **What to Do**:
1. Run the migration script: `migration_add_archived.sql`
2. Test the admin delete functionality
3. Optionally add admin UI for viewing/restoring archived items

📊 **Impact**:
- ✅ Data preservation
- ✅ No data loss on delete
- ✅ Maintains referential integrity
- ✅ Supports audit requirements
- ✅ Zero impact on user experience
