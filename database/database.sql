-- ======================================================
-- TAGPO - Database Schema Initialization
-- Run this in phpMyAdmin or MySQL command line
-- ======================================================

-- Create database
CREATE DATABASE IF NOT EXISTS tagpo_db;
USE tagpo_db;

-- ======================================================
-- USERS TABLE
-- ======================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL, 
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- VENUES TABLE
-- ======================================================
CREATE TABLE IF NOT EXISTS venues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    location VARCHAR(200) NOT NULL,
    price DECIMAL(12, 2) NOT NULL,
    capacity INT NOT NULL,
    rating DECIMAL(3, 1) DEFAULT 0,
    reviews INT DEFAULT 0,
    description TEXT,
    image_url VARCHAR(500),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY fk_venue_creator (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_location (location),
    INDEX idx_price (price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- ACTIVITIES TABLE (For venue packages/add-ons)
-- ======================================================
CREATE TABLE IF NOT EXISTS activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    duration_minutes INT,
    max_count INT DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY fk_activity_venue (venue_id) REFERENCES venues(id) ON DELETE CASCADE,
    INDEX idx_venue (venue_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- BOOKINGS TABLE
-- ======================================================
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    venue_id INT NOT NULL,
    event_date DATE NOT NULL,
    guest_count INT NOT NULL,
    event_type VARCHAR(50),
    venue_package VARCHAR(255),
    subtotal DECIMAL(12, 2),
    activities_total DECIMAL(12, 2) DEFAULT 0,
    discount DECIMAL(12, 2) DEFAULT 0,
    total_price DECIMAL(12, 2) NOT NULL,
    special_requirements TEXT,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    payment_method VARCHAR(50),
    transaction_id VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY fk_booking_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY fk_booking_venue (venue_id) REFERENCES venues(id) ON DELETE RESTRICT,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_event_date (event_date),
    INDEX idx_booking_number (booking_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- BOOKING_ACTIVITIES TABLE (Many-to-Many relationship)
-- ======================================================
CREATE TABLE IF NOT EXISTS booking_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    activity_id INT NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(12, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY fk_ba_booking (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY fk_ba_activity (activity_id) REFERENCES activities(id) ON DELETE CASCADE,
    UNIQUE KEY unique_booking_activity (booking_id, activity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- REVIEWS TABLE
-- ======================================================
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT,
    venue_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY fk_review_booking (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
    FOREIGN KEY fk_review_venue (venue_id) REFERENCES venues(id) ON DELETE CASCADE,
    FOREIGN KEY fk_review_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_venue (venue_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- AUDIT LOG TABLE
-- ======================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY fk_audit_user (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_created_at (created_at),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ======================================================
-- INSERT DEFAULT DATA
-- ======================================================

-- Insert default admin user
INSERT INTO users (name, email, password, role) VALUES 
('Admin User', 'admin@tagpo.com', 'admin123', 'admin');

INSERT INTO users (first_name, last_name, email, password, role) VALUES 
('Jen', 'Ilao', 'jen@gmail.com', 'admin123', 'admin');

-- Insert sample venues
INSERT INTO venues (name, location, price, capacity, rating, reviews, description, tag, image_url, is_active) VALUES 
(
    'Paradiso Terrestre',
    'Molino, Cavite City',
    35000,
    500,
    4.8,
    36,
    'A stunning beachfront venue perfect for weddings and debuts with breathtaking sunset views.',
    'Wedding · Debut',
    'assets/images/paradiso1.jpg',
    TRUE
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
    'assets/images/gardens1.jpg',
    TRUE
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
    'assets/images/lounge1.jpg',
    TRUE
);

-- Insert sample activities for Paradiso Terrestre
INSERT INTO activities (venue_id, name, description, price, duration_minutes) VALUES 
(1, 'Photography Package', '4 hours of professional photography coverage', 8000, 240),
(1, 'Catering Service', 'Full buffet service for up to 100 guests', 15000, 180),
(1, 'Sound & Lighting', 'Professional audio and lighting equipment', 5000, 480);

-- Insert sample activities for Blue Gardens
INSERT INTO activities (venue_id, name, description, price, duration_minutes) VALUES 
(2, 'Decoration Package', 'Complete venue decoration with floral arrangements', 12000, 240),
(2, 'DJ & Entertainment', '4 hours of DJ service with MC', 6000, 240),
(2, 'Premium Catering', 'Multi-course catering for up to 200 guests', 25000, 240);

-- Insert sample activities for The Green Lounge
INSERT INTO activities (venue_id, name, description, price, duration_minutes) VALUES 
(3, 'Cake & Desserts', 'Customized cake and dessert station', 4000, 120),
(3, 'Game & Activities', 'Curated games and activities for guests', 3000, 180),
(3, 'Venue Setup', 'Complete setup and cleanup service', 5000, 240);

-- ======================================================
-- END OF SCHEMA
-- ======================================================
