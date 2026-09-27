<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Откуда пришёл клиент: UTM-метки, метки кликов рекламы, первый источник перехода.
 * Браузер запоминает их при первом заходе (analytics.js) и передаёт с заявкой в скрытом поле utm.
 */
class Attribution
{
    public const KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'yclid', 'gclid', 'referrer', 'landing'];

    /** @return array<string, string>|null */
    public static function fromRequest(Request $request): ?array
    {
        $raw = $request->input('utm');
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($data)) {
            return null;
        }

        $clean = [];
        foreach (self::KEYS as $key) {
            $value = $data[$key] ?? null;
            if (is_string($value) && ($value = trim(strip_tags($value))) !== '') {
                $clean[$key] = mb_substr($value, 0, 255);
            }
        }

        return $clean ?: null;
    }

    /** Коротко для админки и уведомлений: «yandex / cpc / leto-2026» или «с сайта google.com». */
    public static function label(?array $utm): ?string
    {
        if (! $utm) {
            return null;
        }
        if (isset($utm['utm_source'])) {
            return implode(' / ', array_filter([$utm['utm_source'], $utm['utm_medium'] ?? null, $utm['utm_campaign'] ?? null]));
        }
        if (isset($utm['yclid'])) {
            return 'Яндекс Директ';
        }
        if (isset($utm['gclid'])) {
            return 'Google Ads';
        }
        if (isset($utm['referrer'])) {
            return 'Переход с '.(parse_url($utm['referrer'], PHP_URL_HOST) ?: $utm['referrer']);
        }

        return null;
    }
}
