<?php

namespace App\Support;

use App\Models\BodyType;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarClass;
use App\Services\Seo;
use App\Support\Seo\SeoSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Страница-листинг машин: каталог, класс, кузов, коробка, марка, город.
 *
 * Мета-теги: сначала поля самой сущности (заполненные в админке), иначе шаблон
 * из «SEO → Настройки SEO» с подстановками {name}, {count}, {price}.
 */
class CatalogListing
{
    /** Ключ => [подпись, поле снимка Fleet, по убыванию]. */
    public const SORTS = [
        'price' => ['Сначала дешевле', 'price', false],
        'power' => ['Мощнее', 'power', true],
        'economy' => ['Экономичнее', 'per100', false],
        'trunk' => ['Больше багажник', 'trunk', true],
    ];

    /** @return list<int> id машин в порядке сортировки; машины без значения — в конце. */
    public static function sortedIds(string $sort): array
    {
        [, $field, $desc] = self::SORTS[$sort];
        $rows = array_values(Fleet::all());
        usort($rows, function ($a, $b) use ($field, $desc) {
            if (($a[$field] === null) !== ($b[$field] === null)) {
                return $a[$field] === null ? 1 : -1;
            }
            $cmp = $desc ? $b[$field] <=> $a[$field] : $a[$field] <=> $b[$field];

            return $cmp ?: $a['sort'] <=> $b['sort'];
        });

        return array_column($rows, 'id');
    }

    /**
     * @param  array{type: string, name?: string, entity?: ?Model, crumb?: ?string, filters?: array<string, mixed>, scope?: ?callable}  $options
     */
    public static function render(Request $request, array $options): View|RedirectResponse
    {
        $type = $options['type'];
        $entity = $options['entity'] ?? null;

        $query = Car::query()->published()->with(['brand', 'classes', 'bodyType', 'prices', 'media']);
        if ($scope = $options['scope'] ?? null) {
            $scope($query);
        }

        // Фильтры в адресе (?kp=at&seats=7&class[]=biznes…) — рабочие, но закрыты от индекса
        $filters = CatalogFilters::fromRequest($request);
        if (($ids = CatalogFilters::ids($filters)) !== null) {
            $query->whereIn('cars.id', $ids ?: [0]);
        }

        $vars = [
            'name' => $options['name'] ?? '',
            'count' => (clone $query)->count(),
            'price' => DB::table('car_prices')->whereIn('car_id', (clone $query)->select('cars.id'))->min('price'),
        ];

        // Сортировка для тех, кто сравнивает: по цене, мощности, расходу, багажнику (адреса с ?sort — noindex)
        $sort = $request->query('sort');
        if (is_string($sort) && isset(self::SORTS[$sort])) {
            $ids = self::sortedIds($sort);
            if ($ids) {
                $query->orderByRaw('CASE cars.id '.implode(' ', array_map(fn ($id, $i) => 'WHEN '.(int) $id.' THEN '.$i, $ids, array_keys($ids))).' ELSE '.count($ids).' END');
            }
        }

        $basePath = (string) preg_replace('#/page/\d+$#', '', url()->current());
        $cars = CatalogPager::paginate($query->orderBy('sort'), $request, $basePath);
        if ($cars instanceof RedirectResponse) {
            return $cars;
        }

        $field = fn (string $attr, string $templateField) => filled($entity?->{$attr} ?? null)
            ? (string) $entity->{$attr}
            : SeoSettings::meta($type, $templateField, $vars);

        $meta = [
            // У классов и кузовов H1 хранится в поле title, у марок и городов — в h1
            'h1' => filled($entity?->h1 ?? null) ? $entity->h1
                : (filled($entity?->title ?? null) ? $entity->title : (SeoSettings::meta($type, 'h1', $vars) ?: $vars['name'])),
            'title' => $field('seo_title', 'title'),
            'description' => $field('seo_description', 'description'),
            'intro' => filled($entity?->intro ?? null) ? $entity->intro : (filled($entity?->description ?? null) ? $entity->description : SeoSettings::meta($type, 'intro', $vars)),
            'seo_text' => $entity?->seo_text ?? null,
            ...($options['filters'] ?? []),
        ];

        $crumbs = [['name' => 'Главная', 'url' => route('home')]];
        if ($crumb = $options['crumb'] ?? null) {
            if ($type !== 'city') {
                $crumbs[] = ['name' => 'Каталог', 'url' => route('catalog')];
            }
            $crumbs[] = ['name' => $crumb];
        } else {
            $crumbs[] = ['name' => 'Каталог'];
        }

        $page = $cars->currentPage();
        if ($page > 1) {
            $meta['title'] = SeoSettings::paginate($meta['title'], $page);
            $meta['h1'] = SeoSettings::paginate($meta['h1'], $page);
            $crumbs[] = ['name' => 'Страница '.$page];
        }

        $seo = app(Seo::class);

        return view('catalog', [
            'cars' => $cars,
            'filters' => $filters,
            'filterChips' => CatalogFilters::chips($filters),
            'priceRange' => [
                (int) (collect(Fleet::all())->pluck('price')->filter()->min() ?? 0),
                (int) (collect(Fleet::all())->pluck('price')->filter()->max() ?? 0),
            ],
            'classes' => CarClass::query()->orderBy('sort')->get(),
            'bodies' => BodyType::query()->orderBy('sort')->get(),
            'brands' => Brand::query()->orderBy('name')->get(),
            'meta' => $meta,
            'crumbs' => $crumbs,
            'noindex' => $request->except(['page']) !== [],
            'canonical' => $cars->url($page),
            'jsonld' => array_filter([$seo->breadcrumbs($crumbs), $seo->itemList($cars->getCollection(), $meta['h1'])]),
        ]);
    }
}
