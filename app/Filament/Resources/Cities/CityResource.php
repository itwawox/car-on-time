<?php

namespace App\Filament\Resources\Cities;

use App\Filament\Concerns\RestrictedToArea;
use App\Filament\Forms\SeoFields;
use App\Filament\Resources\Cities\Pages\CreateCity;
use App\Filament\Resources\Cities\Pages\EditCity;
use App\Filament\Resources\Cities\Pages\ListCities;
use App\Models\City;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class CityResource extends Resource
{
    use RestrictedToArea;

    protected static ?string $model = City::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Города';

    protected static ?string $modelLabel = 'город';

    protected static ?string $pluralModelLabel = 'Города';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Город')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->label('Название')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set, ?City $record) => $record ? null : $set('slug', Str::slug((string) $state))),
                    TextInput::make('slug')
                        ->label('Адрес')
                        ->prefix('/arenda-avto-v-')
                        ->required()
                        ->alphaDash()
                        ->unique(ignoreRecord: true),
                    TextInput::make('card_subtitle')->label('Подпись на плитке главной')->placeholder('ЮБК, Встреча к рейсу…'),
                    TextInput::make('map_url')->label('Карта (ссылка виджета Яндекс.Карт)')->url()->columnSpanFull()
                        ->helperText('Необязательно. Пусто — карта ищет по названию города. Ссылку даёт «Поделиться → Виджет с картой» в Яндекс.Картах.'),
                    TextInput::make('sort')->label('Порядок')->numeric()->default(0),
                    Toggle::make('is_published')->label('Опубликовано')->default(true),
                    Toggle::make('show_on_home')->label('Показывать на главной'),
                    Toggle::make('show_in_footer')->label('Показывать в подвале'),
                ]),
            SeoFields::section(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Город')->searchable()->weight('medium'),
                TextColumn::make('slug')->label('Адрес')->formatStateUsing(fn ($state) => '/arenda-avto-v-'.$state)->color('gray'),
                TextColumn::make('seo_title')->label('Title')->limit(50)->placeholder('по шаблону')->toggleable(),
                ToggleColumn::make('is_published')->label('Опубл.'),
                ToggleColumn::make('show_on_home')->label('Главная'),
                ToggleColumn::make('show_in_footer')->label('Подвал'),
                TextColumn::make('sort')->label('Порядок')->sortable(),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->recordActions([
                Action::make('open')->label('Открыть')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->url(fn (City $r) => route('city', $r->slug), true),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCities::route('/'),
            'create' => CreateCity::route('/create'),
            'edit' => EditCity::route('/{record}/edit'),
        ];
    }

    protected static function accessArea(): string
    {
        return 'content';
    }
}
