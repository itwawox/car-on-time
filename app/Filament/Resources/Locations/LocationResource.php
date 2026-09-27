<?php

namespace App\Filament\Resources\Locations;

use App\Filament\Concerns\RestrictedToArea;
use App\Filament\Resources\Locations\Pages\CreateLocation;
use App\Filament\Resources\Locations\Pages\EditLocation;
use App\Filament\Resources\Locations\Pages\ListLocations;
use App\Models\Location;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LocationResource extends Resource
{
    use RestrictedToArea;

    protected static ?string $model = Location::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|\UnitEnum|null $navigationGroup = 'Справочники';

    protected static ?string $navigationLabel = 'Локации';

    protected static ?string $modelLabel = 'Локации';

    protected static ?string $pluralModelLabel = 'Локации';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug'),
                TextInput::make('short_name'),
                TextInput::make('type')
                    ->required()
                    ->default('city'),
                TextInput::make('hours_from'),
                TextInput::make('hours_to'),
                TextInput::make('price_1_day')
                    ->numeric(),
                TextInput::make('price_2_days')
                    ->numeric(),
                TextInput::make('price_3plus')
                    ->numeric(),
                TextInput::make('night_price')
                    ->numeric()
                    ->prefix('$'),
                Toggle::make('is_default_pickup')
                    ->required(),
                Toggle::make('seo_enabled')
                    ->required(),
                TextInput::make('seo_title'),
                Textarea::make('seo_description')
                    ->columnSpanFull(),
                Textarea::make('intro')
                    ->columnSpanFull(),
                TextInput::make('sort')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('short_name')
                    ->searchable(),
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('hours_from')
                    ->searchable(),
                TextColumn::make('hours_to')
                    ->searchable(),
                TextColumn::make('price_1_day')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('price_2_days')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('price_3plus')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('night_price')
                    ->money()
                    ->sortable(),
                IconColumn::make('is_default_pickup')
                    ->boolean(),
                IconColumn::make('seo_enabled')
                    ->boolean(),
                TextColumn::make('seo_title')
                    ->searchable(),
                TextColumn::make('sort')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLocations::route('/'),
            'create' => CreateLocation::route('/create'),
            'edit' => EditLocation::route('/{record}/edit'),
        ];
    }

    protected static function accessArea(): string
    {
        return 'fleet';
    }
}
