<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RestrictedToArea;
use App\Models\Setting;
use App\Support\Search\SearchSettings;
use BackedEnum;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SmartSearchSettings extends Page
{
    use RestrictedToArea;

    protected static ?string $navigationLabel = 'Настройки поиска';

    protected static string|UnitEnum|null $navigationGroup = 'Умный поиск';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Настройки умного поиска';

    protected static ?string $slug = 'search/settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected string $view = 'filament.pages.site-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'search_intent' => SearchSettings::intent(),
            'search_tuning' => [
                'weights' => SearchSettings::weights(),
                'fuzzy' => SearchSettings::fuzzy(),
                'limits' => SearchSettings::limits(),
            ],
            'search_ui' => SearchSettings::ui(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('search')
                    ->persistTabInQueryString()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Слова-фильтры')
                            ->icon(Heroicon::OutlinedFunnel)
                            ->schema($this->intentFields()),
                        Tab::make('Точность')
                            ->icon(Heroicon::OutlinedScale)
                            ->schema($this->tuningFields()),
                        Tab::make('Тексты')
                            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                            ->schema($this->uiFields()),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::put($key, $value, SearchSettings::GROUP);
        }

        Notification::make()->title('Сохранено — поиск уже работает по новым настройкам')->success()->send();
    }

    private function tags(string $name, string $label, string $help): TagsInput
    {
        return TagsInput::make($name)
            ->label($label)
            ->helperText($help)
            ->splitKeys([',', 'Enter'])
            ->reorderable(false);
    }

    private function intentFields(): array
    {
        return [
            Section::make('Коробка, привод, топливо')
                ->description('Если слово из списка встречается в запросе, поиск превращает его в фильтр, а не ищет по названию.')
                ->columns(2)
                ->schema([
                    $this->tags('search_intent.gearbox_at', 'Автомат', 'Слова, означающие АКПП: «автомат», «акпп», «робот».'),
                    $this->tags('search_intent.gearbox_mt', 'Механика', 'Слова, означающие МКПП: «механика», «мкпп», «ручка».'),
                    $this->tags('search_intent.drive_4wd', 'Полный привод', '«4x4», «полный», «awd».'),
                    $this->tags('search_intent.fuel_electric', 'Электро', '«электро», «ev».'),
                    $this->tags('search_intent.fuel_diesel', 'Дизель', '«дизель», «diesel».'),
                    $this->tags('search_intent.cheap', 'Сначала дешёвые', 'Слова, после которых результаты сортируются по цене: «недорого», «бюджетно».'),
                ]),
            Section::make('Цена и места')
                ->columns(2)
                ->schema([
                    $this->tags('search_intent.price_max_words', 'Цена «не дороже»', 'Слово перед числом: «до 3000», «дешевле 3к».'),
                    $this->tags('search_intent.price_min_words', 'Цена «не дешевле»', 'Слово перед числом: «от 5000».'),
                    KeyValue::make('search_intent.seat_words')
                        ->label('Слова про места')
                        ->keyLabel('Слово')
                        ->valueLabel('Мест, не меньше')
                        ->addActionLabel('Добавить слово')
                        ->helperText('«7 мест» и «7-местный» поиск понимает сам. Здесь — слова без цифр: «семиместный» → 7.')
                        ->columnSpanFull(),
                ]),
            Section::make('Стоп-слова')
                ->description('Слова, которые не несут смысла для поиска и просто отбрасываются: «аренда», «машина», «в», «крым».')
                ->schema([
                    $this->tags('search_intent.stopwords', 'Стоп-слова', 'Если запрос из одних стоп-слов, покажется весь каталог.'),
                ]),
        ];
    }

    private function tuningFields(): array
    {
        $number = fn (string $name, string $label, string $help, float $min, float $max, float $step = 1) => TextInput::make($name)
            ->label($label)
            ->helperText($help)
            ->numeric()
            ->minValue($min)
            ->maxValue($max)
            ->step($step)
            ->required();

        return [
            Section::make('Вес полей')
                ->description('Насколько важно совпадение в каждом поле. Чем больше число, тем выше машина в выдаче.')
                ->columns(3)
                ->schema([
                    $number('search_tuning.weights.name', 'Название машины', 'Например, «Hyundai Solaris 2».', 0, 10, 0.1),
                    $number('search_tuning.weights.brand', 'Марка и её синонимы', '', 0, 10, 0.1),
                    $number('search_tuning.weights.model', 'Модель', '', 0, 10, 0.1),
                    $number('search_tuning.weights.aliases', 'Синонимы машины', 'Поле «Синонимы для поиска» у машины.', 0, 10, 0.1),
                    $number('search_tuning.weights.categories', 'Класс и кузов', '«эконом», «кроссовер» и их синонимы.', 0, 10, 0.1),
                    $number('search_tuning.weights.features', 'Опции', '«кондиционер», «4WD».', 0, 10, 0.1),
                ]),
            Section::make('Опечатки и раскладка')
                ->columns(2)
                ->schema([
                    $number('search_tuning.fuzzy.min_length', 'Исправлять опечатки в словах от … букв', 'Короче — только точное совпадение или начало слова.', 2, 10),
                    $number('search_tuning.fuzzy.max_distance_short', 'Ошибок в коротком слове (до 6 букв)', '1 — «киа» ≠ «кия», но «хундай» = «хендай».', 0, 3),
                    $number('search_tuning.fuzzy.max_distance_long', 'Ошибок в длинном слове (от 7 букв)', '2 — «мерседез», «мерсeдес» найдутся.', 0, 4),
                    $number('search_tuning.fuzzy.layout_ratio', 'Порог исправления раскладки', 'Во сколько раз вариант в другой раскладке должен быть лучше исходного. 1.5 — по умолчанию.', 1, 5, 0.1),
                ]),
            Section::make('Лимиты')
                ->columns(3)
                ->schema([
                    $number('search_tuning.limits.per_page', 'Машин на странице результатов', '', 6, 96),
                    $number('search_tuning.limits.suggest_cars', 'Машин в подсказках', '', 1, 12),
                    $number('search_tuning.limits.suggest_links', 'Марок и разделов в подсказках', '', 0, 10),
                    $number('search_tuning.limits.popular_brands', 'Популярных марок в пустом поиске', '', 0, 20),
                    $number('search_tuning.limits.min_query_length', 'Искать от … символов', '', 1, 5),
                ]),
        ];
    }

    private function uiFields(): array
    {
        $text = fn (string $key, string $label, string $help = '') => TextInput::make('search_ui.'.$key)->label($label)->helperText($help)->maxLength(255);

        return [
            Section::make('Поле поиска')
                ->columns(2)
                ->schema([
                    $text('header_trigger', 'Кнопка в шапке'),
                    $text('submit', 'Кнопка «Найти»'),
                    $text('placeholder', 'Подсказка в поле (шапка, страница поиска)'),
                    $text('placeholder_catalog', 'Подсказка в поле каталога'),
                    $text('hero_label', 'Подпись поля на главной'),
                    $text('placeholder_hero', 'Подсказка в поле на главной'),
                    $text('hero_submit', 'Кнопка на главной'),
                ]),
            Section::make('Выпадающие подсказки')
                ->columns(2)
                ->schema([
                    $text('group_recent', 'Заголовок «Недавние запросы»'),
                    $text('recent_clear', 'Ссылка «Очистить»'),
                    $text('group_popular', 'Заголовок «Популярные марки»'),
                    $text('group_links', 'Заголовок «Разделы каталога»'),
                    $text('group_cars', 'Заголовок «Автомобили»'),
                    $text('all_results', 'Ссылка «Все результаты»'),
                    $text('price_from', 'Приставка к цене', '«от» перед ценой машины.'),
                    $text('understood', 'Подпись перед распознанными фильтрами'),
                    $text('corrected', 'Уведомление об исправленной раскладке'),
                    $text('relaxed', 'Уведомление о частичных совпадениях'),
                    $text('empty_title', 'Заголовок «Ничего не нашли»'),
                    $text('empty_text', 'Совет, если ничего не нашли'),
                    $text('hint', 'Подсказка под пустым полем')->columnSpanFull(),
                    TagsInput::make('search_ui.examples')
                        ->label('Примеры запросов')
                        ->helperText('Кликабельные примеры под пустым полем поиска.')
                        ->splitKeys(['Enter'])
                        ->columnSpanFull(),
                ]),
            Section::make('Страница результатов')
                ->description('В текстах можно использовать :query — запрос, :count — число найденных машин.')
                ->columns(2)
                ->schema([
                    $text('page_title', 'Title страницы', 'Например: «Поиск: :query | Car on Time».'),
                    $text('page_description', 'Description страницы'),
                    $text('page_found', 'Заголовок, если нашли', '«Найдено :count авто».'),
                    $text('page_not_found', 'Заголовок, если не нашли'),
                    $text('page_query', 'Приписка с запросом', '«по запросу «:query»».'),
                    $text('page_understood', 'Подпись перед фильтрами'),
                    $text('page_corrected', 'Уведомление об исправленной раскладке'),
                    $text('page_relaxed', 'Уведомление о частичных совпадениях'),
                    $text('page_empty_title', 'Заголовок блока «Попробуйте иначе»'),
                    $text('page_catalog_button', 'Кнопка «Смотреть весь каталог»'),
                    TagsInput::make('search_ui.page_empty_tips')
                        ->label('Советы, если ничего не нашли')
                        ->splitKeys(['Enter'])
                        ->columnSpanFull(),
                ]),
            Section::make('Подписи распознанных фильтров')
                ->description(':price — цена, :seats — число мест.')
                ->columns(3)
                ->schema([
                    $text('chip_at', 'Автомат'),
                    $text('chip_mt', 'Механика'),
                    $text('chip_4wd', 'Полный привод'),
                    $text('chip_electric', 'Электро'),
                    $text('chip_diesel', 'Дизель'),
                    $text('chip_cheap', 'Сначала дешёвые'),
                    $text('chip_price_max', 'Цена до', '«до :price ₽/сут».'),
                    $text('chip_price_min', 'Цена от', '«от :price ₽/сут».'),
                    $text('chip_seats', 'Места', '«от :seats мест».'),
                ]),
        ];
    }

    protected static function accessArea(): string
    {
        return 'content';
    }
}
