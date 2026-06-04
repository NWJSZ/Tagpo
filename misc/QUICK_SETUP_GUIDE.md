# TAGPO - Quick Implementation Guide

## 🚀 Quick Setup (5 minutes)

### Step 1: Fix Login Redirect Bug (CRITICAL)

1. **Backup original file:**
   ```bash
   cp config/session_config.php config/session_config.php.backup
   ```

2. **Replace your `config/session_config.php` with the fixed version:**
   - Download `session_config_FIXED.php`
   - Replace entire contents of your `config/session_config.php` with it
   - Save

3. **Test:**
   - Visit `http://localhost/Tagpo/`
   - Click Login
   - Use: `admin@tagpo.com` / `admin123`
   - Should redirect to `http://localhost/Tagpo/index.php` ✅

---

### Step 2: Set Up Database (10 minutes)

1. **Create `.env` file in project root:**
   - Copy `.env.example` → `.env`
   - Keep default values (localhost, root user, empty password)

2. **Create `config/database.php`:**
   - Copy the `database.php` file to `Tagpo/config/database.php`

3. **Import database schema:**

   **Option A: phpMyAdmin (Easiest)**
   ```
   1. Open http://localhost/phpmyadmin
   2. Click "Import" tab
   3. Select init.sql file
   4. Click "Go"
   ```

   **Option B: Command Line**
   ```bash
   mysql -u root < init.sql
   ```

   **Option C: MySQL Workbench**
   ```
   1. Open MySQL Workbench
   2. File → Open SQL Script
   3. Select init.sql
   4. Execute (Ctrl+Shift+Enter)
   ```

4. **Verify database created:**
   - Open phpMyAdmin
   - Check left sidebar → should see `tagpo_db`
   - Click it → see all tables

---

### Step 3: Update Main Pages (5 minutes)

Add this line to all pages that include `header.php`:

**Files to update:** `index.php`, `search.php`, `venue.php`, `booking.php`, `cart.php`, `checkout.php`, `receipt.php`, `wishlist.php`

**Add before the first include statement:**
```php
<?php
$baseUrl = getBaseUrl();
?>
```

**Example for index.php (line 2):**
```php
<?php
require_once 'config/session_config.php';

// ADD THIS LINE:
$baseUrl = getBaseUrl();

// Rest of code...
?>
```

---

### Step 4: Verify Everything Works

**✅ Login Test:**
- Visit `http://localhost/Tagpo/`
- Click "Log In"
- Enter:
  - Email: `admin@tagpo.com`
  - Password: `admin123`
- Should redirect to home page and show "Hi, Admin User" ✅

**✅ Navigation Test:**
- Click all navbar links
- Should not have broken paths

**✅ Admin Test:**
- Should see "Admin" dropdown in navbar
- Should be able to access admin panel

**✅ Database Test:**
- Open `http://localhost/phpmyadmin`
- Select `tagpo_db`
- See all tables created with sample data

---

## 📋 File Changes Summary

### Files to Replace:
```
Tagpo/config/session_config.php  ← Replace with session_config_FIXED.php
```

### Files to Create:
```
Tagpo/config/database.php        ← New file
Tagpo/.env                        ← New file (copy from .env.example)
Tagpo/database/init.sql           ← Schema file for database import
```

### Files to Update (Add $baseUrl = getBaseUrl();):
```
Tagpo/index.php
Tagpo/search.php
Tagpo/venue.php
Tagpo/booking.php
Tagpo/cart.php
Tagpo/checkout.php
Tagpo/receipt.php
Tagpo/wishlist.php
```

---

## 🔍 Troubleshooting

### Problem: Login still goes to /auth/index.php
**Solution:** 
- Verify you saved `session_config_FIXED.php` correctly
- Clear browser cache (Ctrl+Shift+Delete)
- Restart XAMPP

### Problem: Database connection fails
**Solution:**
- Verify MySQL is running (check XAMPP Control Panel)
- Check phpMyAdmin: `http://localhost/phpmyadmin`
- Verify `tagpo_db` database exists
- Check `config/database.php` has correct credentials

### Problem: admin@tagpo.com doesn't exist in database
**Solution:**
- Run `init.sql` to populate default admin user
- Check phpMyAdmin: `tagpo_db` → `users` table

### Problem: "Database connection failed" error
**Solution:**
- The project needs `config/database.php`
- Without it, database features won't work (but basic PHP features still work)
- For now, focus on fixing the login redirect bug first

---

## 📝 Next Steps (After Basics Work)

1. **Integrate Database into Pages:**
   - Update `auth/login.php` to use database instead of hardcoded values
   - Move cart data from session to database
   - Save bookings to database

2. **Add Password Security:**
   ```php
   // In auth/signup.php:
   $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
   ```

3. **Add Input Validation:**
   - Email format validation
   - Password strength requirements
   - XSS/CSRF protection

4. **Error Handling:**
   - Add try-catch blocks
   - Log errors to file
   - Show user-friendly messages

---

## 🎯 Current Status After These Fixes

✅ Login redirects correctly
✅ Database schema created
✅ Basic structure improved
⚠️ Database not yet integrated into pages (phase 2)
⚠️ Payment processing not implemented
⚠️ Email notifications not configured

---

## 📞 Need Help?

Check these before asking:
1. Did you replace `session_config.php`?
2. Did you run `init.sql` in phpMyAdmin?
3. Is MySQL/XAMPP running?
4. Are you using the correct URLs?

---

**Last Updated:** June 4, 2026
**Version:** 1.0
