<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id','company_subscription_id','platform_subscription_plan_id','provider',
        'provider_payment_id','provider_invoice_id','provider_customer_id','status',
        'amount','currency','description','failure_message','paid_at','failed_at','metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function subscription(): BelongsTo { return $this->belongsTo(CompanySubscription::class, 'company_subscription_id'); }
    public function plan(): BelongsTo { return $this->belongsTo(PlatformSubscriptionPlan::class, 'platform_subscription_plan_id'); }
}
