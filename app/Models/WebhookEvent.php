<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'gateway', 'event_id', 'event_type', 'transaction_id',
        'status', 'payload_summary', 'error_message', 'received_at', 'processed_at',
    ];

    protected $casts = [
        'payload_summary' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function markProcessed(array $summary = []): void
    {
        $this->forceFill([
            'status' => 'processed',
            'payload_summary' => array_merge($this->payload_summary ?: [], $summary),
            'error_message' => null,
            'processed_at' => now(),
        ])->save();
    }

    public function markIgnored(array $summary = []): void
    {
        $this->forceFill([
            'status' => 'ignored',
            'payload_summary' => array_merge($this->payload_summary ?: [], $summary),
            'processed_at' => now(),
        ])->save();
    }

    public function markFailed(\Throwable|string $error): void
    {
        $this->forceFill([
            'status' => 'failed',
            'error_message' => mb_substr($error instanceof \Throwable ? $error->getMessage() : $error, 0, 2000),
        ])->save();
    }
}
