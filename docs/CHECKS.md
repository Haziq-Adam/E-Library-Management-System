# Checks and limitations

## Checks included in the repository

- GitHub Actions runs `php -l` on each PHP file when code is pushed or a pull request is opened.
- `database/schema.sql` lists the `users` and `books` parent tables before the reservations and bookings that reference them.
- The database file includes no account or reservation records.
- `config.local.php` is listed in `.gitignore` and is not included in this repository.

## What these checks don't prove

A PHP syntax check doesn't confirm that login, reservations, uploads or permissions work. It also does not check for security vulnerabilities. The live InfinityFree deployment has not been tested by this workflow.

Before making a deployed copy available to others, test the user roles, create and cancel bookings, approve/reject book requests, and review all file-upload and admin endpoints. Use a separate test database with dummy accounts.

## Run the syntax check on Windows

In PowerShell, from the project folder with PHP available on your PATH:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

If PHP is installed with XAMPP and not on PATH, replace `php` with `C:\xampp\php\php.exe`.
