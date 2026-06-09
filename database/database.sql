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
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NULL, -- NULL for anonymous users during checkout
    password VARCHAR(255),
    phone VARCHAR(20),
    role ENUM('user', 'admin') DEFAULT 'user'
    INDEX idx_email (email),
    INDEX idx_role (role)
);

-- ======================================================
-- 2. EVENT_TYPES TABLE (NEW - For filtering addons)
-- ======================================================
CREATE TABLE event_types (
    event_type_id INT AUTO_INCREMENT PRIMARY KEY,
    event_type_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT
);

-- ======================================================
-- 3. VENUES TABLE (Normalized - no denormalized fields)
-- ======================================================
CREATE TABLE venues (
    venue_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    location VARCHAR(200) NOT NULL,
    capacity INT NOT NULL CHECK (capacity > 0),
    price_per_guest DECIMAL(10, 2) NOT NULL CHECK (price_per_guest >= 0),
    description TEXT,
    image_url VARCHAR(500),
    FOREIGN KEY fk_venue_creator (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_location (location),
    INDEX idx_price (price_per_guest)
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
        REFERENCES venues(venue_id)
        ON DELETE CASCADE,
    INDEX idx_venue_id (venue_id)
);

-- ======================================================
-- 5. ADDONS TABLE (Linked to EventTypes)
-- ======================================================
CREATE TABLE addons (
    addon_id INT AUTO_INCREMENT PRIMARY KEY,
    event_type_id INT NOT NULL,
    addon_name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL CHECK (price >= 0),
    FOREIGN KEY (event_type_id)
        REFERENCES event_types(event_type_id)
        ON DELETE CASCADE,
    INDEX idx_event_type_id (event_type_id)
);

-- ======================================================
-- 6. CARTS TABLE (NEW - For shopping cart functionality)
-- ======================================================
CREATE TABLE carts (
    cart_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status ENUM('active', 'checked_out', 'abandoned') DEFAULT 'active',
    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
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
    event_type_id INT NOT NULL,
    event_date DATE NOT NULL CHECK (event_date >= CURDATE()),
    event_time TIME NOT NULL,
    duration INT NOT NULL CHECK (duration > 0), -- in hours
    guest_count INT NOT NULL CHECK (guest_count > 0),
    total_price DECIMAL(10, 2) NOT NULL DEFAULT 0,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE,
    FOREIGN KEY (cart_id)
        REFERENCES carts(cart_id)
        ON DELETE CASCADE,
    FOREIGN KEY (venue_id)
        REFERENCES venues(venue_id)
        ON DELETE RESTRICT,
    FOREIGN KEY (event_type_id)
        REFERENCES event_types(event_type_id)
        ON DELETE RESTRICT,
    INDEX idx_user_id (user_id),
    INDEX idx_cart_id (cart_id),
    INDEX idx_venue_id (venue_id),
    INDEX idx_event_type_id (event_type_id),
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
        REFERENCES users(user_id)
        ON DELETE CASCADE,
    FOREIGN KEY (venue_id)
        REFERENCES venues(venue_id)
        ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_venue_id (venue_id),
    INDEX idx_rating (rating)
);

-- ======================================================
-- SAMPLE DATA FOR TESTING
-- ======================================================

-- Event Types
INSERT INTO event_types (event_type_name, description) VALUES
('Wedding', 'Classic wedding ceremony and reception'),
('Corporate', 'Business conferences, team building, and corporate events'),
('Birthday', 'Birthday parties and celebrations'),
('Seminar', 'Educational seminars and workshops'),
('Barangay Fiesta', 'Community celebrations and festivals');

-- Users
INSERT INTO users (first_name, last_name, email, password, phone, role) VALUES
('Maria', 'Santos', 'maria@email.com', SHA2('password123', 256), '09123456789', 'user'),
('Juan', 'Cruz', 'juan@email.com', SHA2('password123', 256), '09987654321', 'user'),
('Admin', 'User', 'admin@tagpo.com', SHA2('admin123', 256), '09111111111', 'admin');

-- Venues
INSERT INTO venues (name, location, capacity, price_per_guest, description) VALUES
('Grand Ballroom Manila', 'Makati, Metro Manila', 500, 2500.00, 'Luxurious ballroom with modern amenities'),
('Beach Resort Boracay', 'Boracay, Aklan', 300, 1500.00, 'Beautiful beachfront venue perfect for weddings'),
('Business Center BGC', 'Bonifacio Global City, Taguig', 200, 1200.00, 'State-of-the-art corporate event space');

-- Amenities
INSERT INTO amenities (venue_id, amenity_name) VALUES
(1, 'Air Conditioning'),
(1, 'WiFi'),
(1, 'Parking'),
(1, 'Sound System'),
(2, 'Beach Access'),
(2, 'WiFi'),
(2, 'Outdoor Pavilion'),
(3, 'Meeting Rooms'),
(3, 'Video Conference Equipment'),
(3, 'WiFi');

-- Addons (grouped by event type)
INSERT INTO addons (event_type_id, addon_name, description, price) VALUES
-- Wedding Addons
(1, 'Catering Service', 'Full course meal for guests', 500.00),
(1, 'DJ Service', '5-hour DJ service with MC', 3000.00),
(1, 'Flower Arrangements', 'Decorative flowers for venue', 2000.00),
(1, 'Photography', '8-hour photography coverage', 5000.00),
(1, 'Videography', '8-hour video coverage with editing', 7000.00),
-- Corporate Addons
(2, 'Catering Service', 'Breakfast/lunch for attendees', 400.00),
(2, 'AV Equipment', 'Projector, screen, and sound system', 5000.00),
(2, 'Event Coordinator', 'Professional event management for 8 hours', 4000.00),
(2, 'Coffee Break Service', 'Morning and afternoon coffee breaks', 800.00),
-- Birthday Addons
(3, 'Catering Service', 'Buffet and dessert service', 300.00),
(3, 'Entertainment', 'Live band or DJ', 2000.00),
(3, 'Cake & Decorations', 'Custom cake and balloon decorations', 1500.00),
(3, 'Photo Booth', '3-hour photo booth with prints', 2500.00);

-- User 1 creates a cart
INSERT INTO carts (user_id, status) VALUES (1, 'active');

-- Sample booking in cart
INSERT INTO bookings (user_id, cart_id, venue_id, event_type_id, event_date, event_time, duration, guest_count, total_price, status) VALUES
(1, 1, 1, 1, '2026-12-15', '18:00:00', 6, 150, 0, 'pending');

-- Add addons to booking
INSERT INTO booking_addons (booking_id, addon_id, quantity, unit_price_at_booking) VALUES
(1, 1, 1, 500.00), -- Catering
(1, 2, 1, 3000.00), -- DJ
(1, 3, 1, 2000.00); -- Flowers

-- Update total price: (venue_price * guest_count * duration) + addons
UPDATE bookings SET total_price = (2500 * 150 * 6) + (500 + 3000 + 2000) WHERE booking_id = 1;

-- Sample payment
INSERT INTO payments (booking_id, amount, payment_method, payment_status, card_holder_name, card_last_four) VALUES
(1, 2255500.00, 'credit_card', 'pending', 'MARIA SANTOS', '4242');

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