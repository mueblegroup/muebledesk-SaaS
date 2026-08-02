<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class PlatformSetting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'is_secret'];

    protected $casts = ['is_secret' => 'boolean'];

    public static function valueFor(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();
        if (! $setting || $setting->value === null) return $default;

        if ($setting->is_secret) {
            try { return Crypt::decryptString($setting->value); } catch (\Throwable) { return $default; }
        }

        return $setting->value;
    }

    public static function put(string $group, string $key, mixed $value, bool $secret = false): void
    {
        $stored = $value;
        if ($secret && filled($value)) $stored = Crypt::encryptString((string) $value);

        static::query()->updateOrCreate(['key' => $key], [
            'group' => $group,
            'value' => $stored,
            'is_secret' => $secret,
        ]);
    }
}
