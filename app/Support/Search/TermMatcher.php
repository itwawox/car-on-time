<?php

namespace App\Support\Search;

/**
 * Сравнение слова запроса со словами документа в фонетических ключах.
 */
class TermMatcher
{
    /** @var array{min: int, short: int, long: int}|null */
    private static ?array $fuzzy = null;

    public static function flush(): void
    {
        self::$fuzzy = null;
    }

    /**
     * Словарь «ключ → вес» для набора полей.
     *
     * @param  array<string, array{0: ?string, 1: float}>  $fields  поле → [текст, вес]
     * @return array<string, float>
     */
    public static function buildTerms(array $fields): array
    {
        $termAliases = SearchSettings::termAliases();
        $terms = [];

        foreach ($fields as [$text, $weight]) {
            foreach (TextNormalizer::tokens($text) as $token) {
                $variants = [$token];
                foreach ($termAliases[$token] ?? [] as $alias) {
                    $variants = array_merge($variants, TextNormalizer::tokens($alias));
                }
                // «rav4» ищется и как «rav 4», «e200» — как «e 200»
                $parts = preg_split('/(?<=\p{L})(?=\d)|(?<=\d)(?=\p{L})/u', $token) ?: [];
                if (count($parts) > 1) {
                    $variants = array_merge($variants, $parts);
                }

                foreach ($variants as $variant) {
                    $key = TextNormalizer::key($variant);
                    if ($key === '') {
                        continue;
                    }
                    $terms[$key] = max($terms[$key] ?? 0, $weight);
                }
            }
        }

        return $terms;
    }

    /**
     * Насколько слово запроса похоже на слово документа: 0 — не похоже, 1 — совпало.
     */
    public static function similarity(string $query, string $doc): float
    {
        if ($query === $doc) {
            return 1.0;
        }

        $ql = strlen($query);
        $dl = strlen($doc);

        // Цифры сравниваем строго: «4» не должно находить «40»
        if (ctype_digit($query)) {
            return 0.0;
        }

        // Ввод по мере набора: «мерс» → «mersedes»
        if ($ql >= 2 && $dl > $ql && str_starts_with($doc, $query)) {
            return 0.7 + 0.2 * ($ql / $dl);
        }

        if (self::$fuzzy === null) {
            $f = SearchSettings::fuzzy();
            self::$fuzzy = ['min' => (int) $f['min_length'], 'short' => (int) $f['max_distance_short'], 'long' => (int) $f['max_distance_long']];
        }
        $min = self::$fuzzy['min'];
        if ($ql < $min) {
            return 0.0;
        }

        $allowed = $ql >= 7 ? self::$fuzzy['long'] : self::$fuzzy['short'];

        // Опечатка во всём слове: «hundai» → «hiundai»
        $distance = TextNormalizer::distance($query, $doc, $allowed);
        if ($distance <= $allowed) {
            return 0.75 - 0.12 * $distance;
        }

        // Опечатка в начале недописанного слова: «mersd» → «mersedes»
        if ($ql >= 5 && $dl > $ql) {
            $distance = TextNormalizer::distance($query, substr($doc, 0, $ql), 1);
            if ($distance <= 1) {
                return 0.55;
            }
        }

        return 0.0;
    }
}
