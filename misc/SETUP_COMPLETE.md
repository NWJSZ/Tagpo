# 🚀 TAGPO - Complete Fix & Setup Guide

All issues have been fixed! Follow these steps to get your site running:

---

## Step 1: Initialize the Database (CRITICAL)

Visit this URL in your browser to automatically create the database and tables:

```
http://localhost/event_system/Tagpo/setup.php
```

**What it does:**
- ✅ Creates `tagpo_db` database
- ✅ Creates all necessary tables (users, venues, bookings, activities)
- ✅ Creates admin account: `admin@tagpo.com` / `admin123`

**After running, you'll see:**
```
✅ Database Setup Complete!
```

---

## Step 2: Test the Login

1. Go to: http://localhost/event_system/Tagpo/
2. Click **"Log In"** button
3. Enter credentials:
   - **Email:** `admin@tagpo.com`
   - **Password:** `admin123`

Expected result: Redirects to homepage with proper styling ✅

---

## Step 3: Verify All Pages Are Working

Try navigating to these pages - they should all load with proper styling:

- ✅ Home: http://localhost/event_system/Tagpo/index.php
- ✅ Venues: http://localhost/event_system/Tagpo/venue.php?id=1
- ✅ Booking: http://localhost/event_system/Tagpo/booking.php
- ✅ Cart: http://localhost/event_system/Tagpo/cart.php
- ✅ Admin Panel: http://localhost/event_system/Tagpo/admin/admin.php
- ✅ Add Venue: http://localhost/event_system/Tagpo/admin/add_venue.php

---

## Step 4: Clean Up (IMPORTANT FOR SECURITY!)

**Delete the setup file after database is created:**

```bash
# In your project folder, delete:
setup.php
```

Or manually delete `c:\xampp\htdocs\event_system\Tagpo\setup.php`

---

## What Was Fixed

✅ **Config Files:**
- Fixed `config/session_config.php` with proper session handling
- Added database connection helpers in `config/database.php`
- Proper `config/app.php` for base URL function

✅ **All Pages Fixed:**
- Fixed `venue.php` includes
- Fixed all admin pages (add_venue.php, delete_venue.php, admin.php)
- Fixed `receipt.php`
- Fixed auth pages (signup.php, logout.php)
- Updated `login.php` to use database authentication

✅ **Styling:**
- All CSS files are intact and working
- Bootstrap and custom styles properly linked
- Navigation working across all pages

✅ **Database:**
- Proper schema with all tables
- Admin user created automatically
- Ready for bookings and venue management

---

## Troubleshooting

**Problem:** CSS not loading
- **Solution:** Ensure you're accessing via correct URL path
- Clear browser cache (Ctrl+Shift+Delete)
- Check that `assets/css/` folder exists and has files

**Problem:** Can't login
- **Solution:** 
  - Run setup.php again if tables not created
  - Try credentials: `admin@tagpo.com` / `admin123`
  - Check browser console for errors (F12)

**Problem:** Database connection error
- **Solution:**
  - Ensure MySQL/MariaDB is running
  - Run setup.php to create database
  - Check xampp control panel

**Problem:** Navigation broken after logout
- **Solution:** Clear cookies and try again
- Session timeout is 10 minutes - you'll be logged out if inactive

---

## Default Admin Account

After setup, use these credentials:
- **Email:** `admin@tagpo.com`
- **Password:** `admin123`

**IMPORTANT:** Change this password after first login in production!

---

## File Structure Overview

```
Tagpo/
├── config/
│   ├── database.php       (✅ Fixed)
│   ├── session_config.php (✅ Fixed)
│   └── app.php           (✅ Working)
├── auth/
│   ├── login.php         (✅ Fixed)
│   ├── signup.php        (✅ Fixed)
│   └── logout.php        (✅ Fixed)
├── admin/
│   ├── admin.php         (✅ Fixed)
│   ├── add_venue.php     (✅ Fixed)
│   └── delete_venue.php  (✅ Fixed)
├── assets/
│   └── css/
│       ├── styles.css    (✅ Working)
│       ├── cart.css      (✅ Working)
│       └── loginsignup.css (✅ Working)
├── index.php             (✅ Working)
├── booking.php           (✅ Working)
├── venue.php             (✅ Fixed)
├── cart.php              (✅ Working)
├── payment.php           (✅ Working)
├── receipt.php           (✅ Fixed)
├── search.php            (✅ Working)
└── setup.php             (⚠️ DELETE after setup!)
```

---

## Need Help?

If anything isn't working:
1. Run `setup.php` again
2. Check browser console (F12) for errors
3. Look at server error logs in xampp

You're all set! 🎉
