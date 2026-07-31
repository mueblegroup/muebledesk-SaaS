<?php

namespace App\Services\MyInvois;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MyInvoisClient
{
    public function environment(): string
    {
        $environment = (string) config('myinvois.environment', 'sandbox');
        if (! in_array($environment, ['sandbox', 'production'], true)) {
            throw new RuntimeException('Invalid MyInvois environment configured.');
        }
        return $environment;
    }

    public function authenticate(bool $forceRefresh = false): array
    {
        $cacheKey = config('myinvois.cache.token_key').'.'.$this->environment();
        if (! $forceRefresh && ($cached = Cache::get($cacheKey))) return $cached;

        $credentials = $this->credentials();
        $response = $this->baseRequest()->asForm()->post($this->apiUrl().'/connect/token', [
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
            'grant_type' => 'client_credentials',
            'scope' => 'InvoicingAPI',
        ])->throw()->json();

        if (empty($response['access_token'])) throw new RuntimeException('MyInvois authentication did not return an access token.');
        $ttl = max(60, (int) ($response['expires_in'] ?? 3600) - (int) config('myinvois.cache.token_ttl_buffer_seconds', 60));
        Cache::put($cacheKey, $response, now()->addSeconds($ttl));
        return $response;
    }

    public function searchTin(string $idType, string $idValue, ?string $fileType = null): ?string
    {
        $query = ['idType' => strtoupper(trim($idType)), 'idValue' => preg_replace('/[^A-Za-z0-9]/', '', trim($idValue))];
        if ($fileType !== null && $fileType !== '') $query['fileType'] = $fileType;
        $response = $this->authenticatedRequest()->get($this->apiUrl().'/api/v1.0/taxpayer/search/tin', $query);
        if ($response->status() === 404) return null;
        $tin = $response->throw()->json('tin');
        return is_string($tin) && $tin !== '' ? strtoupper(trim($tin)) : null;
    }

    public function validateTin(string $tin, string $idType, string $idValue): bool
    {
        $response = $this->authenticatedRequest()->get($this->apiUrl().'/api/v1.0/taxpayer/validate/'.rawurlencode($tin), ['idType' => $idType, 'idValue' => $idValue]);
        if ($response->successful()) return true;
        if ($response->status() === 404) return false;
        $response->throw();
        return false;
    }

    public function submitDocuments(array $documents): array
    {
        $this->assertSubmissionEnabled();
        return $this->normalise($this->authenticatedRequest()->post($this->apiUrl().'/api/v1.0/documentsubmissions/', ['documents' => $documents]));
    }

    public function getSubmission(string $submissionUid): array
    {
        $response = $this->authenticatedRequest()->get($this->apiUrl().'/api/v1.0/documentsubmissions/'.rawurlencode($submissionUid), ['pageNo' => 1, 'pageSize' => 100]);
        if (! $response->successful()) $response->throw();
        return $response->json();
    }

    public function cancelDocument(string $uuid, string $reason): array
    {
        $response = $this->authenticatedRequest()->put($this->apiUrl().'/api/v1.0/documents/state/'.rawurlencode($uuid).'/state', [
            'status' => 'cancelled',
            'reason' => trim($reason),
        ]);
        return $this->normalise($response);
    }

    public function validationUrl(string $uuid, string $longId): string
    {
        return rtrim($this->portalUrl(), '/').'/'.$uuid.'/share/'.$longId;
    }

    private function assertSubmissionEnabled(): void
    {
        if (! config('myinvois.enabled')) throw new RuntimeException('MyInvois submission is disabled.');
        if ($this->environment() === 'production') {
            if (! app()->environment('production')) throw new RuntimeException('Production MyInvois submissions require APP_ENV=production.');
            if (! config('myinvois.production_enabled')) throw new RuntimeException('Production submission is disabled. Set MYINVOIS_PRODUCTION_ENABLED=true after final approval.');
        }
    }

    private function normalise(Response $response): array
    {
        return [
            'successful' => $response->successful(),
            'status' => $response->status(),
            'body' => $response->json() ?: ['message' => $response->body()],
            'retry_after' => max(0, (int) $response->header('Retry-After', 0)),
            'correlation_id' => $response->header('correlationId') ?: $response->header('Correlation-Id'),
        ];
    }

    private function authenticatedRequest(): PendingRequest
    {
        return $this->baseRequest()->withToken($this->authenticate()['access_token']);
    }

    private function baseRequest(): PendingRequest
    {
        return Http::acceptJson()->asJson()
            ->timeout((int) config('myinvois.http.timeout', 30))
            ->connectTimeout((int) config('myinvois.http.connect_timeout', 10))
            ->withOptions(['verify' => filter_var(config('myinvois.http.verify_tls', true), FILTER_VALIDATE_BOOL)]);
    }

    private function credentials(): array
    {
        $credentials = config('myinvois.environments.'.$this->environment());
        if (empty($credentials['client_id']) || empty($credentials['client_secret'])) throw new RuntimeException('MyInvois credentials are missing for '.$this->environment().'.');
        return $credentials;
    }

    private function apiUrl(): string { return rtrim((string) config('myinvois.environments.'.$this->environment().'.api_url'), '/'); }
    private function portalUrl(): string { return rtrim((string) config('myinvois.environments.'.$this->environment().'.portal_url'), '/'); }
}
