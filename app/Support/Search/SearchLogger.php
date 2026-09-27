<?php

namespace App\Support\Search;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Журнал запросов для админки: что ищут и что не находится.
 * Хранит только текст запроса и счётчики — без IP и прочих персональных данных.
 */
class SearchLogger
{
    public static function record(string $query, int $total, ?string $sessionId = null): void
    {
        $query = self::normalize($query);
        if (mb_strlen($query) < max(2, SearchSettings::limits()['min_query_length'])) {
            return;
        }

        // Один и тот же запрос от одного посетителя (подсказка, потом Enter) считаем один раз
        if ($sessionId !== null && ! Cache::add('search-log:'.sha1($sessionId.'|'.$query), 1, 120)) {
            return;
        }

        $now = now();
        $zero = $total === 0 ? 1 : 0;

        DB::table('search_queries')->upsert(
            [[
                'query' => $query,
                'hits' => 1,
                'last_results' => $total,
                'zero_results_count' => $zero,
                'last_searched_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['query'],
            [
                'hits' => DB::raw('hits + 1'),
                'last_results' => $total,
                'zero_results_count' => DB::raw('zero_results_count + '.$zero),
                'last_searched_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public static function normalize(string $query): string
    {
        $query = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $query) ?? ''));

        return mb_substr($query, 0, 100);
    }
}
