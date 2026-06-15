<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public    $incrementing = false;
    protected $keyType      = 'string';
    public    $timestamps   = false;    // only updated_at, managed by DB trigger

    protected $fillable = ['key', 'value'];

    /** In-process cache — avoids repeated DB hits per request. */
    private static array $cache = [];

    public static function getCached(string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, static::$cache)) {
            static::$cache[$key] = static::where('key', $key)->value('value') ?? $default;
        }
        return static::$cache[$key];
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        static::$cache[$key] = (string) $value;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        return static::getCached($key, $default ? '1' : '0') === '1';
    }

    public static function getBypassUserIds(): array
    {
        $raw = static::getCached('maintenance_bypass_users', '[]');
        return json_decode($raw, true) ?? [];
    }

    public static function clearCache(): void
    {
        static::$cache = [];
    }
}
