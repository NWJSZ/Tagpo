-- ======================================================
-- TAGPO EVENT VENUE BOOKING SYSTEM
-- Complete Normalized Database Schema - 9 Entities
-- ======================================================

DROP DATABASE IF EXISTS tagpo_db;
CREATE DATABASE tagpo_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tagpo_db;

-- ======================================================
-- 1. USERS TABLE
-- ======================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NULL, -- NULL for anonymous users during checkout
    password VARCHAR(255),
    phone VARCHAR(20),
    role ENUM('user', 'admin') DEFAULT 'user',
    INDEX idx_email (email),
    INDEX idx_role (role)
);

-- ======================================================
-- 2. EVENT TABLE (NEW - For filtering addons)
-- ======================================================
CREATE TABLE event (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT
);

-- ======================================================
-- 3. VENUES TABLE (Normalized - no denormalized fields)
-- ======================================================
CREATE TABLE venues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    location VARCHAR(200) NOT NULL,
    capacity INT NOT NULL CHECK (capacity > 0),
    price DECIMAL(10, 2) NOT NULL CHECK (price >= 0),
    rating DECIMAL(3, 1) DEFAULT 0,
    reviews INT DEFAULT 0,
    description TEXT,
    tag VARCHAR(100),
    image_url VARCHAR(500),

    INDEX idx_location (location),
    INDEX idx_price (price)
);

-- ======================================================
-- 4. AMENITIES TABLE
-- Stores venue amenities separately to avoid denormalization
-- ======================================================
CREATE TABLE amenities (
    amenity_id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NOT NULL,
    amenity_name VARCHAR(100) NOT NULL,
    FOREIGN KEY (venue_id)
        REFERENCES venues(id)
        ON DELETE CASCADE,
    INDEX idx_venue_id (venue_id)
);

-- ======================================================
-- 5. ADDONS TABLE (Linked to Event)
-- ======================================================
CREATE TABLE addons (
    addon_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    addon_name VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL CHECK (price >= 0),
    FOREIGN KEY (event_id)
        REFERENCES event(event_id)
        ON DELETE CASCADE,
    INDEX idx_event_id (event_id)
);

-- ======================================================
-- 6. CARTS TABLE (NEW - For shopping cart functionality)
-- ======================================================
CREATE TABLE carts (
    cart_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status ENUM('active', 'checked_out', 'abandoned') DEFAULT 'active',
    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
);

-- ======================================================
-- 7. BOOKINGS TABLE
-- Links user, cart, venue, and event type
-- ======================================================
CREATE TABLE bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    cart_id INT NOT NULL,
    venue_id INT NOT NULL,
    event_id INT NOT NULL,
    event_date DATE NOT NULL CHECK (event_date >= CURDATE()),
    event_time TIME NOT NULL,
    duration INT NOT NULL CHECK (duration > 0), -- in hours
    guest_count INT NOT NULL CHECK (guest_count > 0),
    total_price DECIMAL(10, 2) NOT NULL DEFAULT 0,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,
    FOREIGN KEY (cart_id)
        REFERENCES carts(cart_id)
        ON DELETE CASCADE,
    FOREIGN KEY (venue_id)
        REFERENCES venues(id)
        ON DELETE RESTRICT,
    FOREIGN KEY (event_id)
        REFERENCES event(event_id)
        ON DELETE RESTRICT,
    INDEX idx_user_id (user_id),
    INDEX idx_cart_id (cart_id),
    INDEX idx_venue_id (venue_id),
    INDEX idx_event_id (event_id),
    INDEX idx_event_date (event_date),
    INDEX idx_status (status)
);

-- ======================================================
-- 8. BOOKING_ADDONS TABLE (Junction Table - Many-to-Many)
-- Stores historical pricing for audit trail
-- ======================================================
CREATE TABLE booking_addons (
    booking_id INT NOT NULL,
    addon_id INT NOT NULL,
    quantity INT DEFAULT 1 CHECK (quantity > 0),
    unit_price_at_booking DECIMAL(10, 2) NOT NULL DEFAULT 0,
    PRIMARY KEY (booking_id, addon_id),
    FOREIGN KEY (booking_id)
        REFERENCES bookings(booking_id)
        ON DELETE CASCADE,
    FOREIGN KEY (addon_id)
        REFERENCES addons(addon_id)
        ON DELETE RESTRICT,
    INDEX idx_addon_id (addon_id)
);

-- ======================================================
-- 9. PAYMENTS TABLE
-- ======================================================
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL CHECK (amount > 0),
    payment_method ENUM('credit_card', 'debit_card', 'gcash') NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    transaction_id VARCHAR(100),
    -- For credit/debit card
    card_holder_name VARCHAR(200),
    card_last_four VARCHAR(4),
    card_expiry_month INT,
    card_expiry_year INT,
    -- For GCash
    gcash_phone_number VARCHAR(20),
    gcash_account_name VARCHAR(200),
    -- Shared
    user_phone VARCHAR(20),
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id)
        REFERENCES bookings(booking_id)
        ON DELETE CASCADE,
    INDEX idx_booking_id (booking_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_transaction_id (transaction_id)
);

-- ======================================================
-- 10. REVIEWS TABLE
-- ======================================================
CREATE TABLE reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    venue_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    review_text TEXT,
    review_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE,
    FOREIGN KEY (venue_id)
        REFERENCES venues(id)
        ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_venue_id (venue_id),
    INDEX idx_rating (rating)
);

-- Insert default admin user
INSERT INTO users (name, first_name, last_name, email, password, role) VALUES 
('Admin User', 'Admin', 'User', 'admin@tagpo.com', 'admin123', 'admin');


-- Insert sample venues
INSERT INTO venues (name, location, price, capacity, rating, reviews, description, tag, image_url) VALUES 
(
    'Paradiso Terrestre',
    'Molino, Cavite City',
    35000,
    500,
    4.8,
    36,
    'A stunning beachfront venue perfect for weddings and debuts with breathtaking sunset views.',
    'Wedding · Debut',
    'assets/images/paradiso1.jpg'
),
(
    'Blue Gardens',
    'Makati City',
    60000,
    250,
    4.9,
    52,
    'Elegant garden venue ideal for proms and galas with modern amenities and exquisite landscaping.',
    'Prom · Gala',
    'assets/images/gardens1.jpg'
),
(
    'The Green Lounge Events Place',
    'Quezon City',
    45000,
    300,
    4.7,
    28,
    'Contemporary event space suitable for birthday parties, corporate events, and various celebrations.',
    'Birthday · Corporate',
    'assets/images/lounge1.jpg'
);

--insert event
INSERT INTO event (event_id, event_name, description) VALUES 
(1, 'Wedding', 'A ceremony where two people are united in marriage.'),
(2, 'Birthday', "A celebration of the anniversary of a person\'s birth."),
(3, 'Corporate Event', 'An event organized by a company for its employees or clients.'),
(4, 'Prom', 'A formal dance or gathering of high school students.'),
(5, 'Gala', 'A social occasion with special entertainments or performances.')
;


-- Insert sample addon for Paradiso Terrestre
INSERT INTO addons (event_id, addon_name, price) VALUES 
(1, 'Catering Service', 8000),
(1, 'Bridal Car', 3500),
(1, 'Floral Arrangement Package', 2500),
(1, 'Wedding Stage Decoration', 4000),
(1, 'Photo Booth', 2500);

INSERT INTO addons (event_id, addon_name, price) VALUES 
(2, 'Catering Service', 6000),
(2, 'Balloon & Themed Setup', 2000),
(2, 'Photo Booth', 2500),
(2, 'Clown / Event Host', 1500),
(2, 'Cake Styling Setup', 1000);



-- ======================================================
-- END OF SCHEMA
-- ======================================================

-- ======================================================
-- USEFUL QUERIES
-- ======================================================

-- Get all addons for a specific event type
-- SELECT * FROM addons WHERE event_type_id = 1;

-- Get all bookings in a user's active cart
-- SELECT b.* FROM bookings b
-- JOIN carts c ON b.cart_id = c.cart_id
-- WHERE c.user_id = 1 AND c.status = 'active';

-- Get total price of a booking (with addons)
-- SELECT 
--   (v.price_per_guest * b.guest_count * b.duration) + 
--   COALESCE(SUM(ba.unit_price_at_booking * ba.quantity), 0) as total
-- FROM bookings b
-- JOIN venues v ON b.venue_id = v.venue_id
-- LEFT JOIN booking_addons ba ON b.booking_id = ba.booking_id
-- WHERE b.booking_id = 1
-- GROUP BY b.booking_id;

-- Get venue details with amenities
-- SELECT v.*, GROUP_CONCAT(a.amenity_name) as amenities
-- FROM venues v
-- LEFT JOIN amenities a ON v.venue_id = a.venue_id
-- WHERE v.venue_id = 1
-- GROUP BY v.venue_id;

-- Get venue reviews with ratings
-- SELECT r.*, CONCAT(u.first_name, ' ', u.last_name) as reviewer_name
-- FROM reviews r
-- JOIN users u ON r.user_id = u.user_id
-- WHERE r.venue_id = 1
-- ORDER BY r.review_date DESC;
