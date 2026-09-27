<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RestrictedToArea;
use App\Models\Setting;
use App\Support\Seo\SeoSettings;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use UnitEnum;

class SeoSettingsPage extends Page
{
    use RestrictedToArea;

    protected static ?string $navigationLabel = 'Настройки SEO';

    protected static string|UnitEnum|null $navigationGroup = 'SEO';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Настройки SEO';

    protected static ?string $slug = 'seo/settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected string $view = 'filament.pages.site-settings';

    /** Ключи вебмастеров хранятся отдельно — их читает шаблон сайта. */
    private const WEBMASTER_KEYS = ['yandex_metrika_id', 'yandex_verification', 'google_verification'];

    private const TYPES = [
        'home' => ['Главная', ''],
        'catalog' => ['Каталог', '{count} — машин в каталоге, {price} — минимальная цена.'],
        'class' => ['Класс (эконом, бизнес…)', 'Используется, если в карточке класса SEO-поля пустые. {name} — название класса, {name_lower} — со строчной.'],
        'body' => ['Кузов (кроссовер, минивэн…)', 'Если в карточке кузова SEO-поля пустые.'],
        'gearbox_at' => ['Автомат', ''],
        'gearbox_mt' => ['Механика', ''],
        'brand' => ['Марка', 'Если в карточке марки SEO-поля пустые. {name} — марка.'],
        'city' => ['Город', 'Если в карточке города SEO-поля пустые.'],
        'car' => ['Автомобиль', '{name} — название, {price} — цена от, {gearbox} — коробка, {seats} — мест. Если у машины заполнены свои SEO-поля — используются они.'],
        'articles' => ['Список статей', ''],
        'article' => ['Статья', '{name} — заголовок статьи. Если у статьи заполнен свой title — используется он.'],
        'faq' => ['Вопросы и ответы', ''],
        'quiz' => ['Подбор авто', ''],
        'reviews' => ['Отзывы', ''],
        'compare' => ['Сравнение авто', 'Страница закрыта от индексации, но заголовки видят посетители.'],
    ];

    private const FIELD_LABELS = ['h1' => 'H1', 'title' => 'Title', 'description' => 'Description', 'intro' => 'Вступление под H1'];

    public ?array $data = [];

    public function mount(): void
    {
        $indexing = SeoSettings::indexing();
        $indexing['indexnow_key'] ??= Str::lower(Str::random(32));

        $this->form->fill([
            'seo_general' => SeoSettings::general(),
            'seo_templates' => SeoSettings::templates(),
            'seo_indexing' => $indexing,
            'seo_car_texts' => SeoSettings::carTextsRaw(),
            'webmasters' => collect(self::WEBMASTER_KEYS)->mapWithKeys(fn ($k) => [$k => Setting::get($k)])->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('seo')
                    ->persistTabInQueryString()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Шаблоны мета-тегов')->icon(Heroicon::OutlinedDocumentText)->schema($this->templateFields()),
                        Tab::make('Описания машин')->icon(Heroicon::OutlinedTruck)->schema($this->carTextFields()),
                        Tab::make('Общие')->icon(Heroicon::OutlinedGlobeAlt)->schema($this->generalFields()),
                        Tab::make('Индексация')->icon(Heroicon::OutlinedMagnifyingGlass)->schema($this->indexingFields()),
                        Tab::make('Вебмастеры и аналитика')->icon(Heroicon::OutlinedChartBar)->schema($this->webmasterFields()),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach (['seo_general', 'seo_templates', 'seo_indexing', 'seo_car_texts'] as $key) {
            Setting::put($key, $state[$key] ?? [], SeoSettings::GROUP);
        }
        foreach (self::WEBMASTER_KEYS as $key) {
            Setting::put($key, $state['webmasters'][$key] ?? null);
        }

        Notification::make()->title('SEO-настройки сохранены')->success()->send();
    }

    private function templateFields(): array
    {
        $sections = [
            Text::make('Подстановки: {site} — название сайта, {sep} — разделитель, {name}, {name_lower}, {count}, {price}. Если цены нет, фраза «от … ₽/сутки» убирается сама. Пустое поле — вернётся текст по умолчанию.'),
        ];

        foreach (self::TYPES as $type => [$label, $help]) {
            $fields = [];
            foreach (array_keys(SeoSettings::DEFAULTS['seo_templates'][$type]) as $field) {
                $input = in_array($field, ['description', 'intro'], true)
                    ? Textarea::make("seo_templates.{$type}.{$field}")->rows(2)
                    : TextInput::make("seo_templates.{$type}.{$field}");
                $fields[] = $input
                    ->label(self::FIELD_LABELS[$field])
                    ->helperText($field === 'title' ? 'Оптимально 50–65 символов.' : ($field === 'description' ? 'Оптимально 120–160 символов.' : null))
                    ->columnSpan(in_array($field, ['description', 'intro'], true) ? 'full' : 1);
            }

            $sections[] = Section::make($label)
                ->description($help ?: null)
                ->collapsible()
                ->collapsed($type !== 'home')
                ->columns(2)
                ->schema($fields);
        }

        return $sections;
    }

    private function carTextFields(): array
    {
        $area = fn (string $key, string $label, string $help = '') => Textarea::make('seo_car_texts.'.$key)
            ->label($label)->helperText($help ?: null)->rows(3)->autosize();

        return [
            Text::make('Каждая строка — отдельный вариант фразы. У разных машин выбираются разные варианты, у одной машины — всегда один и тот же. После правок пересоберите описания: «Автомобили» → выделить → «Пересобрать описания» (или php artisan cars:describe --force).'),
            Section::make('Коробка и привод')->columns(2)->collapsible()->schema([
                $area('gearbox_at', 'Автомат'),
                $area('gearbox_mt', 'Механика'),
                $area('drive_fwd', 'Передний привод'),
                $area('drive_rwd', 'Задний привод'),
                $area('drive_4wd', 'Полный привод')->columnSpanFull(),
            ]),
            Section::make('Кузов')->columns(2)->collapsible()->collapsed()->schema(
                collect(['sedan' => 'Седан', 'hetchbek' => 'Хэтчбек', 'liftbek' => 'Лифтбек', 'universal' => 'Универсал', 'krossover' => 'Кроссовер', 'vnedorozhnik' => 'Внедорожник', 'miniven' => 'Минивэн', 'kabriolet' => 'Кабриолет', 'kupe' => 'Купе'])
                    ->map(fn ($label, $slug) => $area('body_'.$slug, $label))->values()->all()
            ),
            Section::make('Класс и особенности')->columns(2)->collapsible()->collapsed()->schema([
                $area('class_ekonom', 'Эконом'),
                $area('class_srednij', 'Средний'),
                $area('class_biznes', 'Бизнес'),
                $area('family', '6+ мест', '{seats} — число мест.'),
                $area('electric', 'Электро с генератором'),
            ]),
            Section::make('Условия аренды')->collapsible()->collapsed()->schema([
                $area('conditions', 'Абзац с условиями', 'Подстановки: {name}, {price}, {deposit}, {age}, {experience}, {min_days}, {daily_km}, {pickup} — берутся из карточки машины.'),
            ]),
            Section::make('SEO-блок со ссылками внизу карточки')
                ->description('{link:home}, {link:class}, {link:body}, {link:gearbox}, {link:brand}, {link:cities}, {link:article}. Свой текст ссылки: {link:home|прокат авто в Крыму}. Если нужной страницы нет (например, у марки одна машина) — предложение пропускается.')
                ->columns(2)->collapsible()->collapsed()->schema([
                    $area('links_intro', 'Вступление (главная)'),
                    $area('links_catalog', 'Класс'),
                    $area('links_body', 'Кузов'),
                    $area('links_gearbox', 'Коробка'),
                    $area('links_brand', 'Марка'),
                    $area('links_cities', 'Города'),
                    $area('links_article', 'Статья'),
                ]),
        ];
    }

    private function generalFields(): array
    {
        return [
            Section::make('Сайт')
                ->columns(3)
                ->schema([
                    TextInput::make('seo_general.site_name')->label('Название сайта ({site})')->required(),
                    TextInput::make('seo_general.title_separator')->label('Разделитель ({sep})')->helperText('Например « | » или « — ».'),
                    TextInput::make('seo_general.pagination_suffix')->label('Суффикс страниц пагинации')->helperText('{page} — номер страницы.'),
                    Textarea::make('seo_general.default_description')->label('Description по умолчанию')->rows(2)->columnSpanFull(),
                ]),
            Section::make('Картинки')
                ->columns(2)
                ->schema([
                    FileUpload::make('seo_general.og_image')
                        ->label('Картинка для соцсетей и мессенджеров (Open Graph)')
                        ->helperText('1200×630 px, JPG или PNG. Показывается, когда ссылкой на сайт делятся в Telegram, WhatsApp, VK.')
                        ->image()
                        ->disk('public')
                        ->directory('seo')
                        ->imageEditor(),
                    FileUpload::make('seo_general.logo')
                        ->label('Логотип для поисковиков')
                        ->helperText('Квадрат от 512×512 px. Попадает в разметку Organization — Яндекс и Google показывают его рядом с сайтом.')
                        ->image()
                        ->disk('public')
                        ->directory('seo'),
                ]),
        ];
    }

    private function indexingFields(): array
    {
        return [
            Section::make('robots.txt')
                ->description('Строка «Sitemap:» с адресом карты сайта добавляется автоматически. На тестовых стендах (не production) сайт целиком закрыт от индексации — это защищает от дублей.')
                ->schema([
                    Textarea::make('seo_indexing.robots')
                        ->label('Содержимое')
                        ->rows(16)
                        ->extraInputAttributes(['style' => 'font-family: ui-monospace, monospace; font-size: 13px']),
                ]),
            Section::make('Карта сайта')
                ->description('Карта сайта строится автоматически: новые машины, статьи, города, марки и страницы попадают в неё сразу после публикации.')
                ->schema([
                    TextInput::make('seo_indexing.sitemap_min_cars')
                        ->label('Добавлять марку, класс или кузов в карту сайта, если в разделе машин не меньше')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Пустые разделы поисковики считают малоценными страницами.'),
                ]),
            Section::make('IndexNow')
                ->description('Мгновенно сообщает Яндексу и Bing о новых и изменённых страницах — их переобходят за минуты, а не дни. Работает только на боевом сайте.')
                ->columns(2)
                ->schema([
                    Toggle::make('seo_indexing.indexnow_enabled')->label('Включено'),
                    TextInput::make('seo_indexing.indexnow_key')
                        ->label('Ключ')
                        ->helperText('Сгенерирован автоматически. Файл ключа отдаётся по адресу /{ключ}.txt.')
                        ->readOnly(),
                ]),
        ];
    }

    private function webmasterFields(): array
    {
        return [
            Section::make('Яндекс')
                ->columns(2)
                ->schema([
                    TextInput::make('webmasters.yandex_metrika_id')->label('ID счётчика Метрики')->numeric()->helperText('Счётчик подключается только на боевом сайте, после загрузки страницы — не тормозит PageSpeed.'),
                    TextInput::make('webmasters.yandex_verification')->label('Код подтверждения Вебмастера')->helperText('Значение content из мета-тега yandex-verification.'),
                ]),
            Section::make('Google')
                ->schema([
                    TextInput::make('webmasters.google_verification')->label('Код подтверждения Search Console')->helperText('Значение content из мета-тега google-site-verification.'),
                ]),
        ];
    }

    protected static function accessArea(): string
    {
        return 'content';
    }
}
