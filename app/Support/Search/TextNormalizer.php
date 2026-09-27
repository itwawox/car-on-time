<?php

namespace App\Support\Search;

/**
 * Приводит русский, английский, транслит и «не та раскладка» к общему виду.
 *
 * Каждое слово сводится к фонетическому ключу на латинице: «Хендай», «hyundai»
 * и «хундай» дают близкие ключи, которые потом сравниваются нечётко.
 */
class TextNormalizer
{
    private const RU_TO_LAT = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    /** Латинские диграфы проверяются раньше одиночных букв. */
    private const LAT_TO_RU = [
        'shch' => 'щ', 'sch' => 'щ', 'zh' => 'ж', 'kh' => 'х', 'ch' => 'ч', 'sh' => 'ш',
        'ts' => 'ц', 'yu' => 'ю', 'ya' => 'я', 'yo' => 'ё', 'ye' => 'е', 'ee' => 'и', 'oo' => 'у',
        'ph' => 'ф', 'a' => 'а', 'b' => 'б', 'c' => 'к', 'd' => 'д', 'e' => 'е', 'f' => 'ф',
        'g' => 'г', 'h' => 'х', 'i' => 'и', 'j' => 'дж', 'k' => 'к', 'l' => 'л', 'm' => 'м',
        'n' => 'н', 'o' => 'о', 'p' => 'п', 'q' => 'к', 'r' => 'р', 's' => 'с', 't' => 'т',
        'u' => 'у', 'v' => 'в', 'w' => 'в', 'x' => 'кс', 'y' => 'й', 'z' => 'з',
    ];

    private const EN_LAYOUT = "qwertyuiop[]asdfghjkl;'zxcvbnm,.`";

    private const RU_LAYOUT = 'йцукенгшщзхъфывапролджэячсмитьбюё';

    /** Кириллица, которая выглядит как латиница: «М/T», «А/T» в названиях. */
    private const HOMOGLYPHS = [
        'а' => 'a', 'в' => 'b', 'е' => 'e', 'к' => 'k', 'м' => 'm', 'н' => 'h',
        'о' => 'o', 'р' => 'p', 'с' => 'c', 'т' => 't', 'у' => 'y', 'х' => 'x',
    ];

    /**
     * Нижний регистр, ё → е, без пунктуации. Возвращает слова.
     *
     * @return list<string>
     */
    public static function tokens(?string $text): array
    {
        $text = mb_strtolower((string) $text);
        $text = str_replace(['ё', '×'], ['е', 'x'], $text);
        // «4x4», «1.6» и «x-line» остаются одним словом
        $text = preg_replace('/(?<=\d)[.,](?=\d)/u', '', $text);
        $text = preg_replace('/(?<=\p{L})-(?=\p{L})/u', '', $text);
        $parts = preg_split('/[^\p{L}\p{N}]+/u', (string) $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_map(self::fixMixedScript(...), $parts));
    }

    /** Слово из смеси кириллицы и латиницы («мт», «a/т») приводим к латинице. */
    public static function fixMixedScript(string $token): string
    {
        if (preg_match('/\p{Cyrillic}/u', $token) && preg_match('/[a-z]/', $token)) {
            return strtr($token, self::HOMOGLYPHS);
        }

        return $token;
    }

    public static function isCyrillic(string $token): bool
    {
        return (bool) preg_match('/\p{Cyrillic}/u', $token);
    }

    public static function toLatin(string $token): string
    {
        return strtr(mb_strtolower($token), self::RU_TO_LAT);
    }

    public static function toCyrillic(string $token): string
    {
        return strtr(mb_strtolower($token), self::LAT_TO_RU);
    }

    /**
     * Фонетический ключ: одинаковый для «camry» / «камри», «hyundai» / «хундай».
     */
    public static function key(string $token): string
    {
        $t = self::isCyrillic($token) ? self::toLatin($token) : mb_strtolower($token);

        if (ctype_digit($t)) {
            return $t;
        }

        $t = strtr($t, ['kh' => 'h', 'ph' => 'f', 'ck' => 'k', 'qu' => 'kv', 'w' => 'v', 'x' => 'ks', 'q' => 'k', 'j' => 'dzh']);
        $t = preg_replace('/c(?=[eiy])/', 's', $t);
        $t = preg_replace('/c(?!h)/', 'k', $t);
        $t = strtr($t, ['y' => 'i', 'ee' => 'i', 'oo' => 'u']);
        // двойные буквы: «ferrari» = «ferari», «mersedes» = «merseedes»
        $t = preg_replace('/(.)\1+/', '$1', $t);

        return (string) $t;
    }

    /** Перевод строки, набранной не в той раскладке: «ьфяв» → «mazd», «htyj» → «рено». */
    public static function swapLayout(string $text): string
    {
        $en = preg_split('//u', self::EN_LAYOUT, -1, PREG_SPLIT_NO_EMPTY);
        $ru = preg_split('//u', self::RU_LAYOUT, -1, PREG_SPLIT_NO_EMPTY);
        $lower = mb_strtolower($text);

        $map = self::isCyrillic($lower) ? array_combine($ru, $en) : array_combine($en, $ru);

        return strtr($lower, $map);
    }

    /**
     * Дамерау-Левенштейн (перестановка соседних букв = 1 ошибка). Строки короткие.
     */
    public static function distance(string $a, string $b, int $max = 3): int
    {
        $la = strlen($a);
        $lb = strlen($b);
        if (abs($la - $lb) > $max) {
            return $max + 1;
        }

        $d = [];
        for ($i = 0; $i <= $la; $i++) {
            $d[$i][0] = $i;
        }
        for ($j = 0; $j <= $lb; $j++) {
            $d[0][$j] = $j;
        }

        for ($i = 1; $i <= $la; $i++) {
            for ($j = 1; $j <= $lb; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $d[$i][$j] = min($d[$i - 1][$j] + 1, $d[$i][$j - 1] + 1, $d[$i - 1][$j - 1] + $cost);
                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1);
                }
            }
        }

        return $d[$la][$lb];
    }
}
