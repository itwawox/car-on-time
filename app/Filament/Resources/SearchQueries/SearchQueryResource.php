<?php

namespace App\Filament\Resources\SearchQueries;

use App\Filament\Concerns\RestrictedToArea;
use App\Filament\Pages\SearchPreview;
use App\Filament\Resources\SearchQueries\Pages\ListSearchQueries;
use App\Models\BodyType;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarClass;
use App\Models\SearchQuery;
use App\Models\SearchSynonym;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SearchQueryResource extends Resource
{
    use RestrictedToArea;

    protected static ?string $model = SearchQuery::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = 'Умный поиск';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Что ищут';

    protected static ?string $modelLabel = 'запрос';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $pluralModelLabel = 'Что ищут на сайте';

    protected static ?string $slug = 'search/queries';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SearchQuery::query()->where('last_results', 0)->where('last_searched_at', '>=', now()->subDays(30))->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Запросов без результатов за 30 дней';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Что посетители вводят в поиск. Запросы без результатов — готовые кандидаты в синонимы. Персональные данные не хранятся.')
            ->columns([
                TextColumn::make('query')
                    ->label('Запрос')
                    ->searchable()
                    ->weight('medium')
                    ->url(fn (SearchQuery $record) => SearchPreview::getUrl(['q' => $record->query])),
                TextColumn::make('hits')
                    ->label('Сколько раз')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_results')
                    ->label('Найдено')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state) => $state === 0 ? 'danger' : 'success'),
                TextColumn::make('zero_results_count')
                    ->label('Раз без результатов')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('last_searched_at')
                    ->label('Последний раз')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('hits', 'desc')
            ->filters([
                Filter::make('zero')
                    ->label('Ничего не нашли')
                    ->query(fn (Builder $query) => $query->where('last_results', 0)),
                Filter::make('recent')
                    ->label('За 30 дней')
                    ->query(fn (Builder $query) => $query->where('last_searched_at', '>=', now()->subDays(30))),
            ])
            ->recordActions([
                Action::make('makeSynonym')
                    ->label('Сделать синонимом')
                    ->icon(Heroicon::OutlinedLink)
                    ->color('primary')
                    ->modalHeading('Научить поиск этому запросу')
                    ->modalDescription('Запрос станет синонимом выбранной марки, класса, кузова, машины или слова — и начнёт находить их на сайте сразу после сохранения.')
                    ->modalSubmitActionLabel('Сохранить синоним')
                    ->fillForm(fn (SearchQuery $record) => ['synonym' => $record->query])
                    ->schema([
                        TextInput::make('synonym')
                            ->label('Синоним')
                            ->required()
                            ->maxLength(100),
                        Select::make('target')
                            ->label('Что должно находиться')
                            ->searchable()
                            ->required()
                            ->options(fn () => self::targetOptions()),
                    ])
                    ->action(function (SearchQuery $record, array $data) {
                        $label = self::attachSynonym($data['target'], trim($data['synonym']));

                        Notification::make()
                            ->title("«{$data['synonym']}» теперь находит: {$label}")
                            ->success()
                            ->actions([
                                Action::make('check')
                                    ->label('Проверить')
                                    ->url(SearchPreview::getUrl(['q' => $data['synonym']])),
                            ])
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Удалить из журнала'),
                ]),
            ]);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function targetOptions(): array
    {
        $group = fn ($items, string $prefix) => $items->mapWithKeys(fn ($m) => ["{$prefix}:{$m->id}" => $m->name ?? $m->term])->all();

        return [
            'Марки' => $group(Brand::query()->orderBy('name')->get(['id', 'name']), 'brand'),
            'Классы' => $group(CarClass::query()->orderBy('sort')->get(['id', 'name']), 'class'),
            'Кузова' => $group(BodyType::query()->orderBy('sort')->get(['id', 'name']), 'body'),
            'Слова из названий' => $group(SearchSynonym::query()->orderBy('term')->get(['id', 'term']), 'term'),
            'Машины' => $group(Car::query()->published()->orderBy('name')->get(['id', 'name']), 'car'),
        ];
    }

    /** Добавляет синоним к выбранной сущности и возвращает её название. */
    public static function attachSynonym(string $target, string $synonym): string
    {
        [$type, $id] = explode(':', $target, 2);

        if ($type === 'term') {
            $row = SearchSynonym::query()->findOrFail($id);
            $row->update(['synonyms' => array_values(array_unique([...(array) $row->synonyms, $synonym]))]);

            return $row->term;
        }

        /** @var Model $model */
        $model = match ($type) {
            'brand' => Brand::query()->findOrFail($id),
            'class' => CarClass::query()->findOrFail($id),
            'body' => BodyType::query()->findOrFail($id),
            'car' => Car::query()->findOrFail($id),
        };

        $current = array_filter(array_map('trim', explode(',', (string) $model->search_aliases)));
        $model->update(['search_aliases' => implode(', ', array_values(array_unique([...$current, $synonym])))]);

        return $model->name;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSearchQueries::route('/'),
        ];
    }

    protected static function accessArea(): string
    {
        return 'content';
    }
}
