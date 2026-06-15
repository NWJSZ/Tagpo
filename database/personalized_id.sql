USE tagpo_db;

-- STEP 1: I-add muna natin sila bilang normal na VARCHAR columns
ALTER TABLE users ADD COLUMN user_code VARCHAR(20) UNIQUE AFTER id;
ALTER TABLE event ADD COLUMN event_code VARCHAR(20) UNIQUE AFTER event_id;
ALTER TABLE venues ADD COLUMN venue_code VARCHAR(20) UNIQUE AFTER id;
ALTER TABLE bookings ADD COLUMN booking_code VARCHAR(20) UNIQUE AFTER booking_id;
ALTER TABLE payments ADD COLUMN payment_code VARCHAR(20) UNIQUE AFTER payment_id;
ALTER TABLE addons ADD COLUMN addon_code VARCHAR(20) UNIQUE AFTER addon_id;
ALTER TABLE carts ADD COLUMN cart_code VARCHAR(20) UNIQUE AFTER cart_id;
ALTER TABLE amenities ADD COLUMN amenity_code VARCHAR(20) UNIQUE AFTER amenity_id;
ALTER TABLE reviews ADD COLUMN review_code VARCHAR(20) UNIQUE AFTER review_id;
ALTER TABLE venue_gallery ADD COLUMN gallery_code VARCHAR(20) UNIQUE AFTER gallery_id;
ALTER TABLE venue_highlights ADD COLUMN highlight_code VARCHAR(20) UNIQUE AFTER highlight_id;

-- STEP 2: I-update ang mga kasalukuyang records para magkalaman (Para hindi blanko ang mga luma)
UPDATE users SET user_code = CONCAT('USR-', LPAD(id, 4, '0')) WHERE user_code IS NULL;
UPDATE event SET event_code = CONCAT('EVT-', LPAD(event_id, 4, '0')) WHERE event_code IS NULL;
UPDATE venues SET venue_code = CONCAT('VN-', LPAD(id, 4, '0')) WHERE venue_code IS NULL;
UPDATE bookings SET booking_code = CONCAT('BKN-', LPAD(booking_id, 4, '0')) WHERE booking_code IS NULL;
UPDATE payments SET payment_code = CONCAT('PAY-', LPAD(payment_id, 4, '0')) WHERE payment_code IS NULL;
UPDATE addons SET addon_code = CONCAT('ADD-', LPAD(addon_id, 4, '0')) WHERE addon_code IS NULL;
UPDATE carts SET cart_code = CONCAT('CRT-', LPAD(cart_id, 4, '0')) WHERE cart_code IS NULL;
UPDATE amenities SET amenity_code = CONCAT('AMN-', LPAD(amenity_id, 4, '0')) WHERE amenity_code IS NULL;
UPDATE reviews SET review_code = CONCAT('REV-', LPAD(review_id, 4, '0')) WHERE review_code IS NULL;
UPDATE venue_gallery SET gallery_code = CONCAT('GAL-', LPAD(gallery_id, 4, '0')) WHERE gallery_code IS NULL;
UPDATE venue_highlights SET highlight_code = CONCAT('HLG-', LPAD(highlight_id, 4, '0')) WHERE highlight_code IS NULL;

-- STEP 3: Gagawa tayo ng TRIGGERS para sa mga BAGONG idadagdag (Kahit galing sa UI)
-- Ito ang awtomatikong mag-ge-generate ng code tuwing may mag-i-insert na admin o user.

DELIMITER $$

CREATE TRIGGER tg_users_code BEFORE INSERT ON users FOR EACH ROW 
BEGIN
    SET NEW.user_code = CONCAT('USR-', LPAD((SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'), 4, '0'));
END$$

CREATE TRIGGER tg_event_code BEFORE INSERT ON event FOR EACH ROW 
BEGIN
    SET NEW.event_code = CONCAT('EVT-', LPAD((SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event'), 4, '0'));
END$$

CREATE TRIGGER tg_venues_code BEFORE INSERT ON venues FOR EACH ROW 
BEGIN
    SET NEW.venue_code = CONCAT('VN-', LPAD((SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venues'), 4, '0'));
END$$

CREATE TRIGGER tg_bookings_code BEFORE INSERT ON bookings FOR EACH ROW 
BEGIN
    SET NEW.booking_code = CONCAT('BKN-', LPAD((SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings'), 4, '0'));
END$$

CREATE TRIGGER tg_payments_code BEFORE INSERT ON payments FOR EACH ROW 
BEGIN
    SET NEW.payment_code = CONCAT('PAY-', LPAD((SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments'), 4, '0'));
END$$

CREATE TRIGGER tg_addons_code BEFORE INSERT ON addons FOR EACH ROW 
BEGIN
    SET NEW.addon_code = CONCAT('ADD-', LPAD((SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'addons'), 4, '0'));
END$$

CREATE TRIGGER tg_carts_code BEFORE INSERT ON carts FOR EACH ROW 
BEGIN
    SET NEW.cart_code = CONCAT('CRT-', LPAD((SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'carts'), 4, '0'));
END$$

CREATE TRIGGER tg_amenities_code BEFORE INSERT ON amenities FOR EACH ROW 
BEGIN
    SET NEW.amenity_code = CONCAT('AMN-', LPAD((SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amenities'), 4, '0'));
END$$

CREATE TRIGGER tg_reviews_code BEFORE INSERT ON reviews FOR EACH ROW 
BEGIN
    SET NEW.review_code = CONCAT('REV-', LPAD((SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reviews'), 4, '0'));
END$$

CREATE TRIGGER tg_gallery_code BEFORE INSERT ON venue_gallery FOR EACH ROW 
BEGIN
    SET NEW.gallery_code = CONCAT('GAL-', LPAD((SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_gallery'), 4, '0'));
END$$

CREATE TRIGGER tg_highlights_code BEFORE INSERT ON venue_highlights FOR EACH ROW 
BEGIN
    SET NEW.highlight_code = CONCAT('HLG-', LPAD((SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'venue_highlights'), 4, '0'));
END$$

DELIMITER ;