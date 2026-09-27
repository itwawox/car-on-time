<?php

namespace App\Support;

/**
 * Характеристики, которые можно надёжно достать из названия машины:
 * «Toyota Camry 70(2018-2021) 2.5л», «Bmw X6 (F16) XDrive35i», «Lada Largus(7 мест)».
 */
class CarSpecs
{
    /** Полный привод, если он явно указан в названии (4WD, xDrive, 4MATIC, 4x4, quattro, ALL4). */
    public static function explicit4wd(string $name): bool
    {
        return (bool) preg_match('/4wd|xdrive|4matic|4ma\b|\b4x4\b|\bawd\b|quattro|all4/u', mb_strtolower($name));
    }

    /** @return array{0: ?int, 1: ?int} [год с, год по] */
    public static function years(string $name): array
    {
        if (preg_match('/(20\d{2})\s*-\s*(20\d{2})/u', $name, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }
        if (preg_match('/\b(20[12]\d)\b/u', $name, $m)) {
            return [(int) $m[1], null];
        }

        return [null, null];
    }

    /** «1.6л. 123л.с.» → «1.6 л, 123 л.с.», «1.4 TSI», «2.5л» → «2.5 л». */
    public static function engine(string $name): ?string
    {
        $name = mb_strtolower($name);
        if (preg_match('/(\d\.\d)\s*л\.?\s*(\d{2,3})\s*л\.с/u', $name, $m)) {
            return "{$m[1]} л, {$m[2]} л.с.";
        }
        if (preg_match('/(\d\.\d)\s*(tsi|tfsi)/u', $name, $m)) {
            return $m[1].' '.mb_strtoupper($m[2]);
        }
        if (preg_match('/\b(\d\.\d)\s*л?\b/u', $name, $m)) {
            return $m[1].' л';
        }

        return null;
    }

    public static function seats(string $name): ?int
    {
        return preg_match('/(\d)\s*мест/u', mb_strtolower($name), $m) ? (int) $m[1] : null;
    }
}
