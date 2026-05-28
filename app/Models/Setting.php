<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            return cache()->remember("setting:{$key}", 3600, function () use ($key, $default) {
                try {
                    $val = static::query()->where('key', $key)->value('value');
                } catch (\Throwable) {
                    return $default;
                }

                return $val ?? $default;
            });
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $val = static::get($key, $default ? 'true' : 'false');

        if ($val === null) {
            return $default;
        }

        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        cache()->forget("setting:{$key}");
    }
}
