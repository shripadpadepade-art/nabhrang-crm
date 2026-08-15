# Nabhrang Cultural Platform

Phase 1 is a Marathi-first PHP 8 + MySQL 8 foundation for a dynamic cultural organization website. It includes a public landing page, secure admin login, dashboard, organization/site settings, bilingual-ready database fields, CSRF protection, PDO prepared statements, sessions, role-ready admin users, and an audit log foundation.

## cPanel installation

1. Create a MySQL 8 database and user with `utf8mb4` support.
2. Import `database.sql` through phpMyAdmin.
3. Set the required `NABHRANG_DB_HOST`, `NABHRANG_DB_PORT`, `NABHRANG_DB_NAME`, `NABHRANG_DB_USER`, `NABHRANG_DB_PASS`, `NABHRANG_BASE_URL`, and `NABHRANG_ENV` environment variables.
4. Point the domain document root at this project directory.
5. Keep `config/` and `storage/` non-public; the included `.htaccess` files block direct access.
6. Create the first administrator from a shell using `php scripts/create_admin.php username "Marathi name"`; the password is entered interactively and is not exposed in shell history.
7. Open `/admin/login.php` and sign in.

## Security checklist

- Use a unique database user with only this database's permissions.
- Run the application over HTTPS so secure session cookies are enabled.
- Replace the temporary remote image URLs and upload a logo through the future asset settings module.
- Keep `config/config.php` outside public web root when the host allows it.
- Never store passwords in plain text; the admin helper uses `password_hash()`.

## Phase 1 routes

- `/` — dynamic public landing page
- `/admin/login.php` — administrator login
- `/admin/index.php` — dashboard
- `/admin/settings.php` — Marathi/English organization and site settings

Phase 2 will extend the same PDO/service structure with dynamic registration, membership types, QR payment submission, verification, and member accounts.