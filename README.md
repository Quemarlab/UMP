# UMP — Unprocessed Materials Production

Daily stock ledger for unprocessed materials (received by shift, issued, closing balance) with dashboards, reports,
document attachments and an audit trail. Part of the Young Masters platform: it shares **one database, one set of
accounts, one password, two-factor authentication and the mail settings** with the website admin.
PHP 8.1+, MySQL/MariaDB, Bootstrap 5. No framework, no build step.

## How the two systems fit together

| | Website (`YM` repo) | UMP (this repo) |
|---|---|---|
| Purpose | public site + content admin | production ledger |
| Accounts / passwords / 2FA | shared (`users` table) | shared |
| Roles | Administrator, Editor | Administrator, Operator, Viewer |
| Who manages users | **website admin → Users** (assign access to either system) | read-only "People" list here |
| Own tables | services, projects, posts, gallery_items, messages, subscribers | stock_records, stock_revisions, stock_attachments |
| Shared tables | users, user_access, login_attempts, password_resets, rate_limits, site_settings, audit_logs, schema_migrations | same |

Deploy each in its own folder or sub-domain (e.g. `youngmasters.rw` and `ump.youngmasters.rw`), point both `.env` files at the
**same database**, and use the same SMTP settings. Set `YM_URL` here and `UMP_URL` in the website's `.env` to get the sidebar shortcuts.
The platform tables are defined identically in both repositories' `database/schema.sql` and `database/migrations/platform_*.php`;
change them in both places.

## Setup

1. Copy `.env.example` to `.env`; use the website's database name and mail values.
2. `php database/install.php` creates/upgrades the tables (safe to repeat). If the website is already installed, accounts already exist:
   give yourself access with `php database/install.php --admin=you@example.com` or from the website admin → Users.
3. Open **Settings** and enter the starting stock, unit, monthly target and low-stock level.
4. Add the hourly scheduled task for emails (cPanel → Cron Jobs):
   `0 * * * *  /usr/local/bin/php /home/USER/public_html/ump/bin/cron.php`

Importing older data: `php database/install.php --merge-from=ump` (the separate `ump` database used before the merge) or
`--migrate-legacy` (the very first version's tables). Then `php database/cleanup-legacy.php` lists unused tables (`--confirm` backs them up and drops them).

## Features

- **Dashboard** — closing stock, today, month-to-date vs target, **month-end forecast**, recording **calendar**, **unusual-day detection**, 30-day charts, week-on-week.
- **Daily record** — opening stock from the previous closing, live totals, refuses negative stock, reason required for every edit, full history, **GRV/GIV scans attached** (PDF/image, private storage), **printable daily report** with signature lines.
- **Records / Reports** — filters, totals, CSV export, print; monthly and yearly summaries, shift split, missing days, stock-continuity check with admin realign.
- **Controls** — period lock (month-end close), read-only mode, operator edit window.
- **Email** — daily summary, low-stock alert, "no record yet today" reminder (cron), per-person opt-in, extra recipients, test button.
- **Security** — individual accounts with per-system roles, two-factor authentication (authenticator app + recovery codes), server-side login lockout,
  CSRF on every action, parameterised SQL, escaped output, no inline scripts (CSP), audit trail. Session timeout, lockout, password length and
  "require 2FA for administrators" are set once in the website admin and apply to both systems.

## Layout

| Path | Purpose |
|---|---|
| `dashboard.php`, `record.php`, `records.php`, `reports.php`, `report-day.php`, `attachment.php` | Production pages |
| `settings.php`, `users.php` (directory), `activity.php`, `backup.php`, `profile.php` | Administration and account pages |
| `login.php`, `login-2fa.php`, `forgot-password.php`, `reset-password.php` | Sign-in flow |
| `app/Core/` | Env, DB, Session, Csrf, Auth, Totp, Migrator, Validator, RateLimiter, Settings, Mailer, Notifier, Audit, Backup |
| `app/Repositories/` | `Stock` (ledger rules, forecast, anomalies), `Attachments`, `Users` |
| `database/` | `schema.sql`, `migrations/`, CLI installer, legacy cleanup |
| `bin/cron.php` | Scheduled emails (CLI only) |
| `storage/` | Logs, backups, private uploads (blocked from the web) |