# Local installation

These steps describe a fresh installation, not the existing InfinityFree website.

## 1. Requirements

- PHP 8.0 or newer (the database connection uses `str_starts_with`)
- MySQL or MariaDB
- Apache or another PHP-capable web server
- XAMPP is a convenient option on Windows

## 2. Place the files

Clone or extract the repository to `C:\xampp\htdocs\e-Library-Management-System`.
The file `index.php` should sit directly inside that directory, not inside an extra nested folder.

## 3. Create the database

Start Apache and MySQL, then open `http://localhost/phpmyadmin`.

Create an empty database, for example `elibrary_booking`.
Select it and use **Import** to upload `database/schema.sql`.

There should be **six tables**: `users`, `books`, `bookings`, `book_reservations`, `room_images`, `page_images`.

The SQL file does not populate any records. It is designed for import into a new empty database. Do not import it over a database that contains important data without making a backup.

## 4. Configure the connection

Make a local copy of `config.local.example.php` named `config.local.php`.
For a default local XAMPP installation the settings are usually:

```php
<?php
return [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'elibrary_booking',
];
```

Change these values for your own environment. A production server normally has a different hostname, username and password. Do not upload `config.local.php` to GitHub.

## 5. Open the application

Visit `http://localhost/e-Library-Management-System/`.
The root page redirects to `login.php`.

The public registration page can be used to create standard application accounts. An administrator account is not created by the SQL file; provision it separately using a trusted administrative process. Do not add a public install script or a shared admin password just for convenience.

## Troubleshooting

| Symptom | First check |
| --- | --- |
| Database connection error | Verify `config.local.php`, database name and MySQL service. |
| No tables / SQL error | Import into a **new empty database** and verify all six tables. |
| Images missing | Confirm `uploads/rooms/` and `uploads/books/` were copied. |
| 404 page | Verify the folder name in `htdocs` and that `index.php` is at its root. |
| Blank screen | Check PHP version and server error logs. Avoid showing errors to public visitors. |

### Hosting

This project runs on PHP/MySQL hosting, not GitHub Pages. The hosting copy should use the same application code but a separate, private `config.local.php` and its own database. Ensure the app is securely configured and tested before making it publicly accessible.
