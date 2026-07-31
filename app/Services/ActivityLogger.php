<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ActivityLogger
{
    public function log(string $event, string $description, ?Model $subject = null, array $old = [], array $new = [], ?int $actorId = null): void
    {
        try {
            if (! Schema::hasTable('activity_logs')) {
                return;
            }

            ActivityLog::create([
                'actor_id' => $actorId ?? Auth::id(),
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'event' => $event,
                'description' => $description,
                'old_values' => $this->sanitize($old),
                'new_values' => $this->sanitize($new),
                'ip_address' => request()?->ip(),
                'user_agent' => mb_substr((string) request()?->userAgent(), 0, 1000),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Activity log could not be written.', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }

    private function sanitize(array $values): array
    {
        $sensitiveKeys = [
            'password',
            'remember_token',
            'hitpay_api_key',
            'hitpay_salt',
            'hitpay_webhook_salt',
            'stripe_secret_key',
            'stripe_webhook_secret',
        ];

        $values = collect($values)->except($sensitiveKeys)->all();
        if (in_array($values['key'] ?? null, $sensitiveKeys, true)) {
            $values['value'] = '[redacted]';
        }
        return $values;
    }
}
