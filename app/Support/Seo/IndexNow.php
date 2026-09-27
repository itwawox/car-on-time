<?php

namespace App\Support\Seo;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * IndexNow: мгновенное уведомление Яндекса и Bing о новых и изменённых страницах.
 * Адреса копятся за запрос и отправляются одним пакетом после ответа пользователю.
 * Работает только на боевом сайте и если включено в «SEO → Настройки SEO → Индексация».
 */
class IndexNow
{
    private const ENDPOINT = 'https://yandex.com/indexnow';

    /** @var array<string, true> */
    private static array $queue = [];

    private static bool $flushScheduled = false;

    public static function queue(string ...$urls): void
    {
        if (! self::enabled()) {
            return;
        }

        foreach ($urls as $url) {
            self::$queue[$url] = true;
        }

        if (! self::$flushScheduled) {
            self::$flushScheduled = true;
            app()->terminating(fn () => self::flush());
        }
    }

    public static function flush(): void
    {
        $urls = array_keys(self::$queue);
        self::$queue = [];
        self::$flushScheduled = false;

        if ($urls === []) {
            return;
        }

        try {
            Http::timeout(5)->asJson()->post(self::ENDPOINT, [
                'host' => parse_url((string) config('app.url'), PHP_URL_HOST),
                'key' => self::key(),
                'keyLocation' => url(self::key().'.txt'),
                'urlList' => array_slice($urls, 0, 10000),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public static function enabled(): bool
    {
        return app()->isProduction() && (bool) (SeoSettings::indexing()['indexnow_enabled'] ?? false);
    }

    /** Ключ создаётся один раз и хранится в настройках SEO. */
    public static function key(): string
    {
        $indexing = SeoSettings::indexing();
        if (blank($indexing['indexnow_key'] ?? null)) {
            $indexing['indexnow_key'] = Str::lower(Str::random(32));
            Setting::put('seo_indexing', $indexing, SeoSettings::GROUP);
        }

        return (string) $indexing['indexnow_key'];
    }
}
