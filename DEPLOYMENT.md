# Production deployment

## Server requirements

- PHP 8.1 or newer with PDO MySQL, cURL, OpenSSL, Fileinfo, JSON, and Mbstring.
- MySQL 8+ or MariaDB 10.5+ using InnoDB.
- Apache `mod_rewrite` or equivalent rewrite rules.
- A valid HTTPS certificate.

## Required configuration

1. Point the domain document root to the `public/` directory.
2. Copy `.env.example` to `.env` outside the public document root.
3. Set `APP_ENV=production`, `APP_DEBUG=false`, and the final HTTPS `APP_URL`.
4. Generate unique random values for `APP_KEY` and `OWNER_LICENSE_KEY`.
5. Create a dedicated MySQL user with access only to the application database. Do not use `root`.
6. Make only `storage/` writable by the PHP process. Keep source files read-only.
7. Configure SMTP and test registration, password reset, admin/customer 2FA, and notifications.
8. Configure live VTU provider credentials, import plans, set selling prices, and complete one low-value transaction per enabled service.
9. Configure the payment webhook secret in Admin > Settings and enter the same secret in the payment provider dashboard.
10. Set the provider webhook URL to `https://your-domain.example/payment/webhook`.
11. Change all local/test administrator passwords and transaction PINs before launch.

## Launch checks

- Confirm `/install` redirects to `/login` after the owner exists.
- Confirm direct requests to `.env`, `storage/`, `database/`, and `app/` are denied.
- Confirm failed VTU purchases reverse the wallet debit and retain the provider response for admins.
- Replay one successful webhook and confirm the wallet is credited only once.
- Test database backup and restore before accepting customer funds.
- Keep gateway funding and any unverified service switched off until its live test passes.

The application should first be deployed to a staging subdomain using production-like PHP and MySQL settings. Enable real-money services only after the provider and webhook checks pass.
