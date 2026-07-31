<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EInvoiceSubmission extends Model
{
    use HasFactory;

    protected $table = 'einvoice_submissions';

    protected $fillable = [
        'environment', 'submission_uid', 'status', 'document_count',
        'valid_count', 'invalid_count', 'retry_count', 'request_payload',
        'response_payload', 'failure_reason', 'submitted_at', 'completed_at',
        'created_by',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function documents()
    {
        return $this->hasMany(EInvoice::class, 'einvoice_submission_id');
    }
}