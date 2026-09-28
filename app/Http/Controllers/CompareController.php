<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Support\CarFacts;
use App\Support\Seo\SeoSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Сравнение до трёх машин. Список хранится в браузере (localStorage), страница получает его в ?ids=.
 * Страница закрыта от индексации: у каждого набора свой адрес, а содержимое дублирует карточки.
 */
class CompareController extends Controller
{
    public const LIMIT = 3;

    public function __invoke(Request $request): View
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->map(fn ($id) => (int) $id)->filter()->unique()->take(self::LIMIT)->values();

        $cars = Car::query()->published()->whereIn('id', $ids)
            ->with(['brand', 'media', 'prices', 'classes', 'bodyType', 'carModel'])->get()
            ->sortBy(fn (Car $car) => $ids->search($car->id))->values();

        $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
        $num = fn (?float $n) => $n === null ? null : str_replace('.', ',', (string) round($n, 1));
        $facts = $cars->mapWithKeys(fn (Car $c) => [$c->id => CarFacts::of($c)]);

        // [иконка, подпись, значение, число для полоски, лучше — меньше (lower) или больше (higher)]
        $rows = [
            ['tag', 'Цена', fn (Car $c) => ($p = $c->currentPriceFrom()) ? 'от '.$fmt($p).' ₽/сут.' : 'по запросу', fn (Car $c) => $c->currentPriceFrom(), 'lower'],
            ['gearbox', 'Коробка', fn (Car $c) => $c->fuel === 'electric' ? 'Электропривод' : $c->gearboxLabel()],
            ['drive', 'Привод', fn (Car $c) => $c->drivetrainLabel()],
            ['car', 'Кузов', fn (Car $c) => $c->bodyType?->name],
            ['check', 'Класс', fn (Car $c) => $c->classes->pluck('name')->implode(', ')],
            ['seats', 'Мест', fn (Car $c) => $c->seats, fn (Car $c) => $c->seats, 'higher'],
            ['fuel', 'Топливо', fn (Car $c) => $c->fuelLabel()],
            ['engine', 'Двигатель', fn (Car $c) => $c->engine],
            ['gauge', 'Мощность', fn (Car $c) => ($p = $facts[$c->id]->power) ? '≈ '.$p.' л.с.' : null, fn (Car $c) => $facts[$c->id]->power, 'higher'],
            ['fuel', 'Расход', fn (Car $c) => ($v = $facts[$c->id]->consumption) ? '≈ '.$num($v).' л/100 км' : $c->consumption, fn (Car $c) => $facts[$c->id]->consumption, 'lower'],
            ['bag', 'Багажник', fn (Car $c) => ($v = $facts[$c->id]->trunk) ? '≈ '.$v.' л' : null, fn (Car $c) => $facts[$c->id]->trunk, 'higher'],
            ['mountain', 'Клиренс', fn (Car $c) => ($v = $facts[$c->id]->clearance) ? '≈ '.$v.' мм' : null, fn (Car $c) => $facts[$c->id]->clearance, 'higher'],
            ['calendar', 'Год', fn (Car $c) => $c->yearsLabel()],
            ['wallet', 'Залог', fn (Car $c) => $c->deposit ? $fmt($c->deposit).' ₽' : null, fn (Car $c) => $c->deposit, 'lower'],
            ['clock', 'Аренда', fn (Car $c) => $c->min_days ? 'от '.$c->min_days.' сут.' : null],
            ['id-card', 'Возраст / стаж', fn (Car $c) => $c->min_age ? 'от '.$c->min_age.' / '.($c->min_experience ?: 0).' лет' : null],
            ['route', 'Пробег в сутки', fn (Car $c) => $c->daily_km ? $c->daily_km.' км' : null, fn (Car $c) => $c->daily_km, 'higher'],
        ];

        $table = collect($rows)->map(function ($row) use ($cars) {
            $values = $cars->map(fn (Car $car) => filled($v = $row[2]($car)) ? (string) $v : '—')->all();

            return [
                'icon' => $row[0], 'label' => $row[1], 'values' => $values, 'same' => count(array_unique($values)) <= 1,
                ...self::bars($cars, $row[3] ?? null, $row[4] ?? null),
            ];
        })->reject(fn ($row) => collect($row['values'])->every(fn ($v) => $v === '—'))->values();

        return view('compare', [
            'cars' => $cars,
            'table' => $table,
            'title' => SeoSettings::meta('compare', 'title'),
            'description' => SeoSettings::meta('compare', 'description'),
            'noindex' => true,
            'crumbs' => [
                ['name' => 'Главная', 'url' => route('home')],
                ['name' => 'Каталог', 'url' => route('catalog')],
                ['name' => SeoSettings::meta('compare', 'h1')],
            ],
        ]);
    }

    /**
     * Полоски разницы: длина — доля от наибольшего значения в строке, «лучше» — у одной машины,
     * если значения различаются и известны хотя бы у двух.
     *
     * @param  Collection<int, Car>  $cars
     * @return array{bars: list<?float>, best: ?int}
     */
    private static function bars(Collection $cars, ?callable $metric, ?string $better): array
    {
        if (! $metric || $cars->count() < 2) {
            return ['bars' => [], 'best' => null];
        }

        $numbers = $cars->map(fn (Car $car) => ($n = $metric($car)) ? (float) $n : null)->values();
        $known = $numbers->filter();
        // Разницы нет или сравнивать не с чем — полоски только шумят
        if ($known->count() < 2 || $known->unique()->count() < 2) {
            return ['bars' => [], 'best' => null];
        }

        $max = $known->max();
        $target = $better === 'lower' ? $known->min() : $max;
        $best = $known->filter(fn ($n) => $n === $target)->count() === 1
            ? $numbers->search($target)
            : null;

        return [
            'bars' => $numbers->map(fn (?float $n) => $n === null ? null : round($n / $max, 3))->all(),
            'best' => $best === false ? null : $best,
        ];
    }
}
