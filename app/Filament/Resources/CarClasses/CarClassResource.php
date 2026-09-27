<?php

namespace App\Filament\Resources\CarClasses;

use App\Filament\Concerns\RestrictedToArea;
use App\Filament\Forms\SearchAliasesInput;
use App\Filament\Forms\SeoFields;
use App\Filament\Resources\CarClasses\Pages\CreateCarClass;
use App\Filament\Resources\CarClasses\Pages\EditCarClass;
use App\Filament\Resources\CarClasses\Pages\ListCarClasses;
use App\Models\CarClass;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CarClassResource extends Resource
{
    use RestrictedToArea;

    protected static ?string $model = CarClass::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|\UnitEnum|null $navigationGroup = 'Справочники';

    protected static ?string $navigationLabel = 'Классы';

    protected static ?string $modelLabel = 'Классы';

    protected static ?string $pluralModelLabel = 'Классы';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                SearchAliasesInput::make('Какими словами ищут этот класс: «премиум», «люкс», «business».'),
                TextInput::make('title'),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('seo_title'),
                Textarea::make('seo_description')
                    ->columnSpanFull(),
                TextInput::make('sort')
                    ->required()
                    ->numeric()
                    ->default(0),
                SeoFields::section(['seo_text']),
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
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('seo_title')
                    ->searchable(),
                TextColumn::make('sort')
                    ->numeric()
                    ->sortable(),
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
            'index' => ListCarClasses::route('/'),
            'create' => CreateCarClass::route('/create'),
            'edit' => EditCarClass::route('/{record}/edit'),
        ];
    }

    protected static function accessArea(): string
    {
        return 'fleet';
    }
}
