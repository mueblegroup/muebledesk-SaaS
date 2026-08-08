<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProductionReadinessCheck extends Command
{
    protected $signature = 'app:production-check {--strict : Return a failure exit code when warnings are present}';

    protected $description = 'Audit Mueble Desk configuration and runtime requirements before production rollout';

    public function handle(): int
    {
        $passed = [];
        $warnings = [];
        $failures = [];

        $check = function (bool $condition, string $success, string $failure, bool $fatal = true) use (&$passed, &$warnings, &$failures): void {
            if ($condition) {
                $passed[] = $success;
                return;
            }

            if ($fatal) {
                $failures[] = $failure;
            } else {
                $warnings[] = $failure;
            }
        };

        $check(app()->environment('production'), 'APP_ENV is production.', 'APP_ENV must be production.');
        $check(! config('app.debug'), 'APP_DEBUG is disabled.', 'APP_DEBUG must be false.');
        $check(str_starts_with((string) config('app.url'), 'https://'), 'APP_URL uses HTTPS.', 'APP_URL must use HTTPS.');
        $check(filled(config('app.key')), 'APP_KEY is configured.', 'APP_KEY is missing.');

        $check(config('queue.default') !== 'sync', 'Queue connection is asynchronous.', 'QUEUE_CONNECTION must not be sync in production.');
        $check(config('cache.default') !== 'array', 'Persistent cache is configured.', 'CACHE_STORE should be database, file, or Redis—not array.', false);
        $check(config('session.driver') !== 'array', 'Persistent sessions are configured.', 'SESSION_DRIVER must not be array.');

        $mailMailer = (string) config('mail.default');
        $check(! in_array($mailMailer, ['log', 'array'], true), 'Mail uses a delivery transport.', 'MAIL_MAILER must not be log or array in production.');
        $check(filled(config('mail.from.address')), 'Mail sender address is configured.', 'MAIL_FROM_ADDRESS is missing.');

        try {
            DB::connection()->getPdo();
            $passed[] = 'Database connection is available.';
        } catch (Throwable $exception) {
            $failures[] = 'Database connection failed: '.$exception->getMessage();
        }

        foreach (['storage', 'storage/framework', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $directory) {
            $path = base_path($directory);
            $check(File::isDirectory($path), $directory.' exists.', $directory.' does not exist.');
            if (File::isDirectory($path)) {
                $check(is_writable($path), $directory.' is writable.', $directory.' is not writable.');
            }
        }

        $publicStorage = public_path('storage');
        $check(is_link($publicStorage) || File::isDirectory($publicStorage), 'Public storage link is present.', 'Run php artisan storage:link.', false);

        try {
            $migrationCount = DB::table('migrations')->count();
            $check($migrationCount > 0, 'Database migrations are present.', 'No migration history was found.');
            $requiredTables = ['companies', 'company_subscriptions', 'platform_subscription_plans', 'settings', 'users', 'clients', 'invoices', 'payments', 'expenses', 'recurring_invoices', 'einvoices', 'einvoice_submissions'];
            foreach ($requiredTables as $table) {
                $check(Schema::hasTable($table), 'Table '.$table.' exists.', 'Required table '.$table.' is missing.');
            }

            if (Schema::hasTable('settings')) {
                $check(Schema::hasColumn('settings', 'company_id'), 'Settings are tenant-scoped.', 'settings.company_id is missing.');
            }
            if (Schema::hasTable('einvoices')) {
                $check(Schema::hasColumn('einvoices', 'company_id'), 'e-Invoices are tenant-scoped.', 'einvoices.company_id is missing.');
            }
            if (Schema::hasTable('einvoice_submissions')) {
                $check(Schema::hasColumn('einvoice_submissions', 'company_id'), 'e-Invoice submissions are tenant-scoped.', 'einvoice_submissions.company_id is missing.');
            }
        } catch (Throwable $exception) {
            $failures[] = 'Could not inspect migrations or tables: '.$exception->getMessage();
        }

        $check(config('myinvois.http.verify_tls') === true || config('myinvois.http.verify_tls') === 'true', 'MyInvois TLS verification is enabled.', 'MYINVOIS_VERIFY_TLS must be true.');
        $check(filled(config('myinvois.environments.sandbox.api_url')), 'MyInvois sandbox API URL is configured.', 'MyInvois sandbox API URL is missing.');
        $check(filled(config('myinvois.environments.production.api_url')), 'MyInvois production API URL is configured.', 'MyInvois production API URL is missing.');

        if (config('myinvois.production_enabled')) {
            $passed[] = 'Platform MyInvois production safety switch is enabled.';
        } else {
            $warnings[] = 'MYINVOIS_PRODUCTION_ENABLED is false. Keep this false until live submission is approved.';
        }

        if (filled(config('myinvois.environments.production.client_id')) || filled(config('myinvois.environments.production.client_secret'))) {
            $warnings[] = 'Legacy platform MyInvois production credentials are still present in .env. Tenant subdomains do not use them; remove them after migration/testing is complete.';
        }

        $stripeSecret = (string) config('services.stripe.secret');
        if ($stripeSecret !== '') {
            $check(! str_starts_with($stripeSecret, 'sk_test_'), 'Stripe does not use a test secret.', 'Stripe is still using a test secret key.');
        } else {
            $warnings[] = 'Stripe secret is not configured. Ignore this only when Stripe is not used.';
        }

        $hitpayApiKey = (string) (config('services.hitpay.api_key') ?? config('services.hitpay.key'));
        if ($hitpayApiKey === '') {
            $warnings[] = 'HitPay API key is not configured. Ignore this only when HitPay is not used.';
        }

        $this->newLine();
        $this->info('Mueble Desk production readiness audit');
        $this->line(str_repeat('=', 40));

        foreach ($passed as $message) {
            $this->line('<fg=green>PASS</> '.$message);
        }
        foreach ($warnings as $message) {
            $this->line('<fg=yellow>WARN</> '.$message);
        }
        foreach ($failures as $message) {
            $this->line('<fg=red>FAIL</> '.$message);
        }

        $this->newLine();
        $this->line('Passed: '.count($passed).' | Warnings: '.count($warnings).' | Failures: '.count($failures));
        $this->newLine();
        $this->comment('Manual checks still required: test MyInvois from at least one sandbox tenant and one approved production tenant, confirm cron runs schedule:run every minute, supervise queue workers continuously, restore backups successfully, point the web root to /public, verify HTTPS/wildcard subdomains, and register production webhook URLs/secrets with each payment gateway.');

        if ($failures !== []) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $warnings !== []) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
