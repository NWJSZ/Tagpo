# TAGPO - Fixes, Improvements & Database Setup

## 🔴 CRITICAL ISSUE: Login Redirect Bug

### Problem
When users login, they're redirected to `auth/index.php` instead of `index.php`

### Root Cause
The `getBaseUrl()` function in `config/session_config.php` (lines 72-84) calculates the base URL from `PHP_SELF`. When called from `auth/login.php`, it includes `/auth` in the path.

**Current logic:**
```php
function getBaseUrl() {
    $path = dirname($_SERVER['PHP_SELF']);
    if ($path === '/' || $path === '\\') {
        return '/Tagpo/';
    }
    
    // Remove /config from path if present
    if (strpos($path, '/config') !== false) {
        $path = str_replace('/config', '', $path);
    }
    
    return rtrim($path, '/') . '/';
}
```

**When `auth/login.php` calls this:**
- `PHP_SELF` = `/Tagpo/auth/login.php`
- `dirname()` = `/Tagpo/auth`
- Returns `/Tagpo/auth/` ❌ (should be `/Tagpo/`)

### ✅ SOLUTION: Update `config/session_config.php`

Replace the `getBaseUrl()` function (lines 72-84) with:

```php
// ========================================
// HELPER FUNCTION: Get base URL
// ========================================
function getBaseUrl() {
    // Get the base directory (one level above current script)
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $scriptDir = dirname($_SERVER['SCRIPT_FILENAME']);
    
    // Calculate relative path from web root
    $relativePath = str_replace($docRoot, '', $scriptDir);
    
    // Remove any /auth or /admin or /config subdirectories from the end
    $relativePath = preg_replace('#/(auth|admin|config)/?$#', '', $relativePath);
    
    // Ensure it starts with /
    if (empty($relativePath)) {
        return '/';
    }
    
    return rtrim($relativePath, '/') . '/';
}
```

---

## 📁 Recommended Folder Structure Improvements

### Current Structure Issues:
1. ❌ Database folder is empty - no DB connection file
2. ❌ No `.env` or config file for database credentials
3. ❌ JavaScript/CSS paths are inconsistent
4. ❌ No separation of concerns (models, controllers, utilities)

### ✅ Improved Structure:

```
Tagpo/
├── config/
│   ├── session_config.php      (✓ exists, needs fix)
│   ├── database.php            (NEW) - DB connection
│   └── .env.example            (NEW) - Environment template
│
├── database/
│   ├── init.sql                (NEW) - Schema & sample data
│   ├── migrations/             (NEW) - Future migrations
│   └── seeds/                  (NEW) - Sample data
│
├── includes/
│   ├── header.php              (✓ exists)
│   ├── footer.php              (✓ exists)
│   └── helpers.php             (NEW) - Utility functions
│
├── auth/
│   ├── login.php               (✓ exists)
│   ├── signup.php              (✓ exists)
│   └── logout.php              (✓ exists)
│
├── admin/
│   ├── admin.php               (✓ exists)
│   ├── add_venue.php           (✓ exists)
│   └── delete_venue.php        (✓ exists)
│
├── assets/
│   ├── css/
│   │   ├── styles.css          (✓ exists)
│   │   └── loginsignup.css     (✓ exists)
│   ├── js/
│   │   └── *.js                (✓ exists)
│   └── images/                 (✓ exists - 4.3MB)
│
├── public/                     (OPTIONAL) - Static files
│
├── .gitignore                  (NEW)
├── .env                        (NEW) - Local only, don't commit
├── index.php                   (✓ exists)
├── booking.php                 (✓ exists)
├── cart.php                    (✓ exists)
├── search.php                  (✓ exists)
├── wishlist.php                (✓ exists)
├── venue.php                   (✓ exists)
└── README.md                   (✓ exists)
```

---

## 🗄️ Database Setup Instructions (XAMPP)

### Step 1: Create Database Connection File

Create `config/database.php`:

```php
<?php
/**
 * Database Connection Handler
 * Change credentials in .env file or directly here
 */

// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'tagpo_db');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Helper function: Execute query safely
function executeQuery($conn, $sql) {
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    return $result;
}

// Helper function: Get single row
function getRow($conn, $sql) {
    $result = $conn->query($sql);
    return $result ? $result->fetch_assoc() : null;
}

// Helper function: Get multiple rows
function getRows($conn, $sql) {
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

?>
```

### Step 2: Create Database Schema

Create `database/init.sql`:

```sql
-- Create database
CREATE DATABASE IF NOT EXISTS tagpo_db;
USE tagpo_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Venues table
CREATE TABLE IF NOT EXISTS venues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    location VARCHAR(200) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    capacity INT NOT NULL,
    rating DECIMAL(3, 1),
    reviews INT DEFAULT 0,
    description TEXT,
    image_url VARCHAR(500),
    tag VARCHAR(100),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX (location),
    INDEX (price)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Bookings table
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    venue_id INT NOT NULL,
    event_date DATE NOT NULL,
    guest_count INT NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    activities JSON,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (venue_id) REFERENCES venues(id),
    INDEX (user_id),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activities table
CREATE TABLE IF NOT EXISTS activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    description TEXT,
    duration_minutes INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (venue_id) REFERENCES venues(id) ON DELETE CASCADE,
    INDEX (venue_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin
INSERT INTO users (name, email, password, role) VALUES 
('Admin', 'admin@tagpo.com', 'admin123', 'admin');

-- Insert sample venues
INSERT INTO venues (name, location, price, capacity, rating, reviews, tag, image_url) VALUES 
('Paradiso Terrestre', 'Molino, Cavite City', 35000, 500, 4.8, 36, 'Wedding · Debut', 'assets/images/paradiso1.jpg'),
('Blue Gardens', 'Makati City', 60000, 250, 4.9, 52, 'Prom · Gala', 'assets/images/gardens1.jpg'),
('The Green Lounge Events Place', 'Quezon City', 45000, 300, 4.7, 28, 'Birthday · Corporate', 'assets/images/lounge1.jpg');
```

### Step 3: Import Schema in XAMPP

**Option A: Using phpMyAdmin**
1. Open `http://localhost/phpmyadmin`
2. Click "Import" tab
3. Upload `database/init.sql`
4. Click "Go"

**Option B: Using Command Line**
```bash
cd C:\xampp\mysql\bin
mysql -u root < "C:\path\to\Tagpo\database\init.sql"
```

### Step 4: Create .env File

Create `.env` in project root:

```env
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=tagpo_db

APP_ENV=development
APP_DEBUG=true
SESSION_TIMEOUT=600
```

### Step 5: Update `config/session_config.php`

Add at the top (after `<?php`):

```php
// Load environment variables if .env exists
if (file_exists(dirname(__DIR__) . '/.env')) {
    $env = parse_ini_file(dirname(__DIR__) . '/.env');
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
}
```

---

## 🚀 Other Issues Found & Fixes

### 1. Missing `$baseUrl` in `index.php`
**File:** `index.php`

**Issue:** Header.php uses `$baseUrl` variable but it's not defined in index.php

**Fix:** Add before `<?php include 'includes/header.php'; ?>` on line 109:

```php
<?php
$baseUrl = getBaseUrl();
?>
```

### 2. Inconsistent Path References
**Files:** All PHP files linking to auth pages

**Current:** Sometimes uses `login.php`, sometimes `auth/login.php`
**Fix:** Use consistent paths: `<?php echo getBaseUrl(); ?>auth/login.php`

### 3. Missing 404 Handling
**Issue:** No error page for invalid venue IDs

**Suggestion:** Add at top of `venue.php`:
```php
<?php
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(404);
    die("Venue not found");
}
?>
```

### 4. SQL Injection Risk (When DB is integrated)
**Current:** `$_GET['id']` used directly in queries

**Fix:** Use prepared statements:
```php
$stmt = $conn->prepare("SELECT * FROM venues WHERE id = ?");
$stmt->bind_param("i", $_GET['id']);
$stmt->execute();
$result = $stmt->get_result();
```

### 5. Missing Input Validation
**Files:** `auth/login.php`, `auth/signup.php`

**Current:** Basic validation only

**Improvement:** Add on lines 14-15 in auth/login.php:
```php
$email = trim($_POST['email']);
$password = $_POST['password'];

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Invalid email format!";
}

// Validate password strength in signup
if (strlen($password) < 6) {
    $error = "Password must be at least 6 characters!";
}
```

---

## ✅ Verification Checklist

- [ ] Fix `getBaseUrl()` in `config/session_config.php`
- [ ] Create `config/database.php`
- [ ] Create `database/init.sql`
- [ ] Create `.env` file
- [ ] Import database schema in phpMyAdmin/MySQL
- [ ] Add `$baseUrl = getBaseUrl();` to all main pages
- [ ] Test login redirect (should go to `/Tagpo/index.php`, not `/Tagpo/auth/index.php`)
- [ ] Test admin panel access
- [ ] Test cart functionality
- [ ] Test venue search & filtering

---

## 🚀 XAMPP Quick Start

1. **Start XAMPP:**
   - Open XAMPP Control Panel
   - Start Apache & MySQL

2. **Access project:**
   - Place Tagpo folder in `C:\xampp\htdocs\` (Windows) or `/Applications/XAMPP/htdocs/` (Mac)
   - Visit `http://localhost/Tagpo/`

3. **Default Login:**
   - Email: `admin@tagpo.com`
   - Password: `admin123`

4. **Check database:**
   - Visit `http://localhost/phpmyadmin`
   - Verify `tagpo_db` database was created

---

## 📝 Next Steps

1. ✅ Apply the `getBaseUrl()` fix first
2. ✅ Set up database with init.sql
3. ✅ Integrate database.php into your pages
4. ✅ Migrate data from JSON/Session to MySQL
5. 🔐 Add password hashing (use `password_hash()`)
6. 🔍 Add input validation & sanitization
7. 📊 Implement proper error logging

---

**Last Updated:** June 4, 2026
**Project:** LakbayLokal (TAGPO)
**Status:** Ready for database integration
