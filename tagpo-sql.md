# TAGPO_DB — Comprehensive SQL DDL Analysis
**Senior Database Administrator & Systems Analyst Report**

---

## TABLE OF CONTENTS
1. [Line-by-Line SQL Explanation](#1-line-by-line-sql-explanation)
2. [Database Schema & ERD](#2-database-schema--erd)
3. [Database Normalization Journey](#3-database-normalization-journey)
4. [Data Dictionary](#4-data-dictionary)

---

## 1. Line-by-Line SQL Explanation

### Database Setup

```sql
DROP DATABASE IF EXISTS tagpo_db;
```
Drops the database named `tagpo_db` only if it already exists. The `IF EXISTS` guard prevents an error from being thrown if the database doesn't yet exist. This is standard at the top of seed/reset scripts so they can be re-run safely.

```sql
CREATE DATABASE tagpo_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```
Creates a fresh database. `utf8mb4` is the full Unicode character set — it supports all Unicode characters including emojis (unlike the older `utf8` in MySQL which only handled 3-byte characters). `utf8mb4_unicode_ci` sets the collation to case-insensitive, Unicode-aware comparison, ensuring correct alphabetical sorting and string comparison across international characters.

```sql
USE tagpo_db;
```
Switches the active database context to `tagpo_db`, so all subsequent DDL and DML statements target this database.

---

### Table: `users`

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
```
`INT AUTO_INCREMENT PRIMARY KEY` — the `id` column is the table's Primary Key. `AUTO_INCREMENT` means MySQL automatically assigns the next sequential integer when a new row is inserted without specifying this value. This ensures every user has a unique, system-managed identifier.

```sql
    first_name VARCHAR(50) NOT NULL,
    last_name  VARCHAR(50) NOT NULL,
```
Variable-length strings up to 50 characters. `NOT NULL` enforces that these fields must always have a value — a user cannot be created without a first and last name.

```sql
    email VARCHAR(100) NOT NULL UNIQUE,
```
`UNIQUE` creates a unique index on the `email` column. MySQL will reject any `INSERT` or `UPDATE` that would result in two rows sharing the same email address. Combined with `NOT NULL`, every user must have a distinct, non-empty email.

```sql
    phone VARCHAR(20),
```
Phone is nullable (no `NOT NULL`) — it is optional. `VARCHAR(20)` stores it as a string to preserve leading zeros and handle various formats (e.g., `+63-912-345-6789`).

```sql
    password VARCHAR(255) NOT NULL,
```
Stores a bcrypt-hashed password. The length 255 comfortably fits bcrypt hash output (typically 60 characters) with room for other hashing algorithms. Passwords are **never** stored in plain text — confirmed by the seed data which uses `$2y$10$...` (bcrypt format).

```sql
    role ENUM('user','admin') DEFAULT 'user'
);
```
`ENUM` restricts the column to only the listed values. MySQL stores ENUMs as 1–2 byte integers internally, mapping to the string values. `DEFAULT 'user'` means any new row that doesn't specify a role gets `'user'` automatically.

---

### Table: `event`

```sql
CREATE TABLE event (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(100) NOT NULL UNIQUE
);
```
A lookup/reference table for event types (Wedding, Birthday, etc.). The `UNIQUE` constraint on `event_name` prevents duplicate event type names from being entered. This table is intentionally simple — it exists to normalize event types out of other tables.

---

### Table: `venues`

```sql
CREATE TABLE venues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    capacity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
```
`DECIMAL(10,2)` stores an exact fixed-point number with up to 10 total digits, 2 of which are after the decimal point. This is **critical for monetary values** — `FLOAT` or `DOUBLE` introduce floating-point rounding errors (e.g., `₱35000.005` instead of `₱35000.00`), which is unacceptable in financial contexts.

```sql
    description TEXT,
    image_url VARCHAR(255)
);
```
Both `description` and `image_url` are nullable. `TEXT` can store up to 65,535 bytes, suitable for long venue descriptions. `VARCHAR(255)` holds file paths or URLs for the primary display image.

---

### Table: `venue_events` (Junction Table)

```sql
CREATE TABLE venue_events (
    venue_id INT NOT NULL,
    event_id INT NOT NULL,
    PRIMARY KEY (venue_id, event_id),
```
This is a **junction table** (also called a bridge or associative table) that resolves the many-to-many relationship between `venues` and `event`. A venue can host many event types, and an event type can occur at many venues. The **composite Primary Key** `(venue_id, event_id)` guarantees that the same venue-event pair cannot be inserted twice.

```sql
    FOREIGN KEY (venue_id)
        REFERENCES venues(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
```
**How REFERENCES works:** When MySQL sees a value in `venue_id`, it enforces that the value must exist as an `id` in the `venues` table. This is **referential integrity** — you cannot add a row to `venue_events` referencing a venue that doesn't exist.

**ON DELETE CASCADE (venue → venue_events):** If a venue row is deleted from `venues`, MySQL automatically deletes all `venue_events` rows that reference that venue. This propagates the deletion "downward" through the relationship tree. You don't need to manually clean up the junction table.

**ON UPDATE CASCADE (venue → venue_events):** If the `id` of a venue changes in `venues`, MySQL automatically updates the `venue_id` in all related `venue_events` rows. In practice, Primary Keys rarely change, but this maintains consistency if they do.

```sql
    FOREIGN KEY (event_id)
        REFERENCES event(event_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
```
Same cascade logic for the `event` side — deleting an event type removes all venue_event mappings for it.

---

### Table: `venue_gallery`

```sql
CREATE TABLE venue_gallery (
    gallery_id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id   INT NOT NULL,
    image_url  VARCHAR(255) NOT NULL,
    label      VARCHAR(100) DEFAULT NULL,
    sort_order TINYINT UNSIGNED DEFAULT 0,
```
`TINYINT UNSIGNED` stores values 0–255, which is more than sufficient for sorting a gallery of images. `UNSIGNED` doubles the positive range since negative sort orders make no sense. `DEFAULT 0` means images without an explicit order go to the front/unsorted position.

```sql
    FOREIGN KEY (venue_id)
        REFERENCES venues(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
```
**Parent-child relationship:** `venues` is the **parent table**, `venue_gallery` is the **child table**. The FK enforces that every gallery image must belong to an existing venue. `ON DELETE CASCADE` means deleting a venue automatically deletes all its gallery images — no orphaned image records.

---

### Table: `venue_highlights`

```sql
CREATE TABLE venue_highlights (
    highlight_id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id     INT NOT NULL,
    highlight    VARCHAR(255) NOT NULL,
    sort_order   TINYINT UNSIGNED DEFAULT 0,
    FOREIGN KEY (venue_id) REFERENCES venues(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);
```
Stores marketing bullet points for each venue (e.g., "Free Parking", "24/7 Security"). Separated from `venues` because a venue can have a variable number of highlights — storing them as a repeating group in the parent table would violate 1NF.

---

### Table: `amenities`

```sql
CREATE TABLE amenities (
    amenity_id INT AUTO_INCREMENT PRIMARY KEY,
    venue_id INT NOT NULL,
    amenity_name VARCHAR(100) NOT NULL,
    FOREIGN KEY (venue_id) REFERENCES venues(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);
```
Same structural pattern as `venue_highlights`. Amenities are physical features of a venue (Sound System, Wi-Fi, Wheelchair Access). Storing them separately allows flexible querying (e.g., "find all venues with AC") without requiring fixed columns in `venues`.

---

### Table: `addons`

```sql
CREATE TABLE addons (
    addon_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    addon_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (event_id) REFERENCES event(event_id)
        ON DELETE CASCADE ON UPDATE CASCADE
);
```
Add-ons are **event-specific extras** a user can add to their booking (e.g., a Bridal Car is only relevant for Weddings). Linking to `event` rather than `venues` means the same add-on concept is available across all venues that support that event type. `ON DELETE CASCADE` means if an event type is removed, all its add-ons are also removed.

---

### Table: `carts`

```sql
CREATE TABLE carts (
    cart_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status ENUM('active','checked_out','cancelled') DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
);
```
Implements a **shopping cart pattern**. A user can have one active cart at a time, which collects bookings before payment. The status lifecycle is: `active` → `checked_out` (after payment) or `cancelled`. `ON DELETE CASCADE` from `users` means if a user account is deleted, their cart is also cleaned up.

---

### Table: `bookings`

```sql
CREATE TABLE bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    user_id INT NOT NULL,
    venue_id INT NOT NULL,
    event_id INT NOT NULL,
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    duration INT NOT NULL,
    guest_count INT NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    status ENUM('pending','confirmed','cancelled') DEFAULT 'pending',
```
The central transactional table. `DATE` stores only the date (YYYY-MM-DD); `TIME` stores the start time (HH:MM:SS). `duration` is presumably in hours. Note that `user_id` is stored here **in addition to** being accessible via `cart_id` — this is a **deliberate denormalization** for query performance (avoids an extra JOIN when fetching a user's bookings).

```sql
    FOREIGN KEY (cart_id)  REFERENCES carts(cart_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)      ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (venue_id) REFERENCES venues(id)     ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (event_id) REFERENCES event(event_id)ON DELETE CASCADE ON UPDATE CASCADE
);
```
Four foreign keys anchor this table to every entity it references. All four use `CASCADE`, ensuring that if any parent record is deleted, associated bookings are cleaned up. In a production system, you might prefer `ON DELETE RESTRICT` for bookings to prevent accidental data loss of financial records.

---

### Table: `booking_addons`

```sql
CREATE TABLE booking_addons (
    booking_id INT NOT NULL,
    addon_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (booking_id, addon_id),
```
Another junction table — this time linking bookings to their selected add-ons. The composite PK prevents the same add-on from being added twice to the same booking. `unit_price` is stored here (not just referencing `addons.price`) to **freeze the price at time of booking** — critical for financial integrity, as add-on prices may change in the future.

```sql
    FOREIGN KEY (addon_id) REFERENCES addons(addon_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
);
```
**ON DELETE RESTRICT** — this is different from the others. If someone tries to delete an add-on from the `addons` table while it still has rows in `booking_addons`, MySQL **refuses the operation** and throws an error. This protects historical booking records — you cannot delete a product that's part of a past transaction.

---

### Table: `payments`

```sql
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    cart_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('credit_card','debit_card','gcash') NOT NULL,
    payment_status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
    transaction_id VARCHAR(100) UNIQUE,
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_id) REFERENCES carts(cart_id)
        ON DELETE CASCADE ON UPDATE CASCADE
);
```
`DATETIME DEFAULT CURRENT_TIMESTAMP` automatically records the exact date and time a payment record is created. `transaction_id UNIQUE` stores the external payment gateway's transaction reference — the `UNIQUE` constraint ensures no duplicate transactions are recorded.

---

### Tables: `card_payments` & `gcash_payments` (Subtype Pattern)

```sql
CREATE TABLE card_payments (
    payment_id INT PRIMARY KEY,
    card_holder_name VARCHAR(100) NOT NULL,
    card_last_four CHAR(4) NOT NULL,
    card_expiry_month TINYINT NOT NULL,
    card_expiry_year SMALLINT NOT NULL,
    FOREIGN KEY (payment_id) REFERENCES payments(payment_id)
        ON DELETE CASCADE ON UPDATE CASCADE
);
```
```sql
CREATE TABLE gcash_payments (
    payment_id INT PRIMARY KEY,
    gcash_phone_number VARCHAR(11) NOT NULL,
    gcash_account_name VARCHAR(100) NOT NULL,
    FOREIGN KEY (payment_id) REFERENCES payments(payment_id)
        ON DELETE CASCADE ON UPDATE CASCADE
);
```
These implement a **table-per-subtype inheritance pattern** (also called Class Table Inheritance). The `payments` table is the supertype with common fields; `card_payments` and `gcash_payments` are subtypes with method-specific fields. `payment_id` is both the PK of these tables **and** a FK back to `payments` — it's a 1:1 relationship (one payment → zero or one card detail record). `CHAR(4)` for `card_last_four` uses fixed-length storage since card last-four digits are always exactly 4 characters.

---

### Table: `reviews`

```sql
CREATE TABLE reviews (
    review_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    venue_id INT NOT NULL,
    rating TINYINT NOT NULL,
    review_text TEXT,
    review_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users(id)   ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (venue_id) REFERENCES venues(id)  ON DELETE CASCADE ON UPDATE CASCADE
);
```
`TINYINT` for `rating` is appropriate since ratings are typically 1–5 (or 1–10), values that fit within a 1-byte integer. Note there is no `CHECK (rating BETWEEN 1 AND 5)` constraint — this validation is delegated to the application layer. `review_text` is nullable — a user might submit a star rating without a written review.

---

### Table: `password_resets`

```sql
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (email)
);
```
`INDEX (email)` creates a **non-unique index** on `email`. This dramatically speeds up lookups like `WHERE email = 'user@example.com'`, which is the primary query pattern for OTP verification. Unlike `UNIQUE`, this allows multiple OTP records per email (e.g., if a user requests a reset multiple times). `expires_at` enables application-level expiry checking. `TIMESTAMP` vs `DATETIME`: `TIMESTAMP` is stored in UTC and converts to the server's timezone on retrieval — appropriate for created_at audit fields.

---

## 2. Database Schema & ERD

### Textual Schema Map

```
tagpo_db
│
├── users (PK: id)
│   ├── has many → carts
│   ├── has many → bookings
│   └── has many → reviews
│
├── event (PK: event_id)
│   ├── has many ↔ venues [via venue_events]
│   └── has many → addons
│
├── venues (PK: id)
│   ├── has many ↔ events [via venue_events]
│   ├── has many → venue_gallery
│   ├── has many → venue_highlights
│   ├── has many → amenities
│   ├── has many → bookings
│   └── has many → reviews
│
├── venue_events (PK: venue_id + event_id) ← M:N junction
│
├── addons (PK: addon_id)
│   └── has many ↔ bookings [via booking_addons]
│
├── carts (PK: cart_id)
│   ├── has many → bookings
│   └── has one  → payments
│
├── bookings (PK: booking_id)
│   └── has many ↔ addons [via booking_addons]
│
├── booking_addons (PK: booking_id + addon_id) ← M:N junction
│
├── payments (PK: payment_id)
│   ├── has one  → card_payments
│   └── has one  → gcash_payments
│
├── card_payments   (PK/FK: payment_id) ← 1:1 subtype
├── gcash_payments  (PK/FK: payment_id) ← 1:1 subtype
│
├── venue_gallery   (PK: gallery_id)
├── venue_highlights (PK: highlight_id)
├── amenities       (PK: amenity_id)
├── reviews         (PK: review_id)
└── password_resets (PK: id)
```

### Relationships Summary

| Relationship | Type | Description |
|---|---|---|
| users → carts | 1:M | One user can have many carts |
| users → bookings | 1:M | One user can make many bookings |
| users → reviews | 1:M | One user can write many reviews |
| venues ↔ event | M:N | Via `venue_events` junction table |
| venues → venue_gallery | 1:M | One venue has many gallery images |
| venues → venue_highlights | 1:M | One venue has many highlights |
| venues → amenities | 1:M | One venue has many amenities |
| venues → reviews | 1:M | One venue can have many reviews |
| event → addons | 1:M | One event type has many add-ons |
| carts → bookings | 1:M | One cart can have many bookings |
| carts → payments | 1:1 | One cart has at most one payment |
| bookings ↔ addons | M:N | Via `booking_addons` junction table |
| payments → card_payments | 1:0..1 | Optional subtype (1:1 if card method) |
| payments → gcash_payments | 1:0..1 | Optional subtype (1:1 if GCash method) |

---

## 3. Database Normalization Journey

### Starting Point: Conceptual Flat File (UNF)

Imagine all booking data in one spreadsheet row per booking:

```
UserID | UserEmail | UserName | Phone | VenueName | VenueLocation | VenueCapacity |
VenuePrice | VenueAmenity1 | VenueAmenity2 | VenueAmenity3 | EventType |
AddonName1 | AddonPrice1 | AddonName2 | AddonPrice2 | BookingDate |
BookingTime | GuestCount | TotalPrice | PaymentMethod | CardNumber |
Rating | ReviewText
```

**Problems with UNF:**
- Repeating groups: `VenueAmenity1`, `VenueAmenity2` — what if a venue has 10 amenities?
- Repeating groups: `AddonName1`, `AddonPrice1`, `AddonName2` — unbounded columns
- Massive data redundancy: same venue info repeated on every booking for that venue
- No way to query "all venues with AC" without checking multiple columns

---

### First Normal Form (1NF)

**Rule:** Eliminate repeating groups; each cell holds exactly one atomic value.

**Changes made:**
- Remove multi-valued columns. Instead of `VenueAmenity1, VenueAmenity2`, create one row per amenity.
- Each row has a unique identifier.

After 1NF, the flat table becomes multiple tables but they still have issues. A single "mega-booking" table might look like:

```
BookingID | UserEmail | UserName | VenueName | VenueLocation | EventName |
AddonName | AddonPrice | BookingDate | TotalPrice
```

With one row per add-on per booking. This satisfies 1NF (atomic values, no repeating groups) but creates new redundancies.

---

### Second Normal Form (2NF)

**Rule:** Must be in 1NF AND every non-key attribute must be **fully functionally dependent** on the **entire** Primary Key (eliminates partial dependencies — relevant when PK is composite).

**Problems found in 1NF tables:**
- In a hypothetical `booking_details(booking_id, addon_id, addon_name, addon_price, venue_name, ...)`:
  - `addon_name` depends only on `addon_id`, not on `booking_id` → partial dependency
  - `venue_name` depends only on `booking_id`, not on `addon_id` → partial dependency

**Changes made:**
- Move `addon_name` and `addon_price` to a separate `addons` table (keyed by `addon_id`)
- Move venue information to a `venues` table (keyed by `venue_id`)
- Move user information to a `users` table (keyed by `user_id`)
- The junction table `booking_addons(booking_id, addon_id, quantity, unit_price)` now has all attributes dependent on the full composite key

After 2NF, we have separate `users`, `venues`, `addons`, `bookings`, and `booking_addons` tables. But transitive dependencies remain.

---

### Third Normal Form (3NF)

**Rule:** Must be in 2NF AND no non-key attribute should depend on another non-key attribute (eliminates transitive dependencies).

**Problems found in 2NF tables:**
- If `venues` contained an `event_id` column: `event_name` would depend on `event_id`, not on `venue_id` → transitive dependency
- If `bookings` contained `venue_location`: it depends on `venue_id` (which is a FK, not the PK) → transitive dependency
- Amenities stored as a JSON column in `venues`: not atomic, not accessible via SQL

**Changes made to reach 3NF:**
- `event` becomes its own lookup table; `venue_events` resolves the M:N relationship
- All venue metadata (gallery, highlights, amenities) extracted to child tables
- `addons` are linked to `event`, not embedded in `venues` or `bookings`
- Payment subtypes (`card_payments`, `gcash_payments`) extracted from `payments` — avoiding columns like `card_number` that are NULL for non-card payments (which would be a transitive dependency via `payment_method`)

**How the current schema satisfies 3NF:**
- Every non-key column depends on **the key** (identity), **the whole key** (no partial deps), and **nothing but the key** (no transitive deps)
- Example: In `bookings`, `total_price` depends on `booking_id` — not on `user_id` or `venue_id` independently
- Example: In `addons`, `price` depends on `addon_id` — not on `event_id` alone
- Example: In `booking_addons`, `unit_price` depends on `(booking_id, addon_id)` — the full composite key

> **Note on intentional denormalization:** The `user_id` column in `bookings` is technically redundant (accessible via `cart_id → carts → user_id`). This is a **deliberate, justified** denormalization for query performance — commonly acceptable in 3NF designs as long as it is documented and managed via application logic.

---

## 4. Data Dictionary

### Table: `users`

| Column | Data Type | Constraints | Description |
|---|---|---|---|
| id | INT | PK, AUTO_INCREMENT, NOT NULL | Unique user identifier, auto-generated |
| first_name | VARCHAR(50) | NOT NULL | User's given name |
| last_name | VARCHAR(50) | NOT NULL | User's family name |
| email | VARCHAR(100) | NOT NULL, UNIQUE | Login credential and contact email; must be unique |
| phone | VARCHAR(20) | nullable | Optional contact phone number |
| password | VARCHAR(255) | NOT NULL | Bcrypt-hashed password; never plain text |
| role | ENUM | DEFAULT 'user' | Access level: 'user' (customer) or 'admin' (staff) |

---

### Table: `event`

| Column | Data Type | Constraints | Description |
|---|---|---|---|
| event_id | INT | PK, AUTO_INCREMENT, NOT NULL | Unique event type identifier |
| event_name | VARCHAR(100) | NOT NULL, UNIQUE | Name of the event type (e.g., 'Wedding', 'Prom / Ball') |

---

### Table: `venues`

| Column | Data Type | Constraints | Description |
|---|---|---|---|
| id | INT | PK, AUTO_INCREMENT, NOT NULL | Unique venue identifier |
| name | VARCHAR(100) | NOT NULL | Display name of the venue |
| location | VARCHAR(255) | NOT NULL | City/address of the venue |
| capacity | INT | NOT NULL | Maximum guest count the venue can hold |
| price | DECIMAL(10,2) | NOT NULL | Base rental price in Philippine Peso |
| description | TEXT | nullable | Long-form marketing description |
| image_url | VARCHAR(255) | nullable | Path or URL to the venue's primary display image |

---

### Table: `venue_events`

| Column | Data Type | Constraints | Description |
|---|---|---|---|
| venue_id | INT | PK (composite), FK → venues.id, NOT NULL | References the venue |
| event_id | INT | PK (composite), FK → event.event_id, NOT NULL | References the event type supported at this venue |

---

### Table: `venue_gallery`

| Column | Data Type | Constraints | Description |
|---|---|---|---|
| gallery_id | INT | PK, AUTO_INCREMENT, NOT NULL | Unique gallery image identifier |
| venue_id | INT | FK → venues.id, NOT NULL | The venue this image belongs to |
| image_url | VARCHAR(255) | NOT NULL | Path or URL to the gallery image |
| label | VARCHAR(100) | nullable, DEFAULT NULL | Human-readable caption (e.g., 'Main Hall') |
| sort_order | TINYINT UNSIGNED | DEFAULT 0 | Controls display order; lower values appear first |

---

### Table: `venue_highlights`

| Column | Data Type | Constraints | Description |
|---|---|---|---|
| highlight_id | INT | PK, AUTO_INCREMENT, NOT NULL | Unique highlight identifier |
| venue_id | INT | FK → venues.id, NOT NULL | The venue this highlight belongs to |
| highlight | VARCHAR(255) | NOT NULL | A single marketing bullet point (e.g., 'Free Parking') |
| sort_order | TINYINT UNSIGNED | DEFAULT 0 | Controls display order; lower values appear first |

---

### Table: `amenities`

| Column | Data Type | Constraints | Description |
|---|---|---|---|
| amenity_id | INT | PK, AUTO_INCREMENT, NOT NULL | Unique amenity identifier |
| venue_id | INT | FK → venues.id, NOT NULL | The venue this amenity belongs to |
| amenity_name | VARCHAR(100) | NOT NULL | Name of the physical feature (e.g., 'Sound System', 'Wi-Fi') |

---

### Table: `addons`

| Column | Data Type | Constraints | Description |
|---|---|---|---|
| addon_id | INT | PK, AUTO_INCREMENT, NOT NULL | Unique add-on identifier |
| event_id | INT | FK → event.event_id, NOT NULL | The event type this add-on is applicable to |
| addon_name | VARCHAR(100) | NOT NULL | Display name of the add-on (e.g., 'Photo Booth') |
| price | DECIMAL(10,2) | NOT NULL | Current listed price of the add-on in PHP |

---

### Table: `carts`

| Column | Data Type | Constraints | Description |
|---|---|---|---|
| cart_id | INT | PK, AUTO_INCREMENT, NOT NULL | Unique cart identifier |
| user_id | INT | FK → users.id, NOT NULL | The user who owns this cart |
| status | ENUM | DEFAULT 'active' | Cart lifecycle: 'active', 'checked_out', or 'cancelled' |

---

### Table: `bookings`

| Column | Data Type | Constraints | Description |
|---|---|---|---|
| booking_id | INT | PK, AUTO_INCREMENT, NOT NULL | Unique booking identifier |
| cart_id | INT | FK → carts.cart_id, NOT NULL | The cart this booking belongs to |
| user_id | INT | FK → users.id, NOT NULL | Denormalized user reference for query performance |
| venue_id | INT | FK → venues.id, NOT NULL | The venue being booked |
| event_id | INT | FK → event.event_id, NOT NULL | The event type for this booking |
| event_date | DATE | NOT NULL | The scheduled date of the event (YYYY-MM-DD) |
| event_time | TIME | NOT NULL | The scheduled start time of the event (HH:MM:SS) |
| duration | INT | NOT NULL | Duration of the event (presumably in hours) |
| guest_count | INT | NOT NULL | Number of guests expected to attend |
| total_price | DECIMAL(10,2) | NOT NULL | Final computed price including all add-ons |
| status | ENUM | DEFAULT 'pending' | Booking lifecycle: 'pending', 'confirmed', or 'cancelled' |

---

### Table: `booking_addons`

| Column | Data Type | Constraints | Description |
|---|---|---|---|
| booking_id | INT | PK (composite), FK → bookings.booking_id, NOT NULL | References the booking |
| addon_id | INT | PK (composite), FK → addons.addon_id ON DELETE RESTRICT, NOT NULL | References the add-on; deletion restricted to protect history |
| quantity | INT | NOT NULL, DEFAULT 1 | How many units of this add-on were selected |
| unit_price | DECIMAL(10,2) | NOT NULL | Price frozen at time of booking; independent of current addon.price |

---

### Table: `payments`

| Column | Data Type | Constraints | Description |
|---|---|---|---|
| payment_id | INT | PK, AUTO_INCREMENT, NOT NULL | Unique payment identifier |
| cart_id | INT | FK → carts.cart_id, NOT NULL | The cart (and all its bookings) this payment covers |
| amount | DECIMAL(10,2) | NOT NULL | Total amount paid in PHP |
| payment_method | ENUM | NOT NULL | Payment channel: 'credit_card', 'debit_card', or 'gcash' |
| payment_status | ENUM | DEFAULT 'pending' | Payment lifecycle: 'pending', 'paid', 'failed', or 'refunded' |
| transaction_id | VARCHAR(100) | UNIQUE, nullable | External payment gateway transaction reference number |
| payment_date | DATETIME | DEFAULT CURRENT_TIMESTAMP | Timestamp of when the payment record was created |

---

### Table: `card_payments`

| Column | Data Type | Constraints | Description |
|---|---|---|---|
| payment_id | INT | PK, FK → payments.payment_id, NOT NULL | Shared PK with payments; creates 1:1 subtype relationship |
| card_holder_name | VARCHAR(100) | NOT NULL | Name as printed on the card |
| card_last_four | CHAR(4) | NOT NULL | Last 4 digits of the card number (for display/verification) |
| card_expiry_month | TINYINT | NOT NULL | Card expiration month (1–12) |
| card_expiry_year | SMALLINT | NOT NULL | Card expiration year (e.g., 2027) |

---

### Table: `gcash_payments`

| Column | Data Type | Constraints | Description |
|---|---|---|---|
| payment_id | INT | PK, FK → payments.payment_id, NOT NULL | Shared PK with payments; creates 1:1 subtype relationship |
| gcash_phone_number | VARCHAR(11) | NOT NULL | GCash-registered Philippine mobile number (11 digits) |
| gcash_account_name | VARCHAR(100) | NOT NULL | Full name of the GCash account holder |

---

### Table: `reviews`

| Column | Data Type | Constraints | Description |
|---|---|---|---|
| review_id | INT | PK, AUTO_INCREMENT, NOT NULL | Unique review identifier |
| user_id | INT | FK → users.id, NOT NULL | The user who wrote this review |
| venue_id | INT | FK → venues.id, NOT NULL | The venue being reviewed |
| rating | TINYINT | NOT NULL | Numeric rating (application should enforce range, e.g., 1–5) |
| review_text | TEXT | nullable | Optional written review body |
| review_date | DATETIME | DEFAULT CURRENT_TIMESTAMP | Timestamp of when the review was submitted |

---

### Table: `password_resets`

| Column | Data Type | Constraints | Description |
|---|---|---|---|
| id | INT | PK, AUTO_INCREMENT, NOT NULL | Unique reset request identifier |
| email | VARCHAR(255) | NOT NULL, INDEX | Email address the OTP was sent to; indexed for fast lookup |
| otp_code | VARCHAR(6) | NOT NULL | 6-digit One-Time Password sent to the user |
| expires_at | DATETIME | NOT NULL | Expiry datetime after which the OTP is invalid |
| created_at | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | When this reset request was created; stored in UTC |

---

*End of Report — TAGPO_DB Analysis*
*Generated by: Senior DBA & Systems Analyst Review*