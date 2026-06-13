# TAGPO – Fixes, Integration & Setup Guide

## 1. Updated Project File Structure

```
Tagpo/
├── assets/
│   ├── css/
│   │   ├── cart.css
│   │   ├── loginsignup.css
│   │   └── styles.css
│   ├── images/           ← venue images uploaded here
│   └── js/
│       ├── loginsignup.js
│       ├── payment.js
│       └── shortcuts.js
├── auth/
│   ├── login.php         ✅ FIXED
│   ├── logout.php
│   └── signup.php        (working – no changes needed)
├── admin/
│   ├── add_venue.php     ✅ FIXED
│   ├── admin.php
│   └── delete_venue.php
├── config/
│   ├── app.php
│   ├── database.php
│   ├── session.php
│   └── session_config.php
├── database/
│   ├── normalize.sql     ✅ FIXED (canonical schema)
│   └── database.sql      (legacy reference only)
├── includes/
│   ├── footer.php
│   └── header.php
├── add_to_cart.php       ✅ FIXED  ← now saves to DB
├── cart.php              (unchanged – reads $_SESSION['cart'])
├── checkout.php          (unchanged – redirect bridge)
├── index.php
├── payment.php           ✅ FIXED  ← saves payments/card_payments/gcash_payments to DB
├── receipt.php           (unchanged – reads $_SESSION['receipt_data'])
├── search.php
├── submit_review.php     ✅ FIXED  ← correct column names
├── venue.php
└── TAGPO_FIXES_MDD.md    ← this file
```

---

## 2. Fixes Made — Detailed

### `database/normalize.sql`

| Problem | Fix |
|---|---|
| `booking_addons` declared both `booking_addon_id INT AUTO_INCREMENT PRIMARY KEY` **and** a `PRIMARY KEY (booking_id, addon_id)` composite key — MySQL rejects duplicate primary keys | Removed the redundant `booking_addon_id` column. Composite PK is the correct design for a junction table. |
| Column name was `unit_price` in normalize.sql but `unit_price_at_booking` in database.sql — inconsistency across files | Standardised to `unit_price` throughout |
| Admin seed password was stored in plain text (`'admin123'`) in database.sql | normalize.sql correctly uses a bcrypt hash; seed kept as-is |

---

### `add_to_cart.php`

| Problem | Fix |
|---|---|
| Entire file only wrote to `$_SESSION['cart']` — nothing ever touched the database | Rewrote to perform full DB writes: `carts`, `bookings`, and `booking_addons` |
| `event_id` was stored as a display label ("Birthday / Debut") — no mapping to `event.event_id` FK | Added `$eventNameMap` to translate front-end labels to DB `event_name` values, then queries `event_id` |
| Add-on prices were hard-coded in 3 separate places (venue.php, cart.php, payment.php) — could drift out of sync | Now fetched directly from the `addons` DB table in a single prepared query |
| Duration stored as string ("4 hours") but `bookings.duration` is `INT` | Strips non-numeric characters with `FILTER_SANITIZE_NUMBER_INT` before insertion |
| No authentication guard — any visitor could POST | Added `isLoggedIn()` check; redirects to login if not authenticated |
| Session cart still populated for display | Session cart entry now also includes `booking_id` and `cart_id` so `payment.php` can retrieve them |

---

### `payment.php`

| Problem | Fix |
|---|---|
| Entire payment flow only wrote to `$_SESSION` — `payments`, `card_payments`, `gcash_payments` were never populated | Full DB transaction: inserts into `payments`, then into the appropriate child table (`card_payments` or `gcash_payments`) |
| PayPal was listed as a payment method but is not in the `payments.payment_method` ENUM (`credit_card`, `debit_card`, `gcash`) | Removed PayPal option to match the DB ENUM |
| `selected_items[]` indices (0, 1, 2…) were passed between cart→payment but were never carried back through the checkout form | Added hidden `<input>` fields for `selected_indices[]`, `booking_ids[]`, and all booking details so the second POST (pay_now) has full context |
| Booking status stayed `'pending'` after payment | After successful payment, `UPDATE bookings SET status = 'confirmed'` is called for all paid booking IDs |
| Cart status stayed `'active'` after payment | `UPDATE carts SET status = 'checked_out'` after successful payment |
| `$customerName` used `$user['name']` — column doesn't exist in normalize.sql | Changed to `$user['first_name'] . ' ' . $user['last_name']` |
| Add-on prices still hard-coded in 6+ `elseif` chains | Single DB lookup against `addons` table — no more hard-coded prices |
| Entire payment wrapped in no error handling | Wrapped in `$conn->begin_transaction()` / `commit()` / `rollback()` with `try/catch` |

---

### `submit_review.php`

| Problem | Fix |
|---|---|
| INSERT query used column name `id` instead of `user_id` | Changed to `user_id` to match `reviews` table definition |
| No check that the `venue_id` actually exists before inserting | Added a SELECT existence check against `venues` table |
| No redirect on failure — `echo`-d raw MySQL error to page | Added `error_log()` and user-friendly error message |

---

### `auth/login.php`

| Problem | Fix |
|---|---|
| Hardcoded admin fallback (`if ($email == $admin['email'] && $password == $admin['password'])`) bypassed `password_hash` entirely | Removed; admin logs in via DB like any other user (admin seed uses bcrypt in normalize.sql) |
| Fallback also checked `$_SESSION['users']` — a legacy array that no longer exists | Removed entirely |
| After DB login, redirect used extra leading `/` (`header("Location: " . getBaseUrl() . "/index.php")`) — could double-slash on some XAMPP setups | Changed to `$baseUrl . 'index.php'` (no leading slash on the path part) |
| Already-logged-in user not redirected away from login page | Added `isLoggedIn()` check at top; redirects to index |

---

### `admin/add_venue.php`

| Problem | Fix |
|---|---|
| Entire save logic only wrote to `$_SESSION['venues']` | Now `INSERT`s into the `venues` table and `amenities` table |
| No file-type validation on upload — any file extension accepted | Added MIME-type validation via `finfo` before moving the uploaded file |
| `id` was set to `time()` — not a real auto-increment DB ID | Reads `$conn->insert_id` after INSERT for the real venue ID |
| No user feedback on success or failure | Added `$errors[]` array and `$success` flag; form displays messages inline |
| Redirect happened before HTML was sent even on GET — could confuse browser | Redirect only happens inside `if ($_SERVER['REQUEST_METHOD'] === 'POST')` block |

---

## 3. Database Setup in XAMPP

### Step 1 — Start XAMPP

Open the XAMPP Control Panel and start:
- **Apache**
- **MySQL**

---

### Step 2 — Open phpMyAdmin

Navigate to: `http://localhost/phpmyadmin`

---

### Step 3 — Import the Schema

1. Click **"New"** in the left sidebar to confirm no stale `tagpo_db` exists  
   *(or use SQL tab and run `DROP DATABASE IF EXISTS tagpo_db;` first)*
2. Click the **"Import"** tab at the top
3. Click **"Choose File"** and select:  
   `Tagpo/database/normalize.sql`
4. Click **"Go"**

phpMyAdmin will create `tagpo_db` and all tables with seed data.

---

### Step 4 — Verify Tables

After import you should see these tables under `tagpo_db`:

- `users`
- `event`
- `venues`
- `amenities`
- `addons`
- `carts`
- `bookings`
- `booking_addons`
- `payments`
- `card_payments`
- `gcash_payments`
- `reviews`

---

### Step 5 — Place Project in htdocs

Copy the `Tagpo/` folder to:

```
C:\xampp\htdocs\Tagpo\
```

---

### Step 6 — Configure Database Connection

Open `Tagpo/config/database.php` and confirm:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');        // Change if you set a MySQL password
define('DB_NAME', 'tagpo_db');
define('DB_PORT', 3306);
```

---

### Step 7 — Access the Site

Open your browser and go to:

```
http://localhost/Tagpo/
```

**Default admin login:**

| Field | Value |
|---|---|
| Email | `admin@tagpo.com` |
| Password | `admin123` |

---

## 4. Full Booking Flow — What Now Saves to the DB

```
User visits venue.php
    └── Fills booking form → POST to add_to_cart.php
            ├── Upserts active cart   → carts table
            ├── Inserts booking       → bookings table
            └── Inserts each addon    → booking_addons table
                    ↓
            cart.php (reads $_SESSION['cart'] for display)
                    ↓
            payment.php (POST selected_items[])
                    ├── Inserts payment record     → payments table
                    ├── Inserts card/GCash detail  → card_payments / gcash_payments
                    ├── Updates bookings.status    → 'confirmed'
                    └── Updates carts.status       → 'checked_out'
                            ↓
                    receipt.php (reads $_SESSION['receipt_data'])

User submits review on venue.php
    └── submit_review.php  → reviews table
```
