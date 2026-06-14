-- =====================================================
-- DATABASE SETUP
-- =====================================================

DROP DATABASE IF EXISTS tagpo_db;

-- Create database for the system
CREATE DATABASE tagpo_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

-- Select database to use
USE tagpo_db;


-- =====================================================
-- USERS TABLE
-- Stores all system users (admin + regular users)
-- =====================================================

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY, -- unique user ID
    first_name VARCHAR(50) NOT NULL,   -- user's first name
    last_name VARCHAR(50) NOT NULL,    -- user's last name
    email VARCHAR(100) NOT NULL UNIQUE,-- login/email (must be unique)
    phone VARCHAR(20),                 -- optional contact number
    password VARCHAR(255) NOT NULL,    -- hashed password
    role ENUM('user','admin') DEFAULT 'user' -- user type
);


-- =====================================================
-- EVENT TYPES TABLE
-- List of allowed event categories
-- =====================================================

CREATE TABLE event (
    event_id INT AUTO_INCREMENT PRIMARY KEY, -- event ID
    event_name VARCHAR(100) NOT NULL UNIQUE   -- event name (Wedding, Birthday, etc.)
);


-- =====================================================
-- VENUES TABLE
-- Stores venue details
-- =====================================================

CREATE TABLE venues (
    id INT AUTO_INCREMENT PRIMARY KEY, -- venue ID
    name VARCHAR(100) NOT NULL,        -- venue name
    location VARCHAR(255) NOT NULL,    -- venue location/address
    capacity INT NOT NULL,             -- max guests
    price DECIMAL(10,2) NOT NULL,      -- base price
    description TEXT,                  -- venue description
    image_url VARCHAR(255)             -- main image
);


-- =====================================================
-- VENUE_EVENTS (MANY-TO-MANY)
-- A venue can support multiple events
-- =====================================================

CREATE TABLE venue_events (
    venue_id INT NOT NULL,  -- FK to venues
    event_id INT NOT NULL,  -- FK to event

    PRIMARY KEY (venue_id, event_id), -- prevents duplicates

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
-- Stores multiple images per venue (1-to-many)
-- =====================================================

CREATE TABLE venue_gallery (
    gallery_id INT AUTO_INCREMENT PRIMARY KEY, 
    -- Unique ID for each image entry

    venue_id INT NOT NULL, 
    -- Foreign key: links image to a specific venue

    image_url VARCHAR(255) NOT NULL, 
    -- Path or URL of the image file

    label VARCHAR(100) DEFAULT NULL, 
    -- Optional caption (e.g., "Main Hall", "Garden View")

    sort_order TINYINT UNSIGNED DEFAULT 0, 
    -- Controls display order (0 = default, higher = later position)

    FOREIGN KEY (venue_id)
        REFERENCES venues(id)
        ON DELETE CASCADE
        -- If venue is deleted, all its images are also deleted

        ON UPDATE CASCADE
        -- If venue ID changes, update automatically in this table
);


-- =====================================================
-- VENUE HIGHLIGHTS
-- Key features of each venue
-- =====================================================

CREATE TABLE venue_highlights (
    highlight_id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NOT NULL,
    highlight VARCHAR(255) NOT NULL, -- feature text
    sort_order TINYINT UNSIGNED DEFAULT 0,

    FOREIGN KEY (venue_id)
        REFERENCES venues(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);


-- =====================================================
-- AMENITIES
-- Services available per venue
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
-- Extra services per event type
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