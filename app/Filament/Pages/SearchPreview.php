<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RestrictedToArea;
use App\Filament\Resources\Cars\CarResource;
use App\Models\Car;
use App\Support\Search\CarSearch;
use App\Support\Search\QueryIntent;
use App\Support\Search\TextNormalizer;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Песочница: админ вводит запрос и видит ровно то, что увидит посетитель,
 * плюс «как поиск понял» запрос и очки релевантности.
 */
class SearchPreview extends Page
{
    use RestrictedToArea;

    protected static ?string $navigationLabel = 'Проверить поиск';

    protected static string|UnitEnum|null $navigationGroup = 'Умный поиск';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Проверить поиск';

    protected static ?string $slug = 'search/preview';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected string $view = 'filament.pages.search-preview';

    #[Url(as: 'q')]
    public string $query = '';

    /**
     * @return array<string, mixed>|null
     */
    public function getResult(): ?array
    {
        $query = trim($this->query);
        if ($query === '') {
            return null;
        }

        $raw = Car::search($query)->raw();
        $effective = $raw['meta']['corrected'] ?? $query;
        $intent = QueryIntent::parse($effective);
        $top = array_slice($raw['results'], 0, 20);
        $cars = Car::query()->with(['prices', 'classes'])->whereIn('id', array_column($top, 'id'))->get()->keyBy('id');
        $suggest = app(CarSearch::class)->suggest($query);

        return [
            'meta' => $raw['meta'],
            'total' => $raw['total'],
            'terms' => array_map(fn ($t) => ['word' => $t, 'key' => TextNormalizer::key($t)], $intent->terms),
            'links' => [...$suggest['brands'], ...$suggest['categories']],
            'rows' => array_values(array_filter(array_map(function ($hit) use ($cars) {
                $car = $cars[$hit['id']] ?? null;

                return $car ? [
                    'name' => $car->name,
                    'url' => CarResource::getUrl('edit', ['record' => $car]),
                    'score' => $hit['score'],
                    'price' => $car->currentPriceFrom(),
                    'gearbox' => $car->gearboxLabel(),
                    'class' => $car->classes->first()?->name,
                ] : null;
            }, $top))),
        ];
    }

    protected static function accessArea(): string
    {
        return 'content';
    }
}
