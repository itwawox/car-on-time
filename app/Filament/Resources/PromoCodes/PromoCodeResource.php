<?php

namespace App\Filament\Resources\PromoCodes;

use App\Filament\Concerns\RestrictedToArea;
use App\Filament\Resources\PromoCodes\Pages\CreatePromoCode;
use App\Filament\Resources\PromoCodes\Pages\EditPromoCode;
use App\Filament\Resources\PromoCodes\Pages\ListPromoCodes;
use App\Models\CarClass;
use App\Models\PromoCode;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Промокоды: скидка считается сразу в расчёте на сайте и в заявке. */
class PromoCodeResource extends Resource
{
    use RestrictedToArea;

    protected static ?string $model = PromoCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|\UnitEnum|null $navigationGroup = 'Контент';

    protected static ?string $navigationLabel = 'Промокоды';

    protected static ?string $modelLabel = 'промокод';

    protected static ?string $pluralModelLabel = 'Промокоды';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextInput::make('code')->label('Код')->required()->maxLength(40)->unique(ignoreRecord: true)
                    ->placeholder('SUMMER26')->helperText('Регистр не важен: клиент может ввести summer26.'),
                Select::make('discount_type')->label('Скидка')->options(PromoCode::TYPES)->required()->default('percent')->live(),
                TextInput::make('discount_value')->label(fn (Get $get) => $get('discount_type') === 'fixed' ? 'Сумма, ₽' : 'Процент')
                    ->numeric()->required()->minValue(1)->maxValue(fn (Get $get) => $get('discount_type') === 'fixed' ? null : 100),
                TextInput::make('min_days')->label('От скольки суток')->numeric()->minValue(1),
                TextInput::make('max_uses')->label('Сколько раз можно применить')->numeric()->minValue(1)->helperText('Пусто — без ограничения. Отказы не считаются.'),
                Select::make('car_class_ids')->label('Только для классов')->multiple()
                    ->options(fn () => CarClass::query()->orderBy('name')->pluck('name', 'id'))->helperText('Пусто — для всех машин.'),
                DateTimePicker::make('starts_at')->label('Действует с')->seconds(false),
                DateTimePicker::make('ends_at')->label('Действует по')->seconds(false)->after('starts_at'),
                Select::make('promotion_id')->label('Акция на сайте')->relationship('promotion', 'title')->searchable()->preload(),
                TextInput::make('note')->label('Заметка для себя')->columnSpan(2),
                Toggle::make('is_active')->label('Включён')->default(true)->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Код')->searchable()->copyable()->weight('bold'),
                TextColumn::make('discount')->label('Скидка')->state(fn (PromoCode $record) => $record->label()),
                TextColumn::make('period')->label('Срок')
                    ->state(fn (PromoCode $record) => trim(($record->starts_at ? 'с '.$record->starts_at->format('d.m.Y') : '').($record->ends_at ? ' по '.$record->ends_at->format('d.m.Y') : '')) ?: 'Бессрочно'),
                TextColumn::make('uses')->label('Применений')
                    ->state(fn (PromoCode $record) => $record->usedCount().($record->max_uses ? ' из '.$record->max_uses : '')),
                IconColumn::make('is_active')->label('Включён')->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make()]);
    }

    protected static function accessArea(): string
    {
        return 'content';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromoCodes::route('/'),
            'create' => CreatePromoCode::route('/create'),
            'edit' => EditPromoCode::route('/{record}/edit'),
        ];
    }
}
