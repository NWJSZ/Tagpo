DROP DATABASE IF EXISTS tagpo_db;
CREATE DATABASE tagpo_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE tagpo_db;

-- =====================================================
-- USERS
-- =====================================================

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role ENUM('user','admin') DEFAULT 'user'
);

-- =====================================================
-- EVENT TYPES
-- =====================================================

CREATE TABLE event (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(100) NOT NULL UNIQUE,
    archived TINYINT(1) DEFAULT 0,
    
    INDEX idx_event_archived (archived)
);

-- =====================================================
-- VENUES
-- =====================================================

CREATE TABLE venues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    capacity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    description TEXT,
    image_url VARCHAR(255),
    archived TINYINT(1) DEFAULT 0,
    
    INDEX idx_venues_archived (archived)
);

-- =====================================================
-- VENUE EVENTS (JUNCTION TABLE FOR MANY-TO-MANY)
-- =====================================================

CREATE TABLE venue_events (
    venue_id INT NOT NULL,
    event_id INT NOT NULL,
    PRIMARY KEY (venue_id, event_id),
    
    FOREIGN KEY (venue_id)
        REFERENCES venues(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
        
    FOREIGN KEY (event_id)
        REFERENCES event(event_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =====================================================
-- VENUE GALLERY
-- =====================================================

CREATE TABLE venue_gallery (
    gallery_id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id   INT NOT NULL,
    image_url  VARCHAR(255) NOT NULL,
    label      VARCHAR(100) DEFAULT NULL,
    sort_order TINYINT UNSIGNED DEFAULT 0,

    FOREIGN KEY (venue_id)
        REFERENCES venues(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =====================================================
-- VENUE HIGHLIGHTS
-- =====================================================

CREATE TABLE venue_highlights (
    highlight_id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id     INT NOT NULL,
    highlight    VARCHAR(255) NOT NULL,
    sort_order   TINYINT UNSIGNED DEFAULT 0,

    FOREIGN KEY (venue_id)
        REFERENCES venues(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =====================================================
-- AMENITIES
-- =====================================================

CREATE TABLE amenities (
    amenity_id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NOT NULL,
    amenity_name VARCHAR(100) NOT NULL,

    FOREIGN KEY (venue_id)
        REFERENCES venues(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =====================================================
-- ADDONS
-- =====================================================

CREATE TABLE addons (
    addon_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    addon_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    archived TINYINT(1) DEFAULT 0,

    FOREIGN KEY (event_id)
        REFERENCES event(event_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
        
    INDEX idx_addons_archived (archived)
);

-- =====================================================
-- CARTS
-- =====================================================

CREATE TABLE carts (
    cart_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status ENUM('active','checked_out','cancelled') DEFAULT 'active',

    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =====================================================
-- BOOKINGS
-- =====================================================

CREATE TABLE bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    user_id INT NOT NULL,
    venue_id INT NOT NULL,
    event_id INT NOT NULL,
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    duration INT NOT NULL,
    guest_count INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status ENUM('pending','confirmed','cancelled') DEFAULT 'pending',

    FOREIGN KEY (cart_id)
        REFERENCES carts(cart_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (venue_id)
        REFERENCES venues(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (event_id)
        REFERENCES event(event_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =====================================================
-- BOOKING ADDONS
-- =====================================================

CREATE TABLE booking_addons (
    booking_id INT NOT NULL,
    addon_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (booking_id, addon_id),

    FOREIGN KEY (booking_id)
        REFERENCES bookings(booking_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (addon_id)
        REFERENCES addons(addon_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

-- =====================================================
-- PAYMENTS
-- =====================================================

CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('credit_card','debit_card','gcash') NOT NULL,
    payment_status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
    transaction_id VARCHAR(100) UNIQUE,
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (cart_id)
        REFERENCES carts(cart_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =====================================================
-- CARD PAYMENTS
-- =====================================================

CREATE TABLE card_payments (
    payment_id INT PRIMARY KEY,
    card_holder_name VARCHAR(100) NOT NULL,
    card_last_four CHAR(4) NOT NULL,
    card_expiry_month TINYINT NOT NULL,
    card_expiry_year SMALLINT NOT NULL,

    FOREIGN KEY (payment_id)
        REFERENCES payments(payment_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =====================================================
-- GCASH PAYMENTS
-- =====================================================

CREATE TABLE gcash_payments (
    payment_id INT PRIMARY KEY,
    gcash_phone_number VARCHAR(11) NOT NULL,
    gcash_account_name VARCHAR(100) NOT NULL,

    FOREIGN KEY (payment_id)
        REFERENCES payments(payment_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =====================================================
-- REVIEWS
-- =====================================================

CREATE TABLE reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    venue_id INT NOT NULL,
    rating TINYINT NOT NULL,
    review_text TEXT,
    review_date DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY (venue_id)
        REFERENCES venues(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =====================================================
-- PASSWORD RESETS
-- =====================================================

CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (email)
);
-- =====================================================
-- SEED DATA
-- =====================================================

-- Admin user (password: admin123)
INSERT INTO users (first_name, last_name, email, password, role) VALUES
('Admin', 'User', 'admin@tagpo.com', '$2y$10$h.t7UQyQNbYcXm/nVXV0NelrTTmIn5LyWqg5MrL.hP08x6B.ZzeJG', 'admin'),
('Jen', 'Ilao', 'jenmaeilao@gmail.com', '$2y$10$tTSf0iu7TUcguP.QZ/hbVuMVZ51eHWJwkX5EhEB0kb4CLC1q0xEWu', 'admin'),
('Natalie', 'Paduhilao', 'nataliepaduhilao@gmail.com', '$2y$10$z7GekMPmrrp.LJnBeVI77OgUCAKldsVgl8xDs1.bZS7quINo5TrKO', 'admin'),
('Wayne', 'Tanglao', 'tanglaowayne@gmail.com', '$2y$10$FeAbEvRERruIx0jkGAcTR.ioueKYI6R/mB2UlHQgTs1AFuZ8gDKmC', 'admin');

-- Venues
INSERT INTO venues (name, location, capacity, price, description, image_url) VALUES
('Paradiso Terrestre',  'Molino, Cavite City', 500, 35000.00, 'A stunning beachfront venue perfect for weddings and debuts with breathtaking sunset views.', 'assets/images/paradiso1.jpg'),
('Blue Gardens',        'Makati City',          250, 60000.00, 'Elegant garden venue ideal for proms and galas with modern amenities and exquisite landscaping.', 'assets/images/gardens1.jpg'),
('The Green Lounge Events Place', 'Quezon City', 300, 45000.00, 'Contemporary event space suitable for birthday parties, corporate events, and various celebrations.', 'assets/images/lounge1.jpg');

-- Events
INSERT INTO event (event_name) VALUES
('Wedding'),             -- ID: 1
('Birthday / Debut'),    -- ID: 2
('Prom / Ball'),         -- ID: 3
('Corporate Event'),     -- ID: 4
('Reunion'),             -- ID: 5
('Anniversary');         -- ID: 6

-- Addons
INSERT INTO addons (event_id, addon_name, price) VALUES
-- Wedding Addons (event_id = 1)
(1, 'Catering Service', 8000.00),
(1, 'Bridal Car', 3500.00),
(1, 'Floral Arrangement Package', 2500.00),
(1, 'Wedding Stage Decoration', 4000.00),
(1, 'Photo Booth', 2500.00),

-- Birthday / Debut Addons (event_id = 2)
(2, 'Catering Service', 6000.00),
(2, 'Balloon & Themed Setup', 2000.00),
(2, 'Photo Booth', 2500.00),
(2, 'Clown / Event Host', 1500.00),
(2, 'Cake Styling Setup', 1000.00),

-- Prom / Ball Addons (event_id = 3)
(3, 'DJ Booth', 3000.00),
(3, 'LED Lights Setup', 2500.00),
(3, 'Red Carpet Entrance Setup', 1500.00),
(3, 'Photo Booth', 2500.00),
(3, 'Emcee / Host', 2000.00),

-- Corporate Event Addons (event_id = 4)
(4, 'Projector & Screen Setup', 2000.00),
(4, 'Sound System', 3000.00),
(4, 'Microphones & Stage Setup', 2500.00),
(4, 'Coffee Break Catering', 5000.00),
(4, 'LED Display Wall', 8000.00),

-- Reunion Addons (event_id = 5)
(5, 'Buffet Catering', 7000.00),
(5, 'Photo Booth', 2500.00),
(5, 'Memory Slideshow / Projector', 1500.00),
(5, 'Event Host / Emcee', 2000.00),

-- Anniversary Addons (event_id = 6)
(6, 'Romantic Venue Styling', 3000.00),
(6, 'Floral Arrangement Package', 2000.00),
(6, 'Candle & Lights Setup', 1500.00),
(6, 'Live Acoustic Music', 5000.00);

-- Amenities
INSERT INTO amenities (venue_id, amenity_name) VALUES
(1, 'Free Parking'),
(1, 'Air Conditioning'),
(1, 'Sound System'),
(1, 'Stage Lighting'),
(1, 'Projector & Screen'),
(1, 'Wheelchair Access'),
(1, '24/7 Security'),
(1, 'Free Wi-Fi'),
(2, 'Valet Parking'),
(2, 'Central Air Conditioning'),
(2, 'Professional Sound System'),
(2, 'In-House Catering'),
(2, 'LED Wall Display'),
(2, 'Wheelchair Access'),
(2, 'Floral Arrangements'),
(2, 'High-Speed Wi-Fi'),
(3, 'Free Parking (100 slots)'),
(3, 'Industrial AC System'),
(3, 'Premium Sound System'),
(3, 'Architectural Lighting'),
(3, 'Twin Projectors'),
(3, 'Wheelchair Access'),
(3, '24/7 Security'),
(3, 'Free Wi-Fi');

-- Gallery images
INSERT INTO venue_gallery (venue_id, image_url, label, sort_order) VALUES
(1, 'assets/images/paradiso1.jpg', 'Garden Terrace', 1),
(1, 'assets/images/paradiso2.jpg', 'Main Hall',      2),
(1, 'assets/images/paradiso3.jpg', 'Al Fresco',      3),
(1, 'assets/images/paradiso4.jpg', 'Bridal Suite',   4),
(1, 'assets/images/paradiso5.jpg', 'Ballroom',       5),
(2, 'assets/images/gardens1.jpg',  'Garden Terrace', 1),
(2, 'assets/images/gardens2.jpg',  'Main Hall',      2),
(2, 'assets/images/gardens3.jpg',  'Al Fresco',      3),
(2, 'assets/images/gardens4.jpg',  'Bridal Suite',   4),
(2, 'assets/images/gardens5.jpg',  'Ballroom',       5),
(3, 'assets/images/lounge1.jpg',   'Garden Terrace', 1),
(3, 'assets/images/lounge2.jpg',   'Main Hall',      2),
(3, 'assets/images/lounge3.jpg',   'Al Fresco',      3),
(3, 'assets/images/lounge4.jpg',   'Bridal Suite',   4),
(3, 'assets/images/lounge5.png',   'Ballroom',       5);

-- Venue highlights
INSERT INTO venue_highlights (venue_id, highlight, sort_order) VALUES
(1, 'Multi-functional event space for any occasion',          1),
(1, 'Easy access to public transportation & major highways',  2),
(1, 'Large outdoor and indoor venues available',              3),
(1, 'Just 10 minutes from Alabang via Daang Hari & MCX',      4),
(2, 'Luxury ballroom with premium interior design',           1),
(2, 'In-house catering with curated menus available',         2),
(2, 'Prime Makati location — walking distance from hotels',   3),
(2, 'Dedicated event coordinator for every booking',          4),
(3, 'Modern minimalist design with architectural lighting',   1),
(3, 'Flexible layout — ideal for any event type',             2),
(3, 'Located along a major QC thoroughfare',                  3),
(3, 'Ample free parking for up to 100 vehicles',              4);

-- MAPPING ALL VENUES TO ALL EVENTS (ALL AVAILABLE)

INSERT INTO venue_events (venue_id, event_id) VALUES
-- Paradiso Terrestre (venue_id = 1) -> All Events
(1, 1), -- Wedding
(1, 2), -- Birthday / Debut
(1, 3), -- Prom / Ball
(1, 4), -- Corporate Event
(1, 5), -- Reunion
(1, 6), -- Anniversary

-- Blue Gardens (venue_id = 2) -> All Events
(2, 1), -- Wedding
(2, 2), -- Birthday / Debut
(2, 3), -- Prom / Ball
(2, 4), -- Corporate Event
(2, 5), -- Reunion
(2, 6), -- Anniversary

-- The Green Lounge Events Place (venue_id = 3) -> All Events
(3, 1), -- Wedding
(3, 2), -- Birthday / Debut
(3, 3), -- Prom / Ball
(3, 4), -- Corporate Event
(3, 5), -- Reunion
(3, 6); -- Anniversary


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



SELECT 
    b.booking_id,
    u.first_name,
    u.last_name,
    v.name AS venue_name,
    e.event_name,
    b.event_date
FROM bookings b
JOIN users u ON b.user_id = u.id
JOIN venues v ON b.venue_id = v.id
JOIN event e ON b.event_id = e.event_id;


SELECT 
    v.name AS venue_name,
    COUNT(b.booking_id) AS total_bookings
FROM venues v
LEFT JOIN bookings b ON v.id = b.venue_id
GROUP BY v.name;