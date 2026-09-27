<?php

namespace App\Filament\Resources\Cars;

use App\Filament\Concerns\RestrictedToArea;
use App\Filament\Forms\SearchAliasesInput;
use App\Filament\Resources\Cars\Pages\CreateCar;
use App\Filament\Resources\Cars\Pages\EditCar;
use App\Filament\Resources\Cars\Pages\ListCars;
use App\Models\Car;
use App\Models\CarModel;
use App\Support\CarDescription;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class CarResource extends Resource
{
    use RestrictedToArea;

    protected static ?string $model = Car::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|\UnitEnum|null $navigationGroup = 'Автопарк';

    protected static ?string $navigationLabel = 'Автомобили';

    protected static ?string $modelLabel = 'автомобиль';

    protected static ?string $pluralModelLabel = 'Автомобили';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function getNavigationBadge(): ?string
    {
        $count = Car::query()->where('status', 'published')->where('specs_verified', false)->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Характеристики не сверены с машиной партнёра';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Основное')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')->label('Название на сайте')->required()->columnSpanFull(),
                    TextInput::make('slug')->label('Адрес')->prefix('/avto/')->required()->alphaDash()->unique(ignoreRecord: true),
                    Select::make('status')->label('Статус')
                        ->options(['published' => 'На витрине', 'hidden' => 'Скрыто', 'unavailable' => 'Нет в наличии'])
                        ->required()->default('published'),
                    Select::make('brand_id')->label('Марка')->relationship('brand', 'name')->required()->searchable()->preload()->live(),
                    Select::make('car_model_id')->label('Модель из справочника')
                        ->options(fn (Get $get) => CarModel::query()
                            ->when($get('brand_id'), fn ($q, $brand) => $q->where('brand_id', $brand))
                            ->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            // Подставляем кузов и привод модели — дальше можно поправить вручную
                            if ($model = CarModel::find($state)) {
                                $set('body_type_id', $model->body_type_id);
                                $set('drivetrain', $model->drivetrain);
                            }
                        })
                        ->helperText('Из модели берутся кузов, привод и текст для описания.'),
                    Select::make('body_type_id')->label('Кузов')->relationship('bodyType', 'name'),
                    Select::make('classes')->label('Классы')->relationship('classes', 'name')->multiple()->preload(),
                    Select::make('partner_id')->label('Партнёр')->relationship('partner', 'name'),
                    TextInput::make('sort')->label('Порядок')->numeric()->default(0),
                ]),

            Section::make('Характеристики')
                ->description('Показываются в карточке с иконками и попадают в описание и разметку для поисковиков.')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('gearbox')->label('Коробка')->options(['at' => 'Автомат', 'mt' => 'Механика'])->required()->default('at'),
                    Select::make('drivetrain')->label('Привод')->options(Car::DRIVETRAINS),
                    Select::make('fuel')->label('Топливо')->options(Car::FUELS),
                    TextInput::make('engine')->label('Двигатель')->placeholder('1.6 л, 123 л.с.'),
                    TextInput::make('seats')->label('Мест')->numeric()->required()->default(5),
                    TextInput::make('consumption')->label('Расход (текст)')->placeholder('7 л/100 км')->helperText('Необязательно. Для расчётов — поле «Расход, л/100» ниже.'),
                    Section::make('Точные цифры этой машины')
                        ->description('Пусто — берётся типичное значение модели из справочника (показано серым). Заполните, если сверили машину у партнёра.')
                        ->columns(5)
                        ->columnSpanFull()
                        ->compact()
                        ->schema([
                            TextInput::make('power_hp')->label('Мощность, л.с.')->numeric()
                                ->placeholder(fn (Get $get) => CarModel::find($get('car_model_id'))?->power_hp),
                            TextInput::make('engine_l')->label('Объём, л')->numeric()->step(0.1)
                                ->placeholder(fn (Get $get) => CarModel::find($get('car_model_id'))?->engine_l),
                            TextInput::make('consumption_mixed')->label('Расход, л/100')->numeric()->step(0.1)
                                ->placeholder(fn (Get $get) => CarModel::find($get('car_model_id'))?->consumption_mixed),
                            TextInput::make('trunk_l')->label('Багажник, л')->numeric()
                                ->placeholder(fn (Get $get) => CarModel::find($get('car_model_id'))?->trunk_l),
                            TextInput::make('clearance_mm')->label('Клиренс, мм')->numeric()
                                ->placeholder(fn (Get $get) => CarModel::find($get('car_model_id'))?->clearance_mm),
                        ]),
                    TextInput::make('year_from')->label('Год с')->numeric(),
                    TextInput::make('year_to')->label('Год по')->numeric(),
                    Toggle::make('specs_verified')
                        ->label('Характеристики сверены с машиной')
                        ->helperText('Снимите, если привод или комплектация не подтверждены — машина попадёт в список «проверить».')
                        ->inline(false),
                ]),

            Section::make('Условия аренды')
                ->columns(5)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('deposit')->label('Залог, ₽')->numeric()->required()->default(0),
                    TextInput::make('min_age')->label('Возраст от')->numeric()->required()->default(22),
                    TextInput::make('min_experience')->label('Стаж от, лет')->numeric()->required()->default(2),
                    TextInput::make('min_days')->label('Мин. срок, сут.')->numeric()->required()->default(2),
                    TextInput::make('daily_km')->label('Км в сутки')->numeric()->required()->default(300),
                ]),

            Section::make('Описание')
                ->description('Собирается автоматически из справочника моделей и характеристик (кнопка «Пересобрать описание» вверху). Можно дописать вручную — тогда автосборка его не затрёт.')
                ->columnSpanFull()
                ->schema([
                    RichEditor::make('description')->hiddenLabel()
                        ->toolbarButtons([['bold', 'italic', 'link'], ['bulletList'], ['undo', 'redo']]),
                ]),

            Section::make('SEO')
                ->description('Пустые title и description берутся из шаблона «SEO → Настройки SEO → Автомобиль».')
                ->columns(2)
                ->collapsible()
                ->columnSpanFull()
                ->schema([
                    TextInput::make('seo_title')->label('Title')->columnSpanFull(),
                    Textarea::make('seo_description')->label('Description')->rows(2)->columnSpanFull(),
                    RichEditor::make('seo_text')->label('Дополнительный SEO-текст')
                        ->helperText('Показывается в блоке «Аренда … в Крыму» внизу страницы, после текста со ссылками.')
                        ->toolbarButtons([['bold', 'italic', 'link'], ['h3', 'bulletList'], ['undo', 'redo']])
                        ->columnSpanFull(),
                    SearchAliasesInput::make('Как ещё ищут именно эту машину: «ласточка», «семейный вэн». Синонимы марки, класса и кузова подтягиваются автоматически.'),
                ]),

            Section::make('Фото')
                ->columnSpanFull()
                ->schema([
                    SpatieMediaLibraryFileUpload::make('gallery')
                        ->hiddenLabel()
                        ->collection('gallery')
                        ->image()
                        ->multiple()
                        ->reorderable()
                        ->imageEditor()
                        ->panelLayout('grid')
                        ->customProperties(fn (Get $get) => ['alt' => 'Аренда '.$get('name').' в Крыму'])
                        ->helperText('Первое фото — обложка. От 1600 px по ширине, белый фон. Превью 640 и 1280 px в webp создаются автоматически.'),
                    Section::make('Старое фото (запасное)')
                        ->description('Показывается, только если выше нет ни одного фото.')
                        ->collapsed()
                        ->compact()
                        ->schema([
                            FileUpload::make('legacy_image')->hiddenLabel()->image(),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover')->label('')->state(fn (Car $car) => $car->coverUrl('card'))->width(64)->height(36),
                TextColumn::make('name')->label('Автомобиль')->searchable()->sortable()->weight('medium')->wrap()
                    ->description(fn (Car $car) => $car->carModel?->fullName()),
                TextColumn::make('gearbox')->label('КПП')->formatStateUsing(fn ($state) => $state === 'mt' ? 'МКПП' : 'АКПП')->badge()->color('gray'),
                TextColumn::make('drivetrain')->label('Привод')->formatStateUsing(fn ($state) => Car::DRIVETRAINS[$state] ?? '—')->badge()
                    ->color(fn ($state) => $state === '4wd' ? 'success' : 'gray'),
                TextColumn::make('bodyType.name')->label('Кузов')->toggleable(),
                TextColumn::make('seats')->label('Мест')->sortable()->toggleable(),
                IconColumn::make('specs_verified')->label('Сверено')->boolean(),
                TextColumn::make('status')->label('Статус')->badge()
                    ->formatStateUsing(fn ($state) => ['published' => 'На витрине', 'hidden' => 'Скрыто', 'unavailable' => 'Нет в наличии'][$state] ?? $state)
                    ->color(fn ($state) => $state === 'published' ? 'success' : 'gray'),
                TextColumn::make('sort')->label('Порядок')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort')
            ->filters([
                TernaryFilter::make('specs_verified')->label('Характеристики сверены'),
                SelectFilter::make('drivetrain')->label('Привод')->options(Car::DRIVETRAINS),
                SelectFilter::make('brand_id')->label('Марка')->relationship('brand', 'name')->searchable()->preload(),
                SelectFilter::make('car_model_id')->label('Модель')->relationship('carModel', 'name')->searchable()->preload(),
                SelectFilter::make('status')->label('Статус')->options(['published' => 'На витрине', 'hidden' => 'Скрыто', 'unavailable' => 'Нет в наличии']),
            ])
            ->recordActions([
                Action::make('open')->label('')->tooltip('Открыть на сайте')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Car $car) => route('car.show', $car->slug), true),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('describe')
                        ->label('Пересобрать описания')
                        ->icon(Heroicon::OutlinedSparkles)
                        ->requiresConfirmation()
                        ->modalDescription('Описания выбранных машин будут собраны заново из справочника моделей. Ручные правки в них будут заменены.')
                        ->action(function (Collection $records) {
                            $records->each(fn (Car $car) => $car->forceFill([
                                'description' => CarDescription::build($car),
                                'description_generated_at' => now(),
                            ])->save());
                            Notification::make()->title('Описаний пересобрано: '.$records->count())->success()->send();
                        }),
                    BulkAction::make('verify')
                        ->label('Отметить «сверено»')
                        ->icon(Heroicon::OutlinedCheckBadge)
                        ->action(fn (Collection $records) => $records->each->update(['specs_verified' => true])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\BlocksRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCars::route('/'),
            'create' => CreateCar::route('/create'),
            'edit' => EditCar::route('/{record}/edit'),
        ];
    }

    protected static function accessArea(): string
    {
        return 'fleet';
    }
}
