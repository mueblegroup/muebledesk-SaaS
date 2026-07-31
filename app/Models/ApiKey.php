<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    public const AVAILABLE_PERMISSIONS = [
        'clients.read', 'clients.write', 'clients.delete',
        'quotations.read', 'quotations.write', 'quotations.delete',
        'invoices.read', 'invoices.write', 'invoices.delete',
        'payments.read', 'payments.write', 'payments.delete',
        'expenses.read', 'expenses.write', 'expenses.delete', 'reports.profit_loss',
        'recurring_invoices.read', 'recurring_invoices.write', 'recurring_invoices.delete',
        'users.read', 'users.write', 'settings.read',
        'activity_logs.read',
    ];

    protected $fillable = [
        'name',
        'key_hash',
        'key_prefix',
        'user_id',
        'permissions',
        'allowed_ips',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'allowed_ips' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public static function generatePlainTextKey(): string
    {
        return 'mdk_'.Str::random(48);
    }

    public static function hashKey(string $plainTextKey): string
    {
        return hash('sha256', $plainTextKey);
    }

    public static function prefixFor(string $plainTextKey): string
    {
        return substr($plainTextKey, 0, 12).'...';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(?string $ip = null): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        $allowedIps = $this->allowed_ips ?: [];
        if ($ip && count($allowedIps) > 0 && ! in_array($ip, $allowedIps, true)) {
            return false;
        }

        return true;
    }

    public function canAccess(string $permission): bool
    {
        $permissions = $this->permissions ?: [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
}
