# e-Library Kolej ATX

A library booking system I worked on for my Information Technology practical assignment at Kolej Vokasional Kangar.

I made this project using PHP and MySQL. Students can look through the book catalogue, send a book reservation request and book a library room. There's also an admin section for managing books, users and bookings.

**Hosted website:** [elibrary-kolej-atx.infy.click](https://elibrary-kolej-atx.infy.click/)  
The hosted website uses a separate database from the one shared here.

![Stars](https://img.shields.io/github/stars/Haziq-Adam/Haziq-Adam?style=flat-square&color=007ec6)
![Forks](https://img.shields.io/github/forks/Haziq-Adam/Haziq-Adam?style=flat-square&color=007ec6)
![Commits](https://img.shields.io/github/commit-activity/t/Haziq-Adam/Haziq-Adam?style=flat-square&label=commits&color=007ec6)
![Last Commit](https://img.shields.io/github/last-commit/Haziq-Adam/Haziq-Adam?style=flat-square&label=last%20commit&color=44cc11)
![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen?style=flat-square)

## Contents

- [What the system does](#what-the-system-does)
- [Tools I used](#tools-i-used)
- [Run it on your computer](#run-it-on-your-computer)
- [What I learned](#what-i-learned)
- [Project files](#project-files)

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
2. Put this project inside `C:\xampp\htdocs\e-Library-Management-System`.
3. Open phpMyAdmin and create an empty database called `elibrary_booking`.
4. Import [`database/schema.sql`](database/schema.sql).
5. Make a copy of `config.local.example.php` named `config.local.php`, then add your own MySQL settings.
6. Open `http://localhost/e-Library-Management-System/`.

The SQL file only creates the tables. It doesn't include student accounts, books, passwords or an admin account, so you'll need to set up your own data.

For more details, see the [installation guide](docs/INSTALLATION.md).

## What I learned

One problem I ran into was a MySQL import error when moving the project from XAMPP to hosting. Some tables referred to `users` and `books` before those tables existed. I fixed the order in the SQL file so the tables it depends on are created first.

## Project files

| Section | Files |
| --- | --- |
| Login and account pages | [`login.php`](login.php), [`register.php`](register.php), [`profile.php`](profile.php) |
| Books and reservations | [`tempah_buku.php`](tempah_buku.php), [`status_buku.php`](status_buku.php) |
| Room bookings | [`booking.php`](booking.php), [`status_tempahan.php`](status_tempahan.php) |
| Admin pages | [`admin_dashboard.php`](admin_dashboard.php), [`admin_books.php`](admin_books.php) |
| Database | [`database/schema.sql`](database/schema.sql) |
| More documentation | [File guide](docs/PROJECT_MAP.md), [Database](docs/DATABASE.md), [Security](docs/SECURITY.md), [Checks](docs/CHECKS.md) |

This is a college assignment and a portfolio project. It hasn't gone through a full security review. The repository doesn't contain the actual hosting password or personal data from the live database.
