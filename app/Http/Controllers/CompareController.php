<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Support\Seo\SeoSettings;
use Illuminate\Http\Request;
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
            ->with(['brand', 'media', 'prices', 'classes', 'bodyType'])->get()
            ->sortBy(fn (Car $car) => $ids->search($car->id))->values();

        $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
        $rows = [
            ['tag', 'Цена', fn (Car $c) => ($p = $c->currentPriceFrom()) ? 'от '.$fmt($p).' ₽/сут.' : 'по запросу'],
            ['gearbox', 'Коробка', fn (Car $c) => $c->fuel === 'electric' ? 'Электропривод' : $c->gearboxLabel()],
            ['drive', 'Привод', fn (Car $c) => $c->drivetrainLabel()],
            ['car', 'Кузов', fn (Car $c) => $c->bodyType?->name],
            ['check', 'Класс', fn (Car $c) => $c->classes->pluck('name')->implode(', ')],
            ['seats', 'Мест', fn (Car $c) => $c->seats],
            ['fuel', 'Топливо', fn (Car $c) => $c->fuelLabel()],
            ['engine', 'Двигатель', fn (Car $c) => $c->engine],
            ['calendar', 'Год', fn (Car $c) => $c->yearsLabel()],
            ['gauge', 'Расход', fn (Car $c) => $c->consumption],
            ['wallet', 'Залог', fn (Car $c) => $c->deposit ? $fmt($c->deposit).' ₽' : null],
            ['clock', 'Аренда', fn (Car $c) => $c->min_days ? 'от '.$c->min_days.' сут.' : null],
            ['id-card', 'Возраст / стаж', fn (Car $c) => $c->min_age ? 'от '.$c->min_age.' / '.($c->min_experience ?: 0).' лет' : null],
            ['route', 'Пробег в сутки', fn (Car $c) => $c->daily_km ? $c->daily_km.' км' : null],
        ];

        $table = collect($rows)->map(function ($row) use ($cars) {
            $values = $cars->map(fn (Car $car) => filled($v = $row[2]($car)) ? (string) $v : '—')->all();

            return ['icon' => $row[0], 'label' => $row[1], 'values' => $values, 'same' => count(array_unique($values)) <= 1];
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
}
