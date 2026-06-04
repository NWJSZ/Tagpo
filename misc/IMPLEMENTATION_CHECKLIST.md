# TAGPO - Implementation Checklist

## 🎯 Phase 1: Fix Login Redirect (5 minutes)

- [ ] Backup original `config/session_config.php`
  ```bash
  cp Tagpo/config/session_config.php Tagpo/config/session_config.php.backup
  ```

- [ ] Download/copy `session_config_FIXED.php` to your project
  
- [ ] Replace entire contents of `config/session_config.php` with fixed version
  - Delete all content
  - Paste new content from session_config_FIXED.php
  - Save file

- [ ] Clear browser cache
  - Windows: Ctrl + Shift + Delete
  - Mac: Cmd + Shift + Delete

- [ ] Test login redirect:
  - [ ] Go to `http://localhost/Tagpo/`
  - [ ] Click "Log In"
  - [ ] Email: `admin@tagpo.com`
  - [ ] Password: `admin123`
  - [ ] Should go to: `http://localhost/Tagpo/index.php` ✅
  - [ ] Should NOT go to: `http://localhost/Tagpo/auth/index.php` ❌
  - [ ] Should see: "Hi, Admin User 👋" in navbar

- [ ] Test logout and login again
  - [ ] Logout from admin account
  - [ ] Login again to verify fix persists

---

## 🎯 Phase 2: Set Up Database (10 minutes)

- [ ] Create `.env` file:
  - [ ] Copy `.env.example` → Rename to `.env`
  - [ ] Keep default values:
    - DB_HOST=localhost
    - DB_USER=root
    - DB_PASS= (empty)
    - DB_NAME=tagpo_db

- [ ] Copy `database.php` to `Tagpo/config/database.php`
  - [ ] Create new file: `Tagpo/config/database.php`
  - [ ] Paste entire contents of database.php
  - [ ] Save

- [ ] Import database schema:

  **Choose ONE method:**

  **Method A: phpMyAdmin (EASIEST)**
  - [ ] Start XAMPP (Apache + MySQL)
  - [ ] Go to: `http://localhost/phpmyadmin`
  - [ ] Click "Import" tab at top
  - [ ] Click "Choose File" button
  - [ ] Select `init.sql`
  - [ ] Click "Go" button
  - [ ] See success message ✅

  **Method B: Command Line**
  - [ ] Open terminal/command prompt
  - [ ] Navigate to Tagpo folder
  - [ ] Run: `mysql -u root < init.sql`
  - [ ] No output = success ✅

  **Method C: MySQL Workbench**
  - [ ] Open MySQL Workbench
  - [ ] Connect to local MySQL
  - [ ] File → Open SQL Script
  - [ ] Select `init.sql`
  - [ ] Click Execute (Ctrl+Shift+Enter)
  - [ ] See green checkmarks ✅

- [ ] Verify database was created:
  - [ ] Go to: `http://localhost/phpmyadmin`
  - [ ] Left sidebar → click `tagpo_db`
  - [ ] Should see these tables:
    - [ ] users
    - [ ] venues
    - [ ] activities
    - [ ] bookings
    - [ ] booking_activities
    - [ ] wishlist
    - [ ] reviews
    - [ ] audit_logs

- [ ] Verify sample data:
  - [ ] Click `users` table
  - [ ] Should see 1 row: admin@tagpo.com
  - [ ] Click `venues` table
  - [ ] Should see 3 rows: Paradiso, Blue Gardens, Green Lounge

---

## 🎯 Phase 3: Update Main Pages (10 minutes)

Add `$baseUrl = getBaseUrl();` to these files:

**File 1: index.php**
- [ ] Open: `Tagpo/index.php`
- [ ] Find line 2: `require_once 'config/session_config.php';`
- [ ] Add new line after it:
  ```php
  $baseUrl = getBaseUrl();
  ```
- [ ] Save

**File 2: search.php**
- [ ] Open: `Tagpo/search.php`
- [ ] Find first `<?php` block
- [ ] Add: `$baseUrl = getBaseUrl();` after the require_once
- [ ] Save

**File 3: venue.php**
- [ ] Open: `Tagpo/venue.php`
- [ ] Find first `<?php` block
- [ ] Add: `$baseUrl = getBaseUrl();`
- [ ] Save

**File 4: booking.php**
- [ ] Open: `Tagpo/booking.php`
- [ ] Find first `<?php` block
- [ ] Add: `$baseUrl = getBaseUrl();`
- [ ] Save

**File 5: cart.php**
- [ ] Open: `Tagpo/cart.php`
- [ ] Find first `<?php` block
- [ ] Add: `$baseUrl = getBaseUrl();`
- [ ] Save

**File 6: checkout.php**
- [ ] Open: `Tagpo/checkout.php`
- [ ] Find first `<?php` block
- [ ] Add: `$baseUrl = getBaseUrl();`
- [ ] Save

**File 7: receipt.php**
- [ ] Open: `Tagpo/receipt.php`
- [ ] Find first `<?php` block
- [ ] Add: `$baseUrl = getBaseUrl();`
- [ ] Save

**File 8: wishlist.php**
- [ ] Open: `Tagpo/wishlist.php`
- [ ] Find first `<?php` block
- [ ] Add: `$baseUrl = getBaseUrl();`
- [ ] Save

---

## 🎯 Phase 4: Comprehensive Testing (5 minutes)

### Navigation Links
- [ ] Home → Works
- [ ] Explore Venues → Works
- [ ] About Us → Works
- [ ] Wishlist → Works
- [ ] Cart → Works

### Admin Functions
- [ ] Admin Dropdown visible (when logged in as admin)
- [ ] Admin Dashboard accessible
- [ ] Add Venue page accessible
- [ ] View Bookings page accessible

### Login/Logout
- [ ] Login with admin@tagpo.com / admin123 → Works
- [ ] Navbar shows "Hi, Admin User 👋"
- [ ] Logout button works
- [ ] Can login again

### Database Verification
- [ ] Go to `http://localhost/phpmyadmin`
- [ ] Select `tagpo_db`
- [ ] All tables visible:
  - [ ] users (1 row)
  - [ ] venues (3 rows)
  - [ ] activities (3 rows per venue)

### Session/Cookies
- [ ] Cookie set after login
- [ ] Cookie persists across pages
- [ ] Logout clears cookie
- [ ] Session expires after 10 minutes of inactivity

---

## 🚨 Troubleshooting

### Issue: Login still goes to /auth/index.php
**Checklist:**
- [ ] Did you save the new session_config.php?
- [ ] Is it in the correct location? (`Tagpo/config/session_config.php`)
- [ ] Did you clear browser cache? (Ctrl+Shift+Delete)
- [ ] Did you restart XAMPP?
- [ ] Compare your file with session_config_FIXED.php - are they identical?

### Issue: Database connection errors
**Checklist:**
- [ ] Is MySQL running? (Check XAMPP Control Panel)
- [ ] Did you run init.sql? (Check phpMyAdmin for tagpo_db)
- [ ] Is config/database.php in correct location?
- [ ] Check database credentials in .env file:
  - DB_HOST=localhost
  - DB_USER=root
  - DB_PASS= (empty)
  - DB_NAME=tagpo_db

### Issue: admin@tagpo.com login fails
**Checklist:**
- [ ] Did you run init.sql? (Should create this user)
- [ ] Check phpMyAdmin: tagpo_db → users table
- [ ] Should see 1 row with email: admin@tagpo.com
- [ ] If not there, run init.sql again

### Issue: Navbar links are broken
**Checklist:**
- [ ] Did you add `$baseUrl = getBaseUrl();` to all pages?
- [ ] Check console (F12 → Console tab) for errors
- [ ] Verify paths in header.php use `<?php echo $baseUrl; ?>`

---

## 📋 Final Checklist

### Core Functionality
- [ ] Login works → redirects correctly
- [ ] Navbar shows login/logout correctly
- [ ] All navigation links work
- [ ] Admin can access admin panel
- [ ] Session timeout works after 10 minutes

### Database
- [ ] Database created (`tagpo_db`)
- [ ] All 8 tables created
- [ ] Sample data imported
- [ ] Admin user exists
- [ ] 3 sample venues exist
- [ ] Activities created for each venue

### File Structure
- [ ] `config/session_config.php` → Updated
- [ ] `config/database.php` → Created
- [ ] `.env` → Created
- [ ] All main pages have `$baseUrl = getBaseUrl();`

### Browser Testing
- [ ] Chrome: All pages load correctly
- [ ] Firefox: All pages load correctly
- [ ] Mobile view: Links still work
- [ ] Cache cleared: Pages refresh correctly

---

## ✅ Completion Status

Once you've completed all checkboxes:

- ✅ Login bug is FIXED
- ✅ Database is SET UP
- ✅ Navigation is IMPROVED
- ✅ Project is READY for next phase

---

## 📊 Time Estimate

| Phase | Task | Time |
|-------|------|------|
| 1 | Fix login redirect | 5 min |
| 2 | Setup database | 10 min |
| 3 | Update pages | 10 min |
| 4 | Testing | 5 min |
| **TOTAL** | | **30 min** |

---

## 📞 Next Steps (When Ready)

1. Integrate database into login system
2. Move bookings from session to database
3. Add password hashing (password_hash)
4. Add input validation on all forms
5. Set up payment processing
6. Add email notifications

---

**Ready to start?** Begin with Phase 1 ↑
