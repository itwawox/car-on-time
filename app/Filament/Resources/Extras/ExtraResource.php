<?php

namespace App\Filament\Resources\Extras;

use App\Filament\Concerns\RestrictedToArea;
use App\Filament\Resources\Extras\Pages\CreateExtra;
use App\Filament\Resources\Extras\Pages\EditExtra;
use App\Filament\Resources\Extras\Pages\ListExtras;
use App\Models\Extra;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExtraResource extends Resource
{
    use RestrictedToArea;

    protected static ?string $model = Extra::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'Справочники';

    protected static ?string $navigationLabel = 'Доп. услуги';

    protected static ?string $modelLabel = 'Доп. услуги';

    protected static ?string $pluralModelLabel = 'Доп. услуги';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Название')->required(),
                TextInput::make('slug')->label('Код (латиницей)')->required(),
                TextInput::make('description')->label('Пояснение для клиента')->columnSpanFull()
                    ->placeholder('Например: залог не нужен — за небольшую доплату в сутки'),
                TextInput::make('price_per_day')->label('Цена в сутки, ₽')->required()->numeric()->default(0),
                TextInput::make('sort')->label('Порядок')->required()->numeric()->default(0),
                Toggle::make('is_free')->label('Бесплатно'),
                Toggle::make('waives_deposit')->label('Снимает залог («Без залога»)')
                    ->helperText('Если клиент выбрал эту услугу, залог в расчёте становится 0 ₽.'),
                Toggle::make('is_active')->label('Показывать клиентам')->default(true),
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
                TextColumn::make('price_per_day')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')->label('Показывается')->boolean(),
                IconColumn::make('waives_deposit')->label('Без залога')->boolean(),
                IconColumn::make('is_free')
                    ->boolean(),
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
            'index' => ListExtras::route('/'),
            'create' => CreateExtra::route('/create'),
            'edit' => EditExtra::route('/{record}/edit'),
        ];
    }

    protected static function accessArea(): string
    {
        return 'fleet';
    }
}
