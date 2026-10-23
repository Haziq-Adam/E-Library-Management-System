# Database structure

The repository includes `database/schema.sql` with six empty tables. It deliberately excludes real account details, passwords, bookings and historical records.

| Table | What it stores | Relationship |
| --- | --- | --- |
| `users` | Account and role data | Referenced by `bookings` and `book_reservations` |
| `books` | Catalogue entries and availability | Referenced by `book_reservations` |
| `bookings` | Room bookings and time slots | `user_id` → `users.id` |
| `book_reservations` | Requests and approval status | `user_id` → `users.id`; `book_id` → `books.id` |
| `room_images` | File paths for room images | No foreign key |
| `page_images` | Image paths used by pages | No foreign key |

## Import order

The table definitions are ordered as follows:

1. `users`
2. `books`
3. `bookings`
4. `book_reservations`
5. `page_images`
6. `room_images`

In particular, the `users` and `books` tables must exist before tables that reference them are created. This avoids the foreign-key import failure seen with the earlier, unsorted SQL export.

## Empty by design

Importing `schema.sql` creates the structure only. It does **not** create a login account, administrator, catalogue records or bookings. This keeps personal data out of the public GitHub repository.

For a live deployment, always make a backup before making database changes. Do not publish exports from a populated database.
