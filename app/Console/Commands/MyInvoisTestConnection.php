<?php

namespace App\Console\Commands;

use App\Services\MyInvois\MyInvoisClient;
use Illuminate\Console\Command;
use Throwable;

class MyInvoisTestConnection extends Command
{
    protected $signature = 'myinvois:test-connection {--environment= : sandbox or production} {--validate-tin=} {--id-type=BRN} {--id-value=}';

    protected $description = 'Authenticate with MyInvois and optionally validate a taxpayer TIN';

    public function handle(MyInvoisClient $client): int
    {
        if ($environment = $this->option('environment')) {
            if (! in_array($environment, ['sandbox', 'production'], true)) {
                $this->error('Environment must be sandbox or production.');
                return self::INVALID;
            }

            config(['myinvois.environment' => $environment]);
        }

        try {
            $token = $client->authenticate(true);
            $this->info('Authenticated successfully with MyInvois '.$client->environment().'.');
            $this->line('Token lifetime: '.($token['expires_in'] ?? 'unknown').' seconds');

            if ($tin = $this->option('validate-tin')) {
                $idValue = $this->option('id-value');
                if (! $idValue) {
                    $this->error('--id-value is required when --validate-tin is used.');
                    return self::INVALID;
                }

                $valid = $client->validateTin($tin, (string) $this->option('id-type'), $idValue);
                $this->line($valid ? 'TIN validation passed.' : 'TIN validation failed.');
                return $valid ? self::SUCCESS : self::FAILURE;
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
