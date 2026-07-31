# Mueble Desk Installation and User Guide

## 1. Purpose

Mueble Desk is a Laravel-based business operations system for clients, quotations, invoices, payments, expenses, recurring invoices, customer portal access, and Malaysian MyInvois e-Invoice submission.

## 2. Recommended production stack

- Ubuntu 22.04 or 24.04 LTS
- Nginx or Apache
- PHP 8.2 or newer with: CLI, FPM, BCMath, Ctype, cURL, DOM/XML, Fileinfo, GD, Intl, Mbstring, OpenSSL, PDO MySQL, Tokenizer, Zip
- MySQL 8 or MariaDB 10.6+
- Composer 2
- Node.js 20 LTS and npm
- Redis server and PHP Redis extension
- Supervisor
- Git
- Cron
- Valid SSL certificate
- SMTP account for notifications

## 3. Moving an existing installation

You may use the existing Git repository and branch. Do not copy `vendor`, `node_modules`, framework caches, or logs from the old server.

Back up the old server first:

```bash
mysqldump --single-transaction --routines --triggers DB_NAME > muebledesk.sql
cd /path/to/muebledesk
 tar -czf muebledesk-storage.tar.gz storage/app/public storage/app/private 2>/dev/null || true
cp .env .env.production.backup
```

Copy securely to the new server:

- Database dump
- `.env`
- User-uploaded files under `storage/app`
- Any custom public assets not committed to Git

Never commit `.env` or credentials to Git.

## 4. Fresh server installation

```bash
sudo apt update
sudo apt install -y nginx mysql-server redis-server supervisor git unzip curl \
  php-fpm php-cli php-mysql php-curl php-xml php-mbstring php-zip php-bcmath php-gd php-intl php-redis
```

Install Composer 2 and Node.js 20 using your normal trusted package source.

Clone the application:

```bash
sudo mkdir -p /var/www/muebledesk
sudo chown $USER:$USER /var/www/muebledesk
git clone https://github.com/mueblegroup/muebledesk.git /var/www/muebledesk
cd /var/www/muebledesk
git checkout agent/myinvois-phase-1
```

Install dependencies and build assets:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
```

## 5. Environment configuration

You may copy the existing `.env`, but review every server-specific or secret setting.

Required changes normally include:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://desk.example.com

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=muebledesk
DB_USERNAME=muebledesk
DB_PASSWORD=STRONG_PASSWORD

CACHE_STORE=redis
SESSION_DRIVER=database
QUEUE_CONNECTION=redis

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=billing@example.com
MAIL_FROM_NAME="Mueble Desk"
```

Keep the existing `APP_KEY` when migrating an existing database. Changing it can invalidate encrypted data and sessions. Generate a key only for a genuinely new installation:

```bash
php artisan key:generate
```

## 6. Database and storage

Create the database and user, import the backup, then run migrations:

```bash
mysql -u root -p muebledesk < muebledesk.sql
php artisan migrate --force
php artisan storage:link
```

Restore uploaded storage and set permissions:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

## 7. MyInvois configuration

Start in Sandbox:

```env
MYINVOIS_ENABLED=true
MYINVOIS_ENVIRONMENT=sandbox
MYINVOIS_PRODUCTION_ENABLED=false
```

Configure Sandbox and Production credentials separately. Confirm supplier TIN, registration type and number, MSIC, business activity, address, state code, postcode, phone and email.

Test the connection:

```bash
php artisan myinvois:test-connection
```

Before enabling live submission:

1. Complete a customer profile.
2. Submit a fully paid invoice in Sandbox.
3. Confirm automatic status polling.
4. Confirm VALID status, UUID, Long ID and QR.
5. Confirm customer email delivery.
6. Test cancellation with a valid Sandbox document.
7. Switch environment to production while keeping `MYINVOIS_PRODUCTION_ENABLED=false`.
8. Test Production authentication.
9. Enable Production only after the interface is confirmed to block live submission correctly.

Final live switches:

```env
MYINVOIS_ENVIRONMENT=production
MYINVOIS_ENABLED=true
MYINVOIS_PRODUCTION_ENABLED=true
```

## 8. Queue worker

Production must not use the synchronous queue for e-Invoice polling and email delivery.

Supervisor example:

```ini
[program:muebledesk-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/muebledesk/artisan queue:work redis --sleep=3 --tries=8 --timeout=120
user=www-data
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/www/muebledesk/storage/logs/queue-worker.log
stopwaitsecs=3600
```

Enable it:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart muebledesk-worker:*
```

## 9. Scheduler

Add one cron entry for Laravel scheduling:

```cron
* * * * * cd /var/www/muebledesk && php artisan schedule:run >> /dev/null 2>&1
```

## 10. Web server

Point the web root to `/var/www/muebledesk/public`, never to the project root. Enable HTTPS and redirect HTTP to HTTPS. Protect `.env`, `.git`, storage internals and backup files from public access.

After deployment:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

## 11. Customer workflow

1. Log in to the customer portal.
2. Open **My e-Invoice Profile**.
3. Enter NRIC and search MyInvois to retrieve and verify the TIN.
4. Enter the full legal name and billing address.
5. Save the profile.
6. Open a fully paid invoice under **My Invoices**.
7. Select **Generate My e-Invoice**.
8. Review the buyer details, amount, tax and environment.
9. Confirm and submit.
10. Wait for automatic validation or use Refresh Status.
11. When VALID, open the QR or validated MyInvois link.

## 12. Staff workflow

- Create and maintain clients.
- Create quotations and convert them to invoices.
- Record payments.
- Submit e-Invoices on behalf of customers when required.
- Review validation errors and retry corrected INVALID, REJECTED or FAILED records.
- Cancel valid documents within the permitted cancellation period using a reason.
- Never blindly retry an uncertain timeout, duplicate or reconciliation-required submission.

## 13. Backup policy

Back up at minimum:

- Database every day
- `storage/app` every day
- `.env` after each configuration change, stored encrypted
- Off-server copy with retention

Test restoration regularly. A backup is not reliable until a restore has succeeded.

## 14. Deployment updates

```bash
cd /var/www/muebledesk
git fetch origin
git checkout agent/myinvois-phase-1
git pull --ff-only origin agent/myinvois-phase-1
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

Use maintenance mode for disruptive releases:

```bash
php artisan down --retry=60
# deploy
php artisan up
```

## 15. Health checks

```bash
php artisan about
php artisan migrate:status
php artisan route:list
php artisan queue:work --once
php artisan myinvois:test-connection
php artisan test
```

Check:

- `storage/logs/laravel.log`
- `storage/logs/queue-worker.log`
- Web server error logs
- PHP-FPM logs
- Supervisor status
- Redis and database availability

## 16. Common problems

### HTTP 500 after moving

Check `laravel.log`, permissions, `.env`, `APP_KEY`, database connectivity, PHP extensions and cached configuration.

```bash
php artisan optimize:clear
sudo chown -R www-data:www-data storage bootstrap/cache
```

### Queue jobs do not run

Check `QUEUE_CONNECTION`, Redis, Supervisor and failed jobs.

```bash
sudo supervisorctl status
php artisan queue:failed
php artisan queue:restart
```

### Emails do not arrive

Verify SMTP credentials, sender domain DNS, spam folder and queued jobs.

### MyInvois lookup or submission fails

Confirm environment, credentials, supplier profile, TLS, server time, queue worker, TIN/ID match and correct `MYS`/state codes.

### Assets are missing

```bash
npm ci
npm run build
php artisan optimize:clear
```

## 17. Security checklist

- `APP_DEBUG=false`
- HTTPS only
- Strong database and SMTP passwords
- Restrict SSH and database access
- Keep MyInvois credentials server-side
- Use least-privilege file permissions
- Patch OS, PHP, Composer and npm dependencies
- Monitor failed logins and application logs
- Never expose backups or `.env`
- Use two-factor authentication for privileged accounts
