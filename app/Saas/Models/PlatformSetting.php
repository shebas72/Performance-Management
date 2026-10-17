<?php

namespace App\Saas\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Simple key/value settings for the platform owner (super admin): brand name, logo, favicon, colour.
 * Reads are cached and never throw, so the panel still loads before the migration has been run.
 */
class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value'];

    private const CACHE_KEY = 'platform_settings';

    public static function allValues(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());
        } catch (\Throwable) {
            return [];
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::allValues()[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }
}
