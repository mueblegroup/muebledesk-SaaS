<?php

namespace App\Models;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EInvoice extends Model
{
    use HasFactory;

    protected $table = 'einvoices';

    protected $fillable = [
        'invoice_id', 'einvoice_submission_id', 'environment', 'document_type',
        'document_version', 'status', 'internal_document_number', 'submission_uid',
        'myinvois_uuid', 'long_id', 'document_hash', 'request_payload', 'response_payload',
        'validation_errors', 'failure_reason', 'correlation_id', 'submission_attempts',
        'retry_after_at', 'notified_at', 'issued_at', 'submitted_at', 'validated_at',
        'cancelled_at', 'cancellation_reason', 'cancelled_by', 'created_by',
    ];

    protected $casts = [
        'request_payload' => 'array', 'response_payload' => 'array', 'validation_errors' => 'array',
        'issued_at' => 'datetime', 'submitted_at' => 'datetime', 'validated_at' => 'datetime',
        'cancelled_at' => 'datetime', 'retry_after_at' => 'datetime', 'notified_at' => 'datetime',
    ];

    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function submission() { return $this->belongsTo(EInvoiceSubmission::class, 'einvoice_submission_id'); }
    public function cancelledByUser() { return $this->belongsTo(User::class, 'cancelled_by'); }

    public function validationUrl(): ?string
    {
        if ($this->status !== 'valid' || ! $this->myinvois_uuid || ! $this->long_id) return null;
        $portalUrl = rtrim((string) config('myinvois.environments.'.$this->environment.'.portal_url'), '/');
        return $portalUrl.'/'.$this->myinvois_uuid.'/share/'.$this->long_id;
    }

    public function cancellationDeadline(): ?\Illuminate\Support\Carbon
    {
        return $this->validated_at?->copy()->addHours((int) config('myinvois.cancellation_window_hours', 72));
    }

    public function canCancel(): bool
    {
        return $this->status === 'valid' && $this->myinvois_uuid && $this->cancellationDeadline()?->isFuture();
    }

    public function qrCodeDataUri(int $size = 220): ?string
    {
        $url = $this->validationUrl();
        if (! $url) return null;
        $renderer = new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd());
        return 'data:image/svg+xml;base64,'.base64_encode((new Writer($renderer))->writeString($url));
    }
}
