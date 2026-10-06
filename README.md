# Radiantlinks VTU

Standalone raw-PHP VTU platform inspired by VTUpress, but rebuilt as a normal PHP web app instead of a WordPress plugin.

## Goals

- Mobile/app-friendly customer and admin dashboards.
- API-driven airtime, data, cable, electricity, exam ePIN, and data PIN transactions.
- Bulk transaction processing with per-row tracking.
- Campaigns with SMTP or PHP mail delivery.
- Welcome templates for new customers.
- Customizable electricity receipts.
- Owner-controlled activation.
- Strong security defaults: PDO prepared statements, CSRF protection, secure sessions, password hashing, transaction-safe wallet debit/credit, audit logs, rate-limit tables, and IP/device records.

## Requirements

- PHP 8.1+
- MySQL/MariaDB
- PDO MySQL extension
- `mod_rewrite` for Apache friendly URLs

## Install

1. Create a MySQL database.
2. Copy `.env.example` to `.env`.
3. Set database credentials and `APP_KEY`.
4. Point your web root to `public/`.
5. Visit `/install` to create the tables and owner account.

## Project Shape

- `public/` - web root and assets.
- `app/Core/` - router, database, security, validation, mailer.
- `app/Controllers/` - HTTP actions.
- `app/Services/` - business logic.
- `app/Providers/` - VTU API provider adapters.
- `app/Views/` - PHP templates.
- `database/` - schema and seeds.

## No Composer Requirement

Radiantlinks VTU is designed to run on shared hosting without Composer. It still uses a clean namespaced structure with a local autoloader, controllers, services, provider adapters, and a public web root.

Composer can be added later for optional packages such as a full SMTP client, QR/PDF receipt generation, or SDK integrations, but the core app should not depend on it.
