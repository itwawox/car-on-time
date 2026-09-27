<?php

namespace App\Support;

use App\Models\Car;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Компактный снимок всех опубликованных машин для подбора: альтернативы, значки, подробный каталог,
 * «похоже на то, что вы смотрели». Один кэш вместо сотни запросов; сбрасывается при изменении
 * машин, цен, моделей (AppServiceProvider).
 */
class Fleet
{
    private const KEY = 'fleet:v1';

    /** @var array<int, array<string, mixed>>|null */
    private static ?array $memo = null;

    /** @return array<int, array<string, mixed>> id => строка */
    public static function all(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $rows = Cache::rememberForever(self::KEY, function () {
            $rows = [];
            $cars = Car::query()->published()
                ->with(['brand', 'media', 'prices', 'classes', 'bodyType', 'carModel'])
                ->orderBy('sort')->get();

            foreach ($cars as $car) {
                $f = $car->facts();
                $rows[$car->id] = [
                    'id' => $car->id,
                    'slug' => $car->slug,
                    'name' => $car->displayName(),
                    'url' => route('car.show', $car->slug, false),
                    'thumb' => $car->coverUrl('card'),
                    'price' => $car->currentPriceFrom(),
                    'body' => $car->body_type_id,
                    'brand' => $car->brand_id,
                    'body_name' => $car->bodyType?->name,
                    'classes' => $car->classes->pluck('id')->all(),
                    'class_name' => $car->classes->first()?->name,
                    'gearbox' => $car->gearbox,
                    'drive' => $car->drivetrain,
                    'fuel' => $car->fuel,
                    'seats' => (int) $car->seats,
                    'power' => $f->power,
                    'consumption' => $f->consumption,
                    'unit' => $f->unit(),
                    'trunk' => $f->trunk,
                    'clearance' => $f->clearance,
                    'grade' => $f->grade,
                    'min_days' => (int) $car->min_days,
                    'sort' => (int) $car->sort,
                ];
            }

            return $rows;
        });

        // Стоимость 100 км — по текущим ценам топлива из настроек (они меняются чаще машин)
        $prices = [];
        foreach ($rows as &$row) {
            $grade = $row['grade'];
            $prices[$grade] ??= self::fuelPrice($grade);
            $row['per100'] = $row['consumption'] && $prices[$grade] ? (int) round($row['consumption'] * $prices[$grade]) : null;
        }

        return self::$memo = $rows;
    }

    public static function fuelPrice(?string $grade): ?float
    {
        if (! $grade) {
            return null;
        }
        $price = Setting::get('fuel_price_'.$grade);

        return is_numeric($price) && $price > 0 ? (float) $price : (isset(CarFacts::DEFAULT_PRICES[$grade]) ? (float) CarFacts::DEFAULT_PRICES[$grade] : null);
    }

    /** @return array<string, mixed>|null */
    public static function get(int $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    public static function forget(): void
    {
        self::$memo = null;
        Cache::forget(self::KEY);
    }
}
