# Security notes

This is coursework and a portfolio repository, **not a security-certified production application**. A PHP syntax check is not a substitute for a full security review.

## Credentials and data

- `config.local.php` contains database credentials and is excluded using `.gitignore`.
- `config.local.example.php` shows configuration field names but no valid credentials.
- `database/schema.sql` contains table definitions only. No real account records or password hashes belong in this repository.
- Do not commit a populated `.sql` export, `.env` file, database backup or logs with sensitive information.
- If credentials were ever published, rotate them. Adding `.gitignore` does not erase secrets from past Git history.

## Before publishing a live instance

- Test user roles and authorization on every sensitive page and API action.
- Check that requests which change data are protected against CSRF.
- Validate user input and use prepared SQL statements consistently.
- Restrict uploaded file types and prevent PHP execution inside upload directories.
- Ensure private configuration files and logs cannot be downloaded from the browser.
- Use HTTPS, current PHP security fixes and private administrator credentials.
- Do not expose real student names, contact details, account hashes or library records without authorization.

The repository includes `.htaccess` restrictions, but their effective behavior depends on server configuration and must be tested. These notes are a checklist for review, not a claim that all checks have passed.
