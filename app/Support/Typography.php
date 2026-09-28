<?php

namespace App\Support;

/**
 * Русская типографика для заголовков: короткий предлог или союз не остаётся в конце строки,
 * а переносится вместе со следующим словом («без предоплаты», а не «без / предоплаты»).
 */
class Typography
{
    private const SHORT_WORDS = 'в|во|на|без|с|со|к|ко|по|от|до|за|для|из|о|об|у|и|а|не';

    public static function nbsp(string $text): string
    {
        return (string) preg_replace('/(?<=^|\s)('.self::SHORT_WORDS.') (?=\S)/iu', "$1\u{00A0}", $text);
    }
}
