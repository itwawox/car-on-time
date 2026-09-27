<?php

namespace App\Support;

use App\Models\Location;
use Illuminate\Support\Facades\Cache;

/**
 * Точки выдачи для выбора с поиском: группа (город) + уточнение + цена доставки.
 * «Алушта(АВТОВОКЗАЛ)» → группа «Алушта», уточнение «автовокзал».
 */
class Places
{
    private const KEY = 'places:v1';

    /** @return list<array{id: int, group: string, label: string, name: string, price: string, popular: bool, type: string}> */
    public static function all(): array
    {
        return Cache::rememberForever(self::KEY, function () {
            return Location::query()->where('is_active', true)->orderBy('sort')->get()
                ->map(function (Location $l) {
                    $raw = trim((string) ($l->short_name ?: $l->name));
                    if (preg_match('/^([^,(]+)[,(]\s*(.*?)\)?\s*$/u', $raw, $m)) {
                        $group = trim($m[1]);
                        $label = trim($m[2], ' )');
                    } else {
                        $group = $raw;
                        $label = '';
                    }
                    // «АВТОВОКЗАЛ» → «автовокзал», «по адресу» оставляем как есть
                    if ($label !== '' && mb_strtoupper($label) === $label) {
                        $label = mb_strtolower($label);
                    }
                    $price = (int) ($l->price_3plus ?? 0);

                    return [
                        'id' => $l->id,
                        'group' => self::ucfirst($group),
                        'label' => $label,
                        'name' => $l->name,
                        'price' => $price > 0 ? 'доставка '.number_format($price, 0, ',', "\u{00A0}")."\u{00A0}₽" : 'бесплатно',
                        'popular' => $l->is_default_pickup || $l->type === 'airport' || str_contains(mb_strtolower($raw), 'ж/д вокзал'),
                        'type' => (string) $l->type,
                    ];
                })->values()->all();
        });
    }

    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }

    private static function ucfirst(string $s): string
    {
        $s = mb_strtolower($s) === $s || mb_strtoupper($s) === $s ? mb_convert_case(mb_strtolower($s), MB_CASE_TITLE) : $s;

        return $s;
    }
}
