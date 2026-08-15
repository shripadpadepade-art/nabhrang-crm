# Nabhrang — Cultural Organization CMS (PRD)

## Original Problem Statement
Professional, production-ready, mobile-friendly web app for cultural organization "Nabhrang".
Fully dynamic **PHP 8 + MySQL** application (cPanel/shared hosting compatible) — NOT React/FastAPI.
- Admin Panel: content, members, manual QR payments, events, gallery, blogs.
- Manual QR Payment workflow: Register → Pay manually → Enter UTR → Admin verifies → Membership approved.
- UI language: Marathi (English-ready DB). No payment gateways / email in V1.

## Architecture
- Native PHP 8.2, MariaDB (18 tables in /app/database.sql), PDO prepared statements, CSRF, sessions.
- Served in preview via PHP built-in server on port 3000 (supervisor "frontend" runs /app/scripts/dev_server.sh which also starts MariaDB).
- Preview URL: https://nabhrang-admin.preview.emergentagent.com
- Credentials: see /app/memory/test_credentials.md (admin/password123; members log in by EMAIL).

## Implemented (all tested)
### Earlier sessions
- Full DB schema, admin panel modules (blogs, events, gallery, videos, publications, notifications, members, payments, reports/CSV), settings + maintenance mode, member register/login/dashboard/QR payment/UTR, printable membership card, soft deletes, Marathi UI, uploads .htaccess.

### 2026-06 (this session)
- Preview environment wired: PHP server on port 3000 via supervisor; MariaDB auto-start; app testable in side panel.
- Full E2E test run: 24/24 HTTP tests + browser flows PASSED (register → UTR payment → admin verify → card NB-2026-000NN).
- Fixes from test report:
  - Collision-safe membership ID generator (MAX per prefix-year instead of COUNT).
  - Global exception handler → Marathi 500 page (was blank white page).
  - Public blog listing page (/blog.php with no slug) + public events page (/events.php, upcoming + past); homepage links to both.
  - Restore-from-archive for blogs, events, gallery albums (admin ?view=archived toggle).
  - Admin panel + registration form fully responsive at 390px (scrollWidth verified = viewport).
  - Nav fully Marathi (Login → लॉगिन); member dashboard greets by name.
  - Admin/member session key separation on login.
  - Seeded sample payment QR (/uploads/qr/sample_qr.png) — PLACEHOLDER, admin must upload real UPI QR in settings.
  - Repaired corrupted trailing bytes in admin/blogs.php.
- PWA / Add-to-Home-Screen support:
  - /manifest.json (Marathi name, standalone display), /sw.js service worker (cache static assets, network-first pages), branded app icons (/assets/icons/, golden 'न' on velvet).
  - PWA head tags + SW registration injected into every page (11 PHP files). Verified: service worker "activated" on preview.
- Pod-restart resilience: MySQL datadir moved to persistent /app/storage/mysql; /app/scripts/dev_server.sh now auto-reinstalls php-cli/php-mysql/mariadb-server if missing, auto-initializes datadir, auto-imports database.sql + seeds admin/user/QR (scripts/seed_runtime.sql). DB and services now survive pod restarts.

## Backlog
- P1: Verify SEO/OG settings coverage in admin settings.
- P2: Pagination on public blog/events listing (currently LIMIT 60).
- P2: Partial-update settings save (currently saves all whitelisted keys per POST).
- Future: Android REST API (JSON endpoints), email notifications (deferred V1).

## Environment Warning
Self-healing: /app/scripts/dev_server.sh (run by supervisor "frontend") auto-reinstalls php/mariadb after pod restarts and uses persistent datadir /app/storage/mysql. First start after a restart may take ~30-60s (apt install).
Production deployment target is standard cPanel shared hosting (upload files + import database.sql + edit config via env).
