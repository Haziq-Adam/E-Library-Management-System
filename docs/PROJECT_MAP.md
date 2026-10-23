# File guide

This is a quick map of the files in the project. I kept the main PHP pages in the root directory because the application uses relative links between them.

## Main pages

| File | Purpose |
| --- | --- |
| [`index.php`](../index.php) | Sends visitors to the sign-in page |
| [`login.php`](../login.php) | Sign in |
| [`register.php`](../register.php) | Register an account |
| [`logout.php`](../logout.php) | Sign out |
| [`dashboard.php`](../dashboard.php) | Main page after signing in |
| [`profile.php`](../profile.php) | Account details |
| [`header.php`](../header.php), [`footer.php`](../footer.php) | Shared page elements |

## Reservations

| File | Purpose |
| --- | --- |
| [`tempah_buku.php`](../tempah_buku.php) | Browse books and submit a reservation request |
| [`status_buku.php`](../status_buku.php) | Book reservation history and status |
| [`book_api.php`](../book_api.php) | Book-related server actions |
| [`booking.php`](../booking.php) | Book a library room |
| [`booking_slots.php`](../booking_slots.php) | Fetch available room slots |
| [`status_tempahan.php`](../status_tempahan.php) | Check room bookings |

## Administration

| File | Purpose |
| --- | --- |
| [`admin_dashboard.php`](../admin_dashboard.php) | Admin overview and booking management |
| [`admin_books.php`](../admin_books.php) | Book and reservation administration |
| [`admin_api.php`](../admin_api.php) | Admin actions for users and bookings |
| [`image_api.php`](../image_api.php) | Image upload and lookup endpoints |

## Supporting files

| File or folder | Purpose |
| --- | --- |
| [`db.php`](../db.php) | Reads the private database configuration and creates a MySQL connection |
| [`config.local.example.php`](../config.local.example.php) | Configuration template; never put real passwords here |
| [`database/schema.sql`](../database/schema.sql) | Empty schema for a new database |
| [`uploads/`](../uploads/) | Images shipped with the project; some user uploads may be added at runtime |
| [`style.css`](../style.css), [`animations.css`](../animations.css), [`slot_picker.css`](../slot_picker.css) | Application styling |
| [`app.js`](../app.js), [`animations.js`](../animations.js), [`slot_picker.js`](../slot_picker.js) | Browser-side scripts |

This is a file guide, not an audit of every function. See [Checks](CHECKS.md) and [Security](SECURITY.md) for limitations.
