<?php

namespace App\Support;

use App\Models\BodyType;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarClass;
use Illuminate\Http\Request;

/**
 * Фильтры каталога: цена, коробка, 4WD, места, топливо, классы, кузова, марки — в любом сочетании.
 * Считаются по снимку автопарка (Fleet) без SQL: и для списка, и для живого счётчика «Показать N авто».
 */
class CatalogFilters
{
    public const KEYS = ['price_min', 'price_max', 'kp', 'awd', 'seats', 'fuel', 'class', 'body', 'brand'];

    /** @return array<string, mixed> только заполненные фильтры из запроса */
    public static function fromRequest(Request $request): array
    {
        $f = [];
        foreach (['price_min', 'price_max', 'seats'] as $key) {
            if (is_numeric($request->query($key)) && (int) $request->query($key) > 0) {
                $f[$key] = (int) $request->query($key);
            }
        }
        if (in_array($request->query('kp'), ['at', 'mt'], true)) {
            $f['kp'] = $request->query('kp');
        }
        if ($request->boolean('awd')) {
            $f['awd'] = true;
        }
        if (is_string($request->query('fuel')) && isset(Car::FUELS[$request->query('fuel')])) {
            $f['fuel'] = $request->query('fuel');
        }
        foreach (['class', 'body', 'brand'] as $key) {
            $values = array_values(array_filter((array) $request->query($key), 'is_string'));
            if ($values) {
                $f[$key] = array_slice($values, 0, 30);
            }
        }

        return $f;
    }

    /** @return list<int>|null id подходящих машин; null — фильтров нет */
    public static function ids(array $f): ?array
    {
        if (! $f) {
            return null;
        }
        $maps = self::maps();
        $classIds = array_filter(array_map(fn ($s) => $maps['class'][$s] ?? null, $f['class'] ?? []));
        $bodyIds = array_filter(array_map(fn ($s) => $maps['body'][$s] ?? null, $f['body'] ?? []));
        $brandIds = array_filter(array_map(fn ($s) => $maps['brand'][$s] ?? null, $f['brand'] ?? []));

        $ids = [];
        foreach (Fleet::all() as $row) {
            if (isset($f['price_min']) && (! $row['price'] || $row['price'] < $f['price_min'])) {
                continue;
            }
            if (isset($f['price_max']) && (! $row['price'] || $row['price'] > $f['price_max'])) {
                continue;
            }
            if (isset($f['kp']) && $row['gearbox'] !== $f['kp']) {
                continue;
            }
            if (! empty($f['awd']) && $row['drive'] !== '4wd') {
                continue;
            }
            if (isset($f['seats']) && $row['seats'] < $f['seats']) {
                continue;
            }
            if (isset($f['fuel']) && $row['fuel'] !== $f['fuel']) {
                continue;
            }
            if ($classIds && ! array_intersect($row['classes'], $classIds)) {
                continue;
            }
            if ($bodyIds && ! in_array($row['body'], $bodyIds, true)) {
                continue;
            }
            if ($brandIds && ! in_array($row['brand'] ?? null, $brandIds, true)) {
                continue;
            }
            $ids[] = $row['id'];
        }

        return $ids;
    }

    /** Подписи активных фильтров для чипов «× Автомат», «× до 3 000 ₽». @return list<array{key: string, value: ?string, label: string}> */
    public static function chips(array $f): array
    {
        $names = self::names();
        $chips = [];
        if (isset($f['price_min']) || isset($f['price_max'])) {
            $label = isset($f['price_min'], $f['price_max'])
                ? number_format($f['price_min'], 0, ',', ' ').'–'.number_format($f['price_max'], 0, ',', ' ').' ₽'
                : (isset($f['price_min']) ? 'от '.number_format($f['price_min'], 0, ',', ' ').' ₽' : 'до '.number_format($f['price_max'], 0, ',', ' ').' ₽');
            $chips[] = ['key' => 'price', 'value' => null, 'label' => $label];
        }
        if (isset($f['kp'])) {
            $chips[] = ['key' => 'kp', 'value' => null, 'label' => $f['kp'] === 'mt' ? 'Механика' : 'Автомат'];
        }
        if (! empty($f['awd'])) {
            $chips[] = ['key' => 'awd', 'value' => null, 'label' => 'Полный привод'];
        }
        if (isset($f['seats'])) {
            $chips[] = ['key' => 'seats', 'value' => null, 'label' => 'от '.$f['seats'].' мест'];
        }
        if (isset($f['fuel'])) {
            $chips[] = ['key' => 'fuel', 'value' => null, 'label' => Car::FUELS[$f['fuel']]];
        }
        foreach (['class', 'body', 'brand'] as $key) {
            foreach ($f[$key] ?? [] as $slug) {
                if (isset($names[$key][$slug])) {
                    $chips[] = ['key' => $key, 'value' => $slug, 'label' => $names[$key][$slug]];
                }
            }
        }

        return $chips;
    }

    /** @return array{class: array<string,int>, body: array<string,int>, brand: array<string,int>} */
    private static function maps(): array
    {
        return [
            'class' => CarClass::query()->pluck('id', 'slug')->all(),
            'body' => BodyType::query()->pluck('id', 'slug')->all(),
            'brand' => Brand::query()->pluck('id', 'slug')->all(),
        ];
    }

    /** @return array{class: array<string,string>, body: array<string,string>, brand: array<string,string>} */
    private static function names(): array
    {
        return [
            'class' => CarClass::query()->pluck('name', 'slug')->all(),
            'body' => BodyType::query()->pluck('name', 'slug')->all(),
            'brand' => Brand::query()->pluck('name', 'slug')->all(),
        ];
    }
}
