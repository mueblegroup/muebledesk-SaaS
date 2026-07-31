<?php

namespace App\Console\Commands;

use App\Services\MyInvois\SupplierProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProductionReadinessCheck extends Command
{
    protected $signature = 'app:production-check {--strict : Return a failure exit code when warnings are present}';

    protected $description = 'Audit Mueble Desk configuration and runtime requirements before production rollout';

    public function handle(SupplierProfile $supplierProfile): int
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
            $requiredTables = ['users', 'clients', 'invoices', 'payments', 'expenses', 'recurring_invoices', 'einvoices', 'einvoice_submissions'];
            foreach ($requiredTables as $table) {
                $check(Schema::hasTable($table), 'Table '.$table.' exists.', 'Required table '.$table.' is missing.');
            }
        } catch (Throwable $exception) {
            $failures[] = 'Could not inspect migrations or tables: '.$exception->getMessage();
        }

        $myInvoisEnvironment = (string) config('myinvois.environment');
        $myInvoisEnabled = (bool) config('myinvois.enabled');
        $productionEnabled = (bool) config('myinvois.production_enabled');
        $check(in_array($myInvoisEnvironment, ['sandbox', 'production'], true), 'MyInvois environment value is valid.', 'MYINVOIS_ENVIRONMENT must be sandbox or production.');

        if ($myInvoisEnvironment === 'production' || $productionEnabled) {
            $check($myInvoisEnvironment === 'production', 'MyInvois uses production.', 'MYINVOIS_ENVIRONMENT must be production when live submission is enabled.');
            $check($myInvoisEnabled, 'MyInvois master switch is enabled.', 'MYINVOIS_ENABLED must be true for production submission.');
            $check($productionEnabled, 'MyInvois production switch is enabled.', 'MYINVOIS_PRODUCTION_ENABLED is false.', false);
            $check(config('myinvois.http.verify_tls') === true || config('myinvois.http.verify_tls') === 'true', 'MyInvois TLS verification is enabled.', 'MYINVOIS_VERIFY_TLS must be true.');
            $check(filled(config('myinvois.environments.production.client_id')), 'MyInvois production Client ID is present.', 'MYINVOIS_PRODUCTION_CLIENT_ID is missing.');
            $check(filled(config('myinvois.environments.production.client_secret')), 'MyInvois production Client Secret is present.', 'MYINVOIS_PRODUCTION_CLIENT_SECRET is missing.');

            try {
                $supplier = $supplierProfile->get();
                foreach (['tin', 'registration_type', 'registration_number', 'msic_code', 'business_activity', 'name', 'phone', 'address_line_1', 'city', 'state_code', 'postcode', 'country_code'] as $field) {
                    $check(filled($supplier[$field] ?? null), 'Supplier '.$field.' is configured.', 'Supplier '.$field.' is missing from Admin Settings or .env.');
                }
            } catch (Throwable $exception) {
                $failures[] = 'Could not load MyInvois supplier profile: '.$exception->getMessage();
            }
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
        $this->comment('Manual checks still required: cron runs schedule:run every minute, a queue worker is continuously supervised, backups restore successfully, web root points to /public, HTTPS redirects correctly, and production webhook URLs/secrets are registered with each payment gateway.');

        if ($failures !== []) {
            return self::FAILURE;
        }

        if ($this->option('strict') && $warnings !== []) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
