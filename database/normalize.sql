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
    description TEXT
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
    image_url VARCHAR(255)
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

    FOREIGN KEY (event_id)
        REFERENCES event(event_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- =====================================================
-- CARTS
-- =====================================================

CREATE TABLE carts (
    cart_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,

    status ENUM(
        'active',
        'checked_out',
        'cancelled'
    ) DEFAULT 'active',

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

    status ENUM(
        'pending',
        'confirmed',
        'cancelled'
    ) DEFAULT 'pending',

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
-- FIX: Removed duplicate AUTO_INCREMENT column (booking_addon_id).
-- The composite PRIMARY KEY (booking_id, addon_id) is sufficient and correct.
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
-- FIX: payments now references cart_id (not booking_id) per normalize.sql design.
-- Separate child tables card_payments and gcash_payments for payment detail.
-- =====================================================

CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,

    cart_id INT NOT NULL,

    amount DECIMAL(10,2) NOT NULL,

    payment_method ENUM(
        'credit_card',
        'debit_card',
        'gcash'
    ) NOT NULL,

    payment_status ENUM(
        'pending',
        'paid',
        'failed',
        'refunded'
    ) DEFAULT 'pending',

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
-- SEED DATA
-- =====================================================

-- Admin user (password: admin123)
INSERT INTO users (first_name, last_name, email, password, role) VALUES
('Admin', 'User', 'admin@tagpo.com', '$2y$10$h.t7UQyQNbYcXm/nVXV0NelrTTmIn5LyWqg5MrL.hP08x6B.ZzeJG', 'admin');

-- Venues
INSERT INTO venues (name, location, capacity, price, description, image_url) VALUES
('Paradiso Terrestre',  'Molino, Cavite City', 500, 35000.00, 'A stunning beachfront venue perfect for weddings and debuts with breathtaking sunset views.', 'assets/images/paradiso1.jpg'),
('Blue Gardens',        'Makati City',          250, 60000.00, 'Elegant garden venue ideal for proms and galas with modern amenities and exquisite landscaping.', 'assets/images/gardens1.jpg'),
('The Green Lounge Events Place', 'Quezon City', 300, 45000.00, 'Contemporary event space suitable for birthday parties, corporate events, and various celebrations.', 'assets/images/lounge1.jpg');

-- Event types
INSERT INTO event (event_id, event_name, description) VALUES
(1, 'Wedding',        'A ceremony where two people are united in marriage.'),
(2, 'Birthday',       "A celebration of the anniversary of a person's birth."),
(3, 'Corporate Event','An event organized by a company for its employees or clients.'),
(4, 'Prom',           'A formal dance or gathering of high school students.'),
(5, 'Gala',           'A social occasion with special entertainments or performances.');

-- Addons
INSERT INTO addons (event_id, addon_name, price) VALUES
(1, 'Catering Service',          8000.00),
(1, 'Bridal Car',                3500.00),
(1, 'Floral Arrangement Package',2500.00),
(1, 'Wedding Stage Decoration',  4000.00),
(1, 'Photo Booth',               2500.00),
(2, 'Catering Service',          6000.00),
(2, 'Balloon & Themed Setup',    2000.00),
(2, 'Photo Booth',               2500.00),
(2, 'Clown / Event Host',        1500.00),
(2, 'Cake Styling Setup',        1000.00);

-- Amenities
INSERT INTO amenities (venue_id, amenity_name) VALUES
(1, '🅿️|Free Parking'),
(1, '❄️|Air Conditioning'),
(1, '🎤|Sound System'),
(1, '💡|Stage Lighting'),
(1, '📽️|Projector & Screen'),
(1, '♿|Wheelchair Access'),
(1, '🛡️|24/7 Security'),
(1, '📶|Free Wi-Fi'),
(2, '🅿️|Valet Parking'),
(2, '❄️|Central Air Conditioning'),
(2, '🎤|Professional Sound System'),
(2, '🍽️|In-House Catering'),
(2, '📽️|LED Wall Display'),
(2, '♿|Wheelchair Access'),
(2, '💐|Floral Arrangements'),
(2, '📶|High-Speed Wi-Fi'),
(3, '🅿️|Free Parking (100 slots)'),
(3, '❄️|Industrial AC System'),
(3, '🎤|Premium Sound System'),
(3, '💡|Architectural Lighting'),
(3, '📽️|Twin Projectors'),
(3, '♿|Wheelchair Access'),
(3, '🛡️|24/7 Security'),
(3, '📶|Free Wi-Fi');

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