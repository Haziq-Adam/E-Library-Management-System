# e-Library Kolej ATX

A library booking system I worked on for my Information Technology practical assignment at Kolej Vokasional Kangar.

I made this project using PHP and MySQL. Students can browse the book catalogue, request book reservations and book library rooms. There is also an admin section for managing books, users and bookings.

**Hosted website:** [elibrary-kolej-atx.infy.click](https://elibrary-kolej-atx.infy.click/)  
The hosted website uses a separate database from the sample database shared in this repository.

![PHP](https://img.shields.io/badge/PHP-Backend-777BB4?style=flat-square)
![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=flat-square)
![JavaScript](https://img.shields.io/badge/JavaScript-Frontend-F7DF1E?style=flat-square)
![HTML5](https://img.shields.io/badge/HTML5-Markup-E34F26?style=flat-square)
![CSS3](https://img.shields.io/badge/CSS3-Styling-1572B6?style=flat-square)
![Full Stack](https://img.shields.io/badge/Full--Stack-Web%20Project-0A66C2?style=flat-square)
![Admin Dashboard](https://img.shields.io/badge/Dashboard-Admin-20B2AA?style=flat-square)
![Coursework](https://img.shields.io/badge/Project-Practical%20Assignment-8A2BE2?style=flat-square)

## Contents

- [What the system does](#what-the-system-does)
- [Tools I used](#tools-i-used)
- [Run it on your computer](#run-it-on-your-computer)
- [Database configuration](#database-configuration)
- [What I learned](#what-i-learned)
- [Project files](#project-files)
- [Project disclaimer](#project-disclaimer)

## What the system does

- Search for books and request a reservation
- Book library rooms by available time slot
- Register, sign in and check booking status
- Manage books, reservations and users from the admin pages

## Tools I used

**PHP, MySQL, HTML, CSS and JavaScript**  
VS Code, XAMPP and phpMyAdmin for development and testing.

## Run it on your computer

1. Install XAMPP and start **Apache** and **MySQL**.
2. Put the project inside `C:\xampp\htdocs\e-Library-Management-System`.
3. Open phpMyAdmin and create an empty database called `elibrary_booking`.
4. Import [`database/schema.sql`](database/schema.sql) into that database.
5. Make a copy of `config.local.example.php` named `config.local.php` and enter your **own** MySQL settings, including the password if required.
6. Open `http://localhost/e-Library-Management-System/` in your browser.

The SQL file creates the database tables only. It does not include student accounts, book records, passwords or an admin account. You will need to enter your own test data and set up any accounts needed for your testing.

For more details, see the [installation guide](docs/INSTALLATION.md).

## Database configuration

**You need to configure your own database connection before running the website.** Do not use the credentials from someone else's hosting account.

Copy `config.local.example.php` to `config.local.php`, then replace the example values:

```php
<?php
return [
    'host' => 'YOUR_MYSQL_HOSTNAME',
    'username' => 'YOUR_MYSQL_USERNAME',
    'password' => 'YOUR_MYSQL_PASSWORD',
    'database' => 'YOUR_FULL_DATABASE_NAME',
];
```

- **XAMPP:** Use your local MySQL settings. A default XAMPP installation often uses `localhost`, `root` and a blank password, but your setup may differ. The database name is `elibrary_booking` if you followed the steps above.
- **InfinityFree:** Open **MySQL Databases** in your own hosting account. Copy the MySQL hostname, username, **MySQL password** and full database name, including its assigned prefix. The hosting database hostname is not `localhost`.
- **Password:** Replace `YOUR_MYSQL_PASSWORD` with **your own MySQL password**. It is not the password for a student's login to the e-Library website. If your local MySQL user has no password, use an empty string (`''`) instead of the placeholder.

**Keep `config.local.php` private.** Do not upload it to a public GitHub repository or include the real password in a README, screenshot or issue. Only share `config.local.example.php` with placeholder values. If you accidentally expose a password, change it in the hosting dashboard.

Import `database/schema.sql` into a **new, empty database**. The tables are ordered so the tables referenced by foreign keys exist before their dependent tables are created. See the [database notes](docs/DATABASE.md) for more information.

## What I learned

One problem I ran into was a MySQL import error when moving the project from XAMPP to InfinityFree. Some tables referred to `users` and `books` before those tables existed. I fixed the order in the SQL file so the tables they depend on are created first.

I also learned that the MySQL settings for local development and hosting are different. The website needs the hostname, username, password and database name for the environment where it is running.

## Project files

| Section | Files |
| --- | --- |
| Login and account pages | [`login.php`](login.php), [`register.php`](register.php), [`profile.php`](profile.php) |
| Books and reservations | [`tempah_buku.php`](tempah_buku.php), [`status_buku.php`](status_buku.php) |
| Room bookings | [`booking.php`](booking.php), [`status_tempahan.php`](status_tempahan.php) |
| Admin pages | [`admin_dashboard.php`](admin_dashboard.php), [`admin_books.php`](admin_books.php) |
| Database | [`database/schema.sql`](database/schema.sql) |
| More documentation | [File guide](docs/PROJECT_MAP.md), [Database](docs/DATABASE.md), [Installation](docs/INSTALLATION.md), [Security](docs/SECURITY.md), [Checks](docs/CHECKS.md) |

## Project disclaimer

This project was developed as part of my Information Technology practical coursework at Kolej Vokasional Kangar. It reflects the skills and experience I had during that stage of my studies. Some features may be incomplete, and there are areas of the code, design and security that still need improvement.

**I am sharing this repository as an example of my coursework and learning progress, not as a production-ready library management service.** The project has not undergone a full security assessment or end-to-end production test.

The repository does not contain the real hosting password or personal data from the live database. Anyone using the source code should create their own database and review the application's security before deploying it publicly.
