<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

#[Fillable(['group', 'key', 'value'])]
class Setting extends Model
{
    /** Настройки на время запроса: без этого каждый Setting::get — отдельный запрос к кэшу в БД. */
    private static ?array $memo = null;

    protected static function booted(): void
    {
        static::saved(fn () => static::flush());
        static::deleted(fn () => static::flush());
    }

    public static function flush(): void
    {
        static::$memo = null;
        Cache::forget('site_settings');
    }

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        static::$memo ??= Cache::remember('site_settings', 60, fn () => static::query()->pluck('value', 'key')->all());

        return static::$memo[$key] ?? $default;
    }

    public static function put(string $key, mixed $value, string $group = 'general'): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['group' => $group, 'value' => $value],
        );

        static::flush();
    }

    /** Секрет интеграции (токен, вебхук): в базе лежит только зашифрованным ключом приложения. */
    public static function secret(string $key): ?string
    {
        $value = static::get($key);
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }

    public static function putSecret(string $key, ?string $value, string $group = 'integrations'): void
    {
        static::put($key, filled($value) ? Crypt::encryptString(trim($value)) : null, $group);
    }
}
