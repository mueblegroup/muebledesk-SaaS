<?php

namespace App\Models;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class Setting extends Model
{
    use HasFactory;

    public const SENSITIVE_KEYS = [
        'hitpay_api_key',
        'hitpay_salt',
        'hitpay_webhook_salt',
        'stripe_secret_key',
        'stripe_webhook_secret',
    ];

    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null)
    {
        return Cache::rememberForever("setting.{$key}", function () use ($key, $default) {
            $value = static::query()->where('key', $key)->value('value');

            if ($value === null) {
                return $default;
            }

            return static::decodeValue($key, (string) $value);
        });
    }

    public static function set(string $key, $value): bool
    {
        $oldValue = static::get($key);
        $newValue = is_null($value) ? '' : (string) $value;
        $storedValue = static::encodeValue($key, $newValue);

        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $storedValue]
        );

        Cache::forget("setting.{$key}");
        Cache::forget('settings.all');

        if ((string) $oldValue !== $newValue) {
            app(ActivityLogger::class)->log(
                'settings.updated',
                'Setting updated: '.$key,
                null,
                ['key' => $key, 'value' => $oldValue],
                ['key' => $key, 'value' => $newValue]
            );
        }

        return true;
    }

    public static function allKeyed(): array
    {
        return Cache::rememberForever('settings.all', function () {
            return static::query()
                ->pluck('value', 'key')
                ->map(fn ($value, $key) => static::decodeValue((string) $key, (string) $value))
                ->toArray();
        });
    }

    public static function isSensitive(string $key): bool
    {
        return in_array($key, self::SENSITIVE_KEYS, true);
    }

    public static function encodeValue(string $key, string $value): string
    {
        if ($value === '' || ! static::isSensitive($key)) {
            return $value;
        }

        if (str_starts_with($value, 'enc:')) {
            return $value;
        }

        return 'enc:'.Crypt::encryptString($value);
    }

    public static function decodeValue(string $key, string $value): string
    {
        if ($value === '' || ! static::isSensitive($key) || ! str_starts_with($value, 'enc:')) {
            return $value;
        }

        try {
            return Crypt::decryptString(substr($value, 4));
        } catch (\Throwable $e) {
            Log::warning('Sensitive setting could not be decrypted.', ['key' => $key, 'message' => $e->getMessage()]);
            return '';
        }
    }
}
