<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Small key/value store for settings admins change inside the app (2026-10-09).
 * Values are JSON. Reads are cached; writes clear the cache.
 *
 *   AppSetting::get('intake.sections', [...defaults])
 *   AppSetting::put('intake.sections', [...])
 */
class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            // A missing table (migration not run yet) falls back to the default and is
            // NOT cached, so the real value is picked up as soon as the table exists.
            if (! static::tableReady()) {
                return $default;
            }
            $value = Cache::rememberForever('app_setting:'.$key, function () use ($key) {
                $row = static::query()->find($key);

                return $row ? ['v' => $row->value] : ['none' => true];
            });
        } catch (\Throwable $e) {
            return $default;
        }

        return is_array($value) && array_key_exists('v', $value) ? $value['v'] : $default;
    }

    /** Checked once per request (a missing table is never cached across requests). */
    public static function tableReady(): bool
    {
        static $ready = null;

        return $ready ??= rescue(fn () => Schema::hasTable('app_settings'), false, false);
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('app_setting:'.$key);
    }
}
