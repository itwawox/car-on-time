<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Один значок на карточку — чтобы подсказать, а не перегрузить: «Выгодно», «7 мест», «Для гор», «Экономичная».
 * Считается по снимку автопарка (Fleet), без запросов к базе.
 */
class CarBadges
{
    public const LABELS = [
        'deal' => 'Выгодно',
        'family' => 'Для семьи',
        'mountains' => 'Для гор',
        'economy' => 'Экономичная',
    ];

    /** @var array<int, ?string>|null */
    private static ?array $map = null;

    /** @return array{key: string, label: string}|null */
    public static function for(int $carId): ?array
    {
        $key = (self::$map ??= self::compute())[$carId] ?? null;

        return $key ? ['key' => $key, 'label' => (string) (Setting::get('badge_'.$key) ?: self::LABELS[$key])] : null;
    }

    public static function flush(): void
    {
        self::$map = null;
    }

    /** @return array<int, ?string> */
    private static function compute(): array
    {
        $fleet = Fleet::all();

        // Группы «класс + кузов» для «выгодно» и классы для «экономичной»
        $groups = [];
        $classes = [];
        foreach ($fleet as $row) {
            foreach ($row['classes'] as $class) {
                if ($row['price']) {
                    $groups[$class.':'.$row['body']][] = $row;
                }
                if ($row['per100'] && $row['unit'] === 'л') {
                    $classes[$class][] = $row['per100'];
                }
            }
        }
        // «Выгодно» — одна машина на группу: самая дешёвая, и заметно дешевле типичной цены группы (≤ 90% медианы)
        $deals = [];
        foreach ($groups as $rows) {
            if (count($rows) < 4) {
                continue;
            }
            usort($rows, fn ($a, $b) => [$a['price'], $a['sort']] <=> [$b['price'], $b['sort']]);
            $median = $rows[intdiv(count($rows), 2)]['price'];
            if ($rows[0]['price'] <= $median * 0.9) {
                $deals[$rows[0]['id']] = true;
            }
        }
        $economyLine = array_map(function ($values) {
            if (count($values) < 5) {
                return null;
            }
            sort($values);

            return $values[(int) floor(count($values) * 0.1)];
        }, $classes);

        $map = [];
        foreach ($fleet as $row) {
            $isDeal = isset($deals[$row['id']]);
            $isEconomy = $row['per100'] && $row['unit'] === 'л'
                && collect($row['classes'])->contains(fn ($c) => ($line = $economyLine[$c] ?? null) && $row['per100'] <= $line);

            $map[$row['id']] = match (true) {
                $isDeal => 'deal',
                $row['seats'] >= 7 => 'family',
                $row['drive'] === '4wd' && $row['clearance'] >= 200 => 'mountains',
                $isEconomy => 'economy',
                default => null,
            };
        }

        return $map;
    }
}
