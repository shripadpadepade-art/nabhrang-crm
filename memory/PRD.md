# Nabhrang Cultural Platform — Phase 1

## Original problem statement
Build a dynamic, secure, mobile-friendly PHP 8 + MySQL 8 cultural organization platform for Nabhrang, with all organization and homepage content managed through an admin panel rather than hard-coded.

## Architecture decisions
- Native PHP pages with shared bootstrap/layout files for cPanel/shared-hosting compatibility.
- MySQL schema uses utf8mb4, prepared statements, dynamic settings, bilingual Marathi/English fields, and audit log foundations.
- PHP sessions, CSRF tokens, password_hash/password_verify, output escaping, role-ready admin accounts, and upload/storage access rules are established.
- Public content reads from `settings` and `site_sections`; temporary visuals are replaceable later.

## Implemented
- Public Marathi-first landing page with dynamic identity, hero, about, membership, footer, and responsive theatrical styling.
- Admin login with session regeneration, password verification, CSRF, basic login throttling, and logout POST flow.
- Admin dashboard shell with responsive sidebar and Phase 1 status cards.
- Organization/site settings editor for Marathi and optional English values, with audit logging.
- `database.sql`, cPanel installation README, secure config requirements, admin creation helper, and protected upload/storage rules.

## Prioritized backlog
- P0: Validate on PHP 8 + MySQL 8 host; import schema; create first admin; run php -l and authentication regression.
- P1: Add dynamic membership types, registration field builder, member records, manual QR payment submission and admin verification.
- P1: Add member login/dashboard, payment history, approval workflow, membership ID and digital card.
- P2: Add CMS modules for blogs, events, gallery, videos, publications, notifications, reports, backups, SEO, and maintenance mode.
