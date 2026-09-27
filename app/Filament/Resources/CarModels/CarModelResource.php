<?php

namespace App\Filament\Resources\CarModels;

use App\Filament\Concerns\RestrictedToArea;
use App\Filament\Resources\CarModels\Pages\CreateCarModel;
use App\Filament\Resources\CarModels\Pages\EditCarModel;
use App\Filament\Resources\CarModels\Pages\ListCarModels;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarModel;
use App\Support\CarFacts;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class CarModelResource extends Resource
{
    use RestrictedToArea;

    protected static ?string $model = CarModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Автопарк';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Модели';

    protected static ?string $modelLabel = 'модель';

    protected static ?string $pluralModelLabel = 'Модели';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Модель')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('brand_id')->label('Марка')->relationship('brand', 'name')->required()->searchable()->preload(),
                    TextInput::make('name')->label('Модель')->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set, callable $get, ?CarModel $record) => $record ? null
                            : $set('slug', Str::slug(optional(Brand::find($get('brand_id')))->name.' '.$state))),
                    TextInput::make('slug')->label('Код')->required()->alphaDash()->unique(ignoreRecord: true),
                    Select::make('body_type_id')->label('Кузов')->relationship('bodyType', 'name'),
                    Select::make('drivetrain')->label('Привод')->options(Car::DRIVETRAINS),
                    TextInput::make('drivetrain_note')->label('Примечание к приводу')->placeholder('полный — в версиях 4WD'),
                    Select::make('fuel')->label('Топливо')->options(Car::FUELS)->helperText('Пусто — берётся из машины.'),
                    TextInput::make('fuel_note')->label('Примечание к топливу')->placeholder('электромобиль с генератором (EREV)')->columnSpan(2),
                    Toggle::make('is_verified')->label('Привод и кузов не зависят от комплектации')
                        ->helperText('Снимите, если привод бывает разный: машины этой модели попадут в список «сверить».')
                        ->columnSpanFull(),
                ]),
            Section::make('Характеристики модели')
                ->description('Паспортные данные самой распространённой в прокате версии. На сайте подписаны «≈ типично для модели»; точные значения конкретной машины указываются в её карточке и важнее этих. Из этих цифр считаются «мощнее / экономичнее», стоимость бензина и сравнение.')
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('power_hp')->label('Мощность, л.с.')->numeric()->minValue(40)->maxValue(1200),
                    TextInput::make('power_hp_max')->label('До, л.с. (у мощных версий)')->numeric(),
                    TextInput::make('engine_l')->label('Объём, л')->numeric()->step(0.1),
                    Select::make('fuel_grade')->label('Топливо для расчёта')->options(CarFacts::GRADES),
                    TextInput::make('consumption_city')->label('Расход город')->numeric()->step(0.1)->suffix('л/100'),
                    TextInput::make('consumption_highway')->label('Расход трасса')->numeric()->step(0.1)->suffix('л/100'),
                    TextInput::make('consumption_mixed')->label('Расход смешанный')->numeric()->step(0.1)->suffix('л/100')
                        ->helperText('Для электро — кВт·ч на 100 км.'),
                    TextInput::make('tank_l')->label('Бак, л')->numeric(),
                    TextInput::make('trunk_l')->label('Багажник, л')->numeric(),
                    TextInput::make('clearance_mm')->label('Клиренс, мм')->numeric(),
                    TextInput::make('diesel_power_hp')->label('Дизель: л.с.')->numeric()->helperText('Если у модели есть дизельные машины.'),
                    TextInput::make('diesel_consumption')->label('Дизель: расход')->numeric()->step(0.1)->suffix('л/100'),
                ]),

            Section::make('Текст для описаний машин')
                ->description('Один раз на модель. Из него и характеристик конкретной машины собирается описание в карточке. Только факты — без цифр, которые зависят от версии.')
                ->columnSpanFull()
                ->schema([
                    Textarea::make('overview')->label('О модели')->rows(4)->required(),
                    TagsInput::make('strengths')->label('Сильные стороны')->placeholder('Добавьте и нажмите Enter')->splitKeys(['Enter']),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('brand.name')->label('Марка')->sortable()->searchable(),
                TextColumn::make('name')->label('Модель')->searchable()->weight('medium'),
                TextColumn::make('bodyType.name')->label('Кузов'),
                TextColumn::make('power_hp')->label('л.с.')->sortable()->toggleable(),
                TextColumn::make('consumption_mixed')->label('Расход')->sortable()->toggleable(),
                TextColumn::make('drivetrain')->label('Привод')->formatStateUsing(fn ($state) => Car::DRIVETRAINS[$state] ?? '—')->badge()
                    ->color(fn ($state) => $state === '4wd' ? 'success' : 'gray'),
                IconColumn::make('is_verified')->label('Однозначно')->boolean(),
                TextColumn::make('cars_count')->label('Машин')->counts('cars')->sortable(),
            ])
            ->defaultSort('brand.name')
            ->filters([
                TernaryFilter::make('is_verified')->label('Привод однозначен'),
                SelectFilter::make('brand_id')->label('Марка')->relationship('brand', 'name')->searchable()->preload(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCarModels::route('/'),
            'create' => CreateCarModel::route('/create'),
            'edit' => EditCarModel::route('/{record}/edit'),
        ];
    }

    protected static function accessArea(): string
    {
        return 'fleet';
    }
}
