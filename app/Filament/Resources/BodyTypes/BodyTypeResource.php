<?php

namespace App\Filament\Resources\BodyTypes;

use App\Filament\Concerns\RestrictedToArea;
use App\Filament\Forms\SearchAliasesInput;
use App\Filament\Forms\SeoFields;
use App\Filament\Resources\BodyTypes\Pages\CreateBodyType;
use App\Filament\Resources\BodyTypes\Pages\EditBodyType;
use App\Filament\Resources\BodyTypes\Pages\ListBodyTypes;
use App\Models\BodyType;
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

class BodyTypeResource extends Resource
{
    use RestrictedToArea;

    protected static ?string $model = BodyType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|\UnitEnum|null $navigationGroup = 'Справочники';

    protected static ?string $navigationLabel = 'Кузов';

    protected static ?string $modelLabel = 'Кузов';

    protected static ?string $pluralModelLabel = 'Кузов';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                SearchAliasesInput::make('Какими словами ищут этот кузов: «паркетник», «suv», «микроавтобус».'),
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
            'index' => ListBodyTypes::route('/'),
            'create' => CreateBodyType::route('/create'),
            'edit' => EditBodyType::route('/{record}/edit'),
        ];
    }

    protected static function accessArea(): string
    {
        return 'fleet';
    }
}
