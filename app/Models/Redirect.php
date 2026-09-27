<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['from_path', 'to_path', 'status'])]
class Redirect extends Model
{
    /** Метки рекламы и аналитики не мешают найти правило: «/contact.php?utm_source=…» = «/contact.php». */
    private const TRACKING = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'yclid', 'gclid', 'fbclid', '_openstat', 'from'];

    protected static function booted(): void
    {
        static::saving(function (Redirect $redirect) {
            $redirect->from_path = self::normalize($redirect->from_path);
        });
        static::saved(fn () => Cache::forget('redirects:map'));
        static::deleted(fn () => Cache::forget('redirects:map'));
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function map(): array
    {
        return Cache::rememberForever('redirects:map', fn () => static::query()
            ->get(['from_path', 'to_path', 'status'])
            ->mapWithKeys(fn (Redirect $r) => [$r->from_path => [$r->to_path, in_array($r->status, [301, 302, 307, 308], true) ? $r->status : 301]])
            ->all());
    }

    /**
     * Ключи для поиска правила: сначала путь с параметрами, потом без них.
     *
     * @return list<string>
     */
    public static function candidates(string $path, array $query): array
    {
        $path = self::normalizePath($path);
        $query = array_diff_key($query, array_flip(self::TRACKING));
        ksort($query);

        return array_values(array_unique(array_filter([
            $query ? $path.'?'.http_build_query($query) : null,
            $path,
        ])));
    }

    /** «https://car-on-time.ru/booking.php?carid=2» → «/booking.php?carid=2». */
    public static function normalize(string $from): string
    {
        $parts = parse_url(trim($from));
        $path = self::normalizePath($parts['path'] ?? '/');
        parse_str($parts['query'] ?? '', $query);

        return self::candidates($path, $query)[0];
    }

    private static function normalizePath(string $path): string
    {
        $path = '/'.ltrim(rawurldecode($path), '/');

        return $path === '/' ? $path : rtrim($path, '/');
    }
}
