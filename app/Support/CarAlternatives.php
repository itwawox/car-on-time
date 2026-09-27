<?php

namespace App\Support;

use App\Models\Car;
use App\Models\Setting;

/**
 * «Такая же, но…»: дешевле, мощнее, экономичнее, на автомате, с полным приводом, больше мест или багажник.
 *
 * Каждой машине автопарка ставится оценка похожести на текущую (кузов, класс, места, коробка, цена).
 * В каждом сценарии берётся самая похожая машина, которая ему подходит; одна машина — один раз.
 * Разница показывается цифрами: «−300 ₽/сут», «+40 л.с.», «−1,2 л/100 км».
 */
class CarAlternatives
{
    /** Сценарий => заголовок по умолчанию (свой — в «Настройки сайта → Подбор»). */
    public const SCENARIOS = [
        'cheaper' => 'Такая же, но дешевле',
        'stronger' => 'Мощнее',
        'economy' => 'Экономичнее',
        'automatic' => 'На автомате',
        'awd' => 'С полным приводом',
        'bigger' => 'Просторнее',
    ];

    /**
     * @return list<array{key: string, title: string, car: array<string, mixed>, deltas: list<array{text: string, good: bool}>}>
     */
    public static function for(Car $car): array
    {
        $fleet = Fleet::all();
        $me = $fleet[$car->id] ?? null;
        if (! $me || ! $me['price']) {
            return [];
        }

        $candidates = [];
        foreach ($fleet as $row) {
            if ($row['id'] === $me['id'] || ! $row['price']) {
                continue;
            }
            // Кто смотрит автомат, механику не предлагаем
            if ($me['gearbox'] === 'at' && $row['gearbox'] !== 'at') {
                continue;
            }
            $row['score'] = self::similarity($me, $row);
            $candidates[] = $row;
        }
        usort($candidates, fn ($a, $b) => [$b['score'], $a['sort']] <=> [$a['score'], $b['sort']]);

        $rules = [
            'cheaper' => fn ($c) => $c['score'] >= 5 && $c['price'] < $me['price'],
            'stronger' => fn ($c) => $c['score'] >= 4 && $me['power'] && $c['power'] >= $me['power'] * 1.15 && $c['price'] <= $me['price'] * 1.4,
            'economy' => fn ($c) => $c['score'] >= 4 && $me['consumption'] && $c['consumption'] && $c['unit'] === $me['unit']
                && $c['consumption'] <= $me['consumption'] * 0.9,
            'automatic' => fn ($c) => $me['gearbox'] === 'mt' && $c['gearbox'] === 'at' && array_intersect($c['classes'], $me['classes'])
                && $c['price'] <= $me['price'] * 1.5,
            'awd' => fn ($c) => $me['drive'] !== '4wd' && $c['drive'] === '4wd' && $c['score'] >= 3 && $c['price'] <= $me['price'] * 1.6,
            'bigger' => fn ($c) => $c['score'] >= 3 && $c['price'] <= $me['price'] * 1.5
                && ($c['seats'] > $me['seats'] || ($me['trunk'] && $c['trunk'] >= $me['trunk'] * 1.2)),
        ];

        $result = [];
        $taken = [];
        foreach ($rules as $key => $rule) {
            foreach ($candidates as $c) {
                if (isset($taken[$c['id']]) || ! $rule($c)) {
                    continue;
                }
                $taken[$c['id']] = true;
                $result[] = [
                    'key' => $key,
                    'title' => (string) (Setting::get('alt_title_'.$key) ?: self::SCENARIOS[$key]),
                    'car' => $c,
                    'deltas' => self::deltas($key, $me, $c),
                ];
                break;
            }
        }

        return $result;
    }

    /** Похожесть: кузов +3, класс +2, места ±1 +1, та же коробка +1, цена в пределах 30% +2. */
    public static function similarity(array $a, array $b): int
    {
        $score = 0;
        $score += $a['body'] && $a['body'] === $b['body'] ? 3 : 0;
        $score += array_intersect($a['classes'], $b['classes']) ? 2 : 0;
        $score += abs($a['seats'] - $b['seats']) <= 1 ? 1 : 0;
        $score += $a['gearbox'] === $b['gearbox'] ? 1 : 0;
        $score += $a['price'] && $b['price'] && abs($a['price'] - $b['price']) / $a['price'] <= 0.3 ? 2 : 0;

        return $score;
    }

    /**
     * Плашки разницы: главная по сценарию первой, затем цена.
     *
     * @return list<array{text: string, good: bool}>
     */
    private static function deltas(string $key, array $me, array $c): array
    {
        $fmt = fn (int $n) => number_format(abs($n), 0, ',', ' ');
        $out = [];

        $main = match ($key) {
            'stronger' => $c['power'] ? ['+'.($c['power'] - $me['power']).' л.с.', true] : null,
            'economy' => ['−'.CarFacts::num($me['consumption'] - $c['consumption']).' '.$c['unit'].'/100 км', true],
            'automatic' => ['Автомат', true],
            'awd' => ['Полный привод', true],
            'bigger' => $c['seats'] > $me['seats']
                ? [$c['seats'].' мест', true]
                : ['+'.($c['trunk'] - $me['trunk']).' л багажник', true],
            default => null,
        };
        if ($main) {
            $out[] = ['text' => $main[0], 'good' => $main[1]];
        }

        $diff = $c['price'] - $me['price'];
        if ($diff !== 0) {
            $out[] = ['text' => ($diff < 0 ? '−' : '+').$fmt($diff).' ₽/сут', 'good' => $diff < 0];
        } else {
            $out[] = ['text' => 'та же цена', 'good' => true];
        }

        // Для «дешевле» полезно знать, что теряем или выигрываем в мощности
        if ($key === 'cheaper' && $me['power'] && $c['power'] && abs($c['power'] - $me['power']) >= 10) {
            $d = $c['power'] - $me['power'];
            $out[] = ['text' => ($d > 0 ? '+' : '−').abs($d).' л.с.', 'good' => $d > 0];
        }

        return $out;
    }
}
