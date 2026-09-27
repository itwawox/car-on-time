<?php

namespace App\Support;

use App\Models\BodyType;
use App\Models\CarClass;

/**
 * Подбор по ответам квиза на данных автопарка (Fleet): жёсткие условия (места, коробка, бюджет)
 * + баллы за соответствие задачам поездки. У каждой машины — процент совпадения и понятные причины.
 * Если жёсткие условия не дают ни одной машины, по очереди ослабляем их (бюджет → коробку) и честно пишем об этом.
 */
class QuizMatcher
{
    /**
     * @return array{best: ?array, cheaper: ?array, comfort: ?array, count: int, relaxed: list<string>, filters: array<string, mixed>}
     */
    public static function match(array $a): array
    {
        $rows = self::scored($a, $relaxed);
        if (! $rows) {
            return ['best' => null, 'cheaper' => null, 'comfort' => null, 'count' => 0, 'relaxed' => $relaxed, 'filters' => self::catalogFilters($a, $relaxed)];
        }

        // При равных баллах: для «комфорта» и «эмоций» — мощнее, иначе — дешевле
        $premium = in_array($a['priority'] ?? null, ['comfort', 'fun'], true) || ($a['who'] ?? null) === 'business';
        usort($rows, fn ($x, $y) => $premium
            ? [$y['score'], $y['class_rank'], $y['power'] ?? 0] <=> [$x['score'], $x['class_rank'], $x['power'] ?? 0]
            : [$y['score'], $x['price']] <=> [$x['score'], $y['price']]);
        $best = $rows[0];

        // «Дешевле» и «Комфортнее» — лучшие по баллам среди подходящих машин заметно другой цены
        $cheaper = collect($rows)->first(fn ($r) => $r['id'] !== $best['id'] && $r['price'] <= $best['price'] * 0.88 && $r['score'] >= $best['score'] - 35);
        $comfort = collect($rows)->first(fn ($r) => $r['id'] !== $best['id'] && $r['price'] >= $best['price'] * 1.12
            && ($r['class_rank'] > $best['class_rank'] || ($r['power'] ?? 0) > ($best['power'] ?? 0) * 1.1) && $r['score'] >= $best['score'] - 35);

        return [
            'best' => $best,
            'cheaper' => $cheaper,
            'comfort' => $comfort,
            'count' => count($rows),
            'relaxed' => $relaxed,
            'filters' => self::catalogFilters($a, $relaxed),
        ];
    }

    /** Сколько машин подходит под уже данные ответы — для счётчика «Подходит N машин». */
    public static function count(array $a): int
    {
        return count(self::candidates($a, false, false));
    }

    /** @return list<array<string, mixed>> */
    private static function scored(array $a, ?array &$relaxed = []): array
    {
        $relaxed = [];
        $rows = self::candidates($a, true, true);
        if (! $rows && isset($a['budget']) && $a['budget'] !== 'any') {
            $relaxed[] = 'budget';
            $rows = self::candidates($a, false, true);
        }
        if (! $rows && ($a['gearbox'] ?? 'any') !== 'any') {
            $relaxed[] = 'gearbox';
            $rows = self::candidates($a, false, false);
        }

        $max = 0;
        foreach ($rows as &$row) {
            [$row['score'], $row['reasons']] = self::score($row, $a);
            $max = max($max, $row['score']);
        }
        unset($row);
        // Процент совпадения: лучшая машина = не больше 98%, остальные — относительно неё
        foreach ($rows as &$row) {
            $row['match'] = $max > 0 ? max(40, min(98, (int) round($row['score'] / $max * 96))) : 70;
        }

        return $rows;
    }

    /** Жёсткие условия. @return list<array<string, mixed>> */
    private static function candidates(array $a, bool $withBudget, bool $withGearbox): array
    {
        $maps = self::maps();
        $budgetMax = self::budgetMax($a['budget'] ?? 'any');
        $trips = (array) ($a['trip'] ?? []);

        $out = [];
        foreach (Fleet::all() as $row) {
            if (! $row['price']) {
                continue;
            }
            $row['body_slug'] = $maps['body'][$row['body']] ?? null;
            $row['class_slugs'] = array_values(array_filter(array_map(fn ($id) => $maps['class'][$id] ?? null, $row['classes'])));
            $row['class_rank'] = max(array_map(fn ($s) => ['ekonom' => 1, 'srednij' => 2, 'biznes' => 3][$s] ?? 0, $row['class_slugs'] ?: ['']));

            if (($a['who'] ?? null) === 'group' && $row['seats'] < 6) {
                continue;
            }
            // Деловая поездка — без эконом-класса
            if (($a['who'] ?? null) === 'business' && $row['class_rank'] < 2) {
                continue;
            }
            if (($a['who'] ?? null) === 'family' && $row['seats'] < 5) {
                continue;
            }
            if (($a['luggage'] ?? null) === 'lots' && $row['trunk'] && $row['trunk'] < 380 && $row['seats'] < 7) {
                continue;
            }
            if (in_array('mountains', $trips, true) && $row['drive'] !== '4wd' && ($row['clearance'] ?? 0) < 180) {
                continue;
            }
            if ($withGearbox && in_array($a['gearbox'] ?? 'any', ['at', 'mt'], true) && $row['gearbox'] !== $a['gearbox']) {
                continue;
            }
            if ($withBudget && $budgetMax && $row['price'] > $budgetMax) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    /** Баллы и причины «почему подходит». @return array{0: int, 1: list<string>} */
    private static function score(array $r, array $a): array
    {
        $s = 50;
        $why = [];
        $trips = (array) ($a['trip'] ?? []);
        $body = $r['body_slug'];
        $per100 = $r['per100'];

        switch ($a['who'] ?? null) {
            case 'family':
                if (in_array($body, ['krossover', 'miniven', 'universal', 'liftbek', 'vnedorozhnik'], true)) {
                    $s += 14;
                    $why[] = 'Просторный кузов: '.mb_strtolower((string) $r['body_name']);
                }
                if (($r['trunk'] ?? 0) >= 450) {
                    $s += 8;
                }
                if (in_array($body, ['kupe', 'kabriolet'], true)) {
                    $s -= 25;
                }
                break;
            case 'group':
                $s += min(20, ($r['seats'] - 5) * 7);
                $why[] = $r['seats'].' мест — вся компания в одной машине';
                break;
            case 'business':
                if (in_array('biznes', $r['class_slugs'], true)) {
                    $s += 28;
                    $why[] = 'Бизнес-класс';
                } elseif (in_array('srednij', $r['class_slugs'], true)) {
                    $s += 8;
                }
                if (in_array($body, ['sedan', 'liftbek', 'vnedorozhnik', 'krossover'], true)) {
                    $s += 6;
                }
                if ($r['class_rank'] <= 1) {
                    $s -= 20;
                }
                break;
            case 'solo':
                if (in_array($body, ['hetchbek', 'sedan', 'kupe', 'kabriolet', 'liftbek'], true)) {
                    $s += 6;
                }
                break;
        }

        $prices = self::priceStats();
        $rel = $prices['median'] ? $r['price'] / $prices['median'] : 1;
        switch ($a['priority'] ?? null) {
            case 'save':
                $s += (int) round(max(-20, min(24, (1 - $rel) * 40)));
                if ($per100 && $r['unit'] === 'л' && $per100 <= 450) {
                    $s += 6;
                }
                if ($rel <= 0.85) {
                    $why[] = 'Одна из самых доступных: от '.Quiz::money($r['price']).' в сутки';
                }
                break;
            case 'balance':
                $s += (int) round(14 - abs($rel - 1) * 20);
                if ($r['class_rank'] === 2) {
                    $s += 6;
                    $why[] = 'Средний класс — разумная цена и удобство';
                }
                break;
            case 'comfort':
                $s += $r['class_rank'] * 8 - 8 + (int) min(12, ($r['power'] ?? 0) / 25);
                if (($r['power'] ?? 0) >= 180) {
                    $s += 8;
                    $why[] = $r['power'].' л.с. — легко обгоняет';
                }
                if ($r['class_rank'] <= 1) {
                    $s -= 15;
                }
                break;
            case 'fun':
                if (in_array($body, ['kabriolet', 'kupe'], true)) {
                    $s += 30;
                    $why[] = mb_convert_case(mb_substr((string) $r['body_name'], 0, 1), MB_CASE_UPPER).mb_substr(mb_strtolower((string) $r['body_name']), 1).' — главное впечатление отпуска';
                }
                if (($r['power'] ?? 0) >= 200) {
                    $s += 12;
                    $why[] = $r['power'].' л.с.';
                }
                break;
        }

        if (in_array('city', $trips, true)) {
            if (in_array($body, ['hetchbek', 'sedan', 'liftbek'], true) || ($r['seats'] <= 5 && $r['class_rank'] <= 2)) {
                $s += 5;
            }
            if ($r['gearbox'] === 'at') {
                $s += 4;
            }
        }
        if (in_array('coast', $trips, true)) {
            if ($r['gearbox'] === 'at') {
                $s += 8;
                $why[] = 'Автомат — удобно на серпантинах и в пробках';
            }
            if (($r['power'] ?? 0) >= 120) {
                $s += 6;
            }
        }
        if (in_array('mountains', $trips, true)) {
            // Для гор это главные причины — ставим их сразу после «кто едет»
            $mountainWhy = [];
            if ($r['drive'] === '4wd') {
                $s += 16;
                $mountainWhy[] = 'Полный привод';
            }
            if (($r['clearance'] ?? 0) >= 190) {
                $s += 10;
                $mountainWhy[] = 'Клиренс '.$r['clearance'].' мм — не боится грунтовок';
            }
            array_splice($why, min(1, count($why)), 0, $mountainWhy);
        }
        if (in_array('long', $trips, true)) {
            if ($per100 && $r['unit'] === 'л' && $per100 <= 480) {
                $s += 10;
                $why[] = '≈ '.Quiz::money($per100).' на 100 км';
            }
            if ($r['class_rank'] >= 2) {
                $s += 4;
            }
        }

        $trunk = $r['trunk'] ?? 0;
        switch ($a['luggage'] ?? null) {
            case 'suitcases':
                if ($trunk >= 400) {
                    $s += 6;
                } elseif ($trunk && $trunk < 330) {
                    $s -= 10;
                }
                break;
            case 'lots':
                if ($trunk >= 500 || $r['seats'] >= 7) {
                    $s += 12;
                    $why[] = $trunk ? 'Багажник '.$trunk.' л' : 'Много места для вещей';
                }
                break;
        }

        if (($a['gearbox'] ?? 'any') === 'at' && $r['gearbox'] === 'at' && ! in_array('coast', $trips, true)) {
            $why[] = 'Автомат';
        }

        // Бюджет «не важно» + комфорт: не штрафуем дорогие
        $budgetMax = self::budgetMax($a['budget'] ?? 'any');
        if ($budgetMax && $r['price'] <= $budgetMax) {
            $s += 4;
        }

        $why = array_values(array_unique($why));
        if (count($why) < 2) {
            $why[] = $r['gearbox'] === 'mt' ? 'Механика — дешевле автомата' : 'Автомат';
            if ($r['seats']) {
                $why[] = $r['seats'].' мест';
            }
        }

        return [max(1, $s), array_slice(array_values(array_unique($why)), 0, 3)];
    }

    /** Фильтры каталога под ответы — ссылка «Все подходящие машины». @return array<string, mixed> */
    public static function catalogFilters(array $a, array $relaxed = []): array
    {
        $f = [];
        if (! in_array('gearbox', $relaxed, true) && in_array($a['gearbox'] ?? 'any', ['at', 'mt'], true)) {
            $f['kp'] = $a['gearbox'];
        }
        if (($a['who'] ?? null) === 'group') {
            $f['seats'] = 7;
        }
        if (in_array('mountains', (array) ($a['trip'] ?? []), true)) {
            $f['awd'] = 1;
        }
        if (! in_array('budget', $relaxed, true) && ($max = self::budgetMax($a['budget'] ?? 'any'))) {
            $f['price_max'] = $max;
        }
        if (($a['who'] ?? null) === 'business' || ($a['priority'] ?? null) === 'comfort') {
            $f['class'] = ['biznes'];
        }

        return $f;
    }

    private static function budgetMax(string $key): ?int
    {
        $b = Quiz::budgets();

        return ['b1' => $b[0], 'b2' => $b[1], 'b3' => $b[2]][$key] ?? null;
    }

    /** @return array{median: int} */
    private static function priceStats(): array
    {
        $p = collect(Fleet::all())->pluck('price')->filter()->sort()->values();

        return ['median' => $p->isEmpty() ? 0 : (int) $p[intdiv($p->count(), 2)]];
    }

    /** @return array{body: array<int, string>, class: array<int, string>} */
    private static function maps(): array
    {
        return [
            'body' => BodyType::query()->pluck('slug', 'id')->all(),
            'class' => CarClass::query()->pluck('slug', 'id')->all(),
        ];
    }
}
