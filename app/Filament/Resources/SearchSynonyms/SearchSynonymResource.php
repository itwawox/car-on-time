<?php

namespace App\Filament\Resources\SearchSynonyms;

use App\Filament\Concerns\RestrictedToArea;
use App\Filament\Resources\SearchSynonyms\Pages\CreateSearchSynonym;
use App\Filament\Resources\SearchSynonyms\Pages\EditSearchSynonym;
use App\Filament\Resources\SearchSynonyms\Pages\ListSearchSynonyms;
use App\Models\SearchSynonym;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class SearchSynonymResource extends Resource
{
    use RestrictedToArea;

    protected static ?string $model = SearchSynonym::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static string|UnitEnum|null $navigationGroup = 'Умный поиск';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Синонимы слов';

    protected static ?string $modelLabel = 'синоним';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $pluralModelLabel = 'Синонимы слов';

    protected static ?string $slug = 'search/synonyms';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('term')
                    ->label('Слово из названий машин')
                    ->helperText('Как слово написано в названии: «solaris», «qashqai», «рестайлинг». Для марок, классов и кузовов синонимы задаются в их карточках.')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),
                TagsInput::make('synonyms')
                    ->label('Как его ещё пишут')
                    ->placeholder('Добавьте написание и нажмите Enter')
                    ->helperText('Транслит и опечатки поиск понимает сам («солярис» найдёт Solaris). Сюда — то, что он не угадает: «кашкай», «жук».')
                    ->splitKeys([',', 'Enter'])
                    ->required(),
                Toggle::make('is_active')
                    ->label('Включено')
                    ->default(true),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('term')
                    ->label('Слово')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('synonyms')
                    ->label('Синонимы')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                ToggleColumn::make('is_active')
                    ->label('Включено'),
                TextColumn::make('updated_at')
                    ->label('Изменено')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('term')
            ->filters([
                TernaryFilter::make('is_active')->label('Включено'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSearchSynonyms::route('/'),
            'create' => CreateSearchSynonym::route('/create'),
            'edit' => EditSearchSynonym::route('/{record}/edit'),
        ];
    }

    protected static function accessArea(): string
    {
        return 'content';
    }
}
