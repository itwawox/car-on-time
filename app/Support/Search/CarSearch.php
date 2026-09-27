<?php

namespace App\Support\Search;

use App\Models\BodyType;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarClass;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Поиск машин для страницы результатов и подсказок в выпадающем списке.
 */
class CarSearch
{
    /**
     * @param  array{class?: ?string, kp?: ?string}  $filters
     * @return array{cars: LengthAwarePaginator, meta: array<string, mixed>}
     */
    public function page(string $query, int $page = 1, array $filters = []): array
    {
        $perPage = max(1, SearchSettings::limits()['per_page']);
        $builder = Car::search($query);
        if (! empty($filters['class'])) {
            $builder->where('class_slugs', $filters['class']);
        }
        if (! empty($filters['kp'])) {
            $builder->where('gearbox', $filters['kp']);
        }
        $raw = $builder->raw();
        $ids = array_column($raw['results'], 'id');

        $cars = new LengthAwarePaginator(
            $this->load(array_slice($ids, ($page - 1) * $perPage, $perPage)),
            $raw['total'],
            $perPage,
            $page,
            ['path' => route('search'), 'query' => array_filter(['q' => $query, ...$filters])],
        );

        return ['cars' => $cars, 'meta' => $raw['meta']];
    }

    /**
     * Данные для выпадающего списка: марки, категории и машины.
     *
     * @return array<string, mixed>
     */
    public function suggest(string $query): array
    {
        $raw = Car::search($query)->raw();
        $effective = $raw['meta']['corrected'] ?? $query;
        $keys = array_map(TextNormalizer::key(...), QueryIntent::parse($effective)->terms);

        $limits = SearchSettings::limits();
        $cars = $this->load(array_slice(array_column($raw['results'], 'id'), 0, $limits['suggest_cars']))
            ->map(fn (Car $car) => [
                'title' => $car->name,
                'url' => route('car.show', $car->slug),
                'image' => $car->coverUrl('card'),
                'price' => $car->currentPriceFrom(),
                'meta' => collect([$car->gearboxLabel(), $car->seats ? $car->seats.' мест' : null, $car->classes->first()?->name])->filter()->implode(' · '),
            ])->values();

        return [
            'query' => $query,
            'corrected' => $raw['meta']['corrected'],
            'relaxed' => $raw['meta']['relaxed'],
            'chips' => $raw['meta']['chips'],
            'total' => $raw['total'],
            'url' => route('search', ['q' => $query]),
            'brands' => $keys ? $this->matchLinks($this->brandLinks(), $keys, $limits['suggest_links']) : [],
            'categories' => $keys ? $this->matchLinks($this->categoryLinks(), $keys, $limits['suggest_links']) : [],
            'cars' => $cars,
        ];
    }

    /**
     * Популярное для пустого запроса: марки с наибольшим числом машин.
     *
     * @return list<array{title: string, url: string, count: int}>
     */
    public function popularBrands(): array
    {
        return array_map(
            fn ($l) => ['title' => $l['title'], 'url' => $l['url'], 'count' => $l['count']],
            array_slice($this->brandLinks(), 0, SearchSettings::limits()['popular_brands']),
        );
    }

    /**
     * @param  list<int|string>  $ids
     */
    private function load(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $positions = array_flip($ids);

        return Car::query()
            ->with(['brand', 'classes', 'prices', 'media'])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Car $car) => $positions[$car->id])
            ->values();
    }

    /**
     * @param  list<array{title: string, url: string, subtitle: string, terms: array<string, float>}>  $links
     * @param  list<string>  $keys
     * @return list<array{title: string, url: string, subtitle: string}>
     */
    private function matchLinks(array $links, array $keys, int $limit): array
    {
        $scored = [];
        foreach ($links as $link) {
            $best = 0.0;
            foreach ($keys as $key) {
                foreach ($link['terms'] as $term => $weight) {
                    $best = max($best, TermMatcher::similarity($key, (string) $term));
                }
            }
            if ($best >= 0.55) {
                $scored[] = ['score' => $best] + $link;
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn ($l) => ['title' => $l['title'], 'url' => $l['url'], 'subtitle' => $l['subtitle']], array_slice($scored, 0, $limit));
    }

    /**
     * @return list<array{title: string, url: string, subtitle: string, count: int, terms: array<string, float>}>
     */
    private function brandLinks(): array
    {
        return Cache::remember('search:brand-links', 600, function () {

            return Brand::query()
                ->withCount(['cars' => fn ($q) => $q->published()])
                ->whereHas('cars', fn ($q) => $q->published())
                ->orderByDesc('cars_count')
                ->get()
                ->map(fn (Brand $brand) => [
                    'title' => $brand->name,
                    'url' => route('marka', $brand->slug),
                    'subtitle' => $brand->cars_count.' авто',
                    'count' => $brand->cars_count,
                    'terms' => TermMatcher::buildTerms([
                        [$brand->name, 1.0],
                        [str_replace([',', ';'], ' ', (string) $brand->search_aliases), 1.0],
                    ]),
                ])
                ->all();
        });
    }

    /**
     * @return list<array{title: string, url: string, subtitle: string, terms: array<string, float>}>
     */
    private function categoryLinks(): array
    {
        return Cache::remember('search:category-links', 600, function () {
            $make = fn ($item, string $url, string $subtitle) => [
                'title' => $item->name,
                'url' => $url,
                'subtitle' => $subtitle,
                'terms' => TermMatcher::buildTerms([[$item->name, 1.0], [str_replace([',', ';'], ' ', (string) $item->search_aliases), 1.0]]),
            ];

            return [
                ...CarClass::query()->orderBy('sort')->get()->map(fn ($c) => $make($c, route('klass', $c->slug), 'Класс'))->all(),
                ...BodyType::query()->orderBy('sort')->get()->map(fn ($b) => $make($b, route('kuzov', $b->slug), 'Кузов'))->all(),
            ];
        });
    }

    public static function forgetLinks(): void
    {
        Cache::forget('search:brand-links');
        Cache::forget('search:category-links');
    }
}
