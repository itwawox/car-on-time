<?php

namespace App\Filament\Widgets;

use App\Models\SearchQuery;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Что ищут и не находят: подсказка, какие машины добавить или какие синонимы завести. */
class ZeroResultSearches extends TableWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = 'Ищут, но не находят';

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->canUseArea('content') || $user->canUseArea('fleet'));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(SearchQuery::query()->where('last_results', 0)->where('last_searched_at', '>=', now()->subDays(30))->orderByDesc('hits'))
            ->columns([
                TextColumn::make('query')->label('Запрос'),
                TextColumn::make('hits')->label('Раз'),
                TextColumn::make('last_searched_at')->label('Последний')->since(),
            ])
            ->paginated([5])
            ->emptyStateHeading('Всё находится');
    }
}
