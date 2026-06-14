-- ======================================================
--Add  Delete Archive Support
-- Adds 'archived' column to key tables for soft deletion
-- ======================================================

-- Add archived column to venues table
ALTER TABLE venues ADD COLUMN archived TINYINT(1) DEFAULT 0 AFTER image_url;
CREATE INDEX idx_venues_archived ON venues(archived);

-- Add archived column to event table
ALTER TABLE event ADD COLUMN archived TINYINT(1) DEFAULT 0 AFTER event_name;
CREATE INDEX idx_event_archived ON event(archived);

-- Add archived column to addons table
ALTER TABLE addons ADD COLUMN archived TINYINT(1) DEFAULT 0 AFTER price;
CREATE INDEX idx_addons_archived ON addons(archived);

-- ======================================================
-- VERIFY: Check if columns were added successfully
-- ======================================================
-- SELECT * FROM venues LIMIT 1;
-- SELECT * FROM event LIMIT 1;
-- SELECT * FROM addons LIMIT 1;
