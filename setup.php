<?php
/**
 * TAGPO Database Setup Script
 * Run this file ONCE in your browser: http://localhost/event_system/Tagpo/setup.php
 * This will initialize the database and create all tables
 */

set_time_limit(300);
ini_set('display_errors', 1);
error_reporting(E_ALL);

$host = 'localhost';
$user = 'root';
$password = '';
$database = 'tagpo_db';
$port = 3306;

// Step 1: Connect to MySQL (without database first)
echo "<h2>🔧 TAGPO Database Setup</h2>";
echo "<p>Step 1: Connecting to MySQL...</p>";

$conn = new mysqli($host, $user, $password, '', $port);
if ($conn->connect_error) {
    die("<p style='color:red;'>❌ Connection failed: " . $conn->connect_error . "</p>");
}
echo "<p style='color:green;'>✅ Connected to MySQL</p>";

// Step 2: Create Database
echo "<p>Step 2: Creating database '{$database}'...</p>";
$sql = "CREATE DATABASE IF NOT EXISTS `{$database}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "<p style='color:green;'>✅ Database created/exists</p>";
} else {
    die("<p style='color:red;'>❌ Error creating database: " . $conn->error . "</p>");
}

// Step 3: Select Database
echo "<p>Step 3: Selecting database...</p>";
if (!$conn->select_db($database)) {
    die("<p style='color:red;'>❌ Error selecting database: " . $conn->error . "</p>");
}
echo "<p style='color:green;'>✅ Database selected</p>";

// Step 4: Import SQL
echo "<p>Step 4: Creating tables...</p>";

$sql_queries = array(
    // Users Table
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('user', 'admin') DEFAULT 'user',
        phone VARCHAR(20),
        address VARCHAR(255),
        city VARCHAR(100),
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_role (role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Venues Table
    "CREATE TABLE IF NOT EXISTS venues (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        location VARCHAR(200) NOT NULL,
        price DECIMAL(12, 2) NOT NULL,
        capacity INT NOT NULL,
        rating DECIMAL(3, 1) DEFAULT 0,
        reviews INT DEFAULT 0,
        description TEXT,
        image_url VARCHAR(500),
        tag VARCHAR(100),
        created_by INT,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY fk_venue_creator (created_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_location (location),
        INDEX idx_price (price),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Activities Table
    "CREATE TABLE IF NOT EXISTS activities (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Bookings Table
    "CREATE TABLE IF NOT EXISTS bookings (
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
        INDEX idx_event_date (event_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

foreach ($sql_queries as $index => $query) {
    if ($conn->query($query) === TRUE) {
        echo "<p style='color:green;'>✅ Table created successfully</p>";
    } else {
        echo "<p style='color:orange;'>⚠️ Table " . ($index + 1) . ": " . $conn->error . "</p>";
    }
}

// Step 5: Create Admin User
echo "<p>Step 5: Creating admin user...</p>";
$admin_email = 'admin@tagpo.com';
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);

$check_user = $conn->query("SELECT id FROM users WHERE email = '{$admin_email}'");
if ($check_user->num_rows == 0) {
    $insert_admin = "INSERT INTO users (name, email, password, role) VALUES ('Admin User', '{$admin_email}', '{$admin_password}', 'admin')";
    if ($conn->query($insert_admin) === TRUE) {
        echo "<p style='color:green;'>✅ Admin user created (email: admin@tagpo.com, password: admin123)</p>";
    } else {
        echo "<p style='color:red;'>❌ Error creating admin user: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color:orange;'>⚠️ Admin user already exists</p>";
}

echo "<hr>";
echo "<h3 style='color:green;'>✅ Database Setup Complete!</h3>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Go to: <a href='http://localhost/event_system/Tagpo/'>http://localhost/event_system/Tagpo/</a></li>";
echo "<li>Click 'Log In' button</li>";
echo "<li>Use credentials: <strong>admin@tagpo.com</strong> / <strong>admin123</strong></li>";
echo "<li>Verify all pages load with styling</li>";
echo "</ol>";
echo "<p><strong>⚠️ SECURITY NOTE:</strong> Delete this setup.php file after setup is complete!</p>";
echo "<p><code style='background:#eee;padding:10px;display:inline-block;'>rm setup.php</code></p>";

$conn->close();
?>
