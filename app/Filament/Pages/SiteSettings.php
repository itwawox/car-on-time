<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RestrictedToArea;
use App\Models\Car;
use App\Models\Setting;
use App\Support\BookingStages;
use App\Support\ExpiredForm;
use App\Support\HeroStage;
use App\Support\HomeScenarios;
use App\Support\Quiz;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SiteSettings extends Page
{
    use RestrictedToArea;

    protected static ?string $navigationLabel = 'Настройки сайта';

    protected static string|\UnitEnum|null $navigationGroup = 'Сайт';

    protected static ?string $title = 'Настройки сайта';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected string $view = 'filament.pages.site-settings';

    public ?array $data = [];

    /** Ключи текстов квиза: вопросы, варианты, кнопки. @return list<string> */
    private static function quizKeys(): array
    {
        $keys = ['quiz_submit', 'quiz_need_hint', 'quiz_result_title', 'quiz_label_best', 'quiz_label_cheaper', 'quiz_label_comfort'];
        foreach (Quiz::steps() as $step) {
            $keys[] = 'quiz_q_'.$step['name'];
            foreach ($step['options'] as $o) {
                $keys[] = 'quiz_o_'.$step['name'].'_'.$o['value'];
            }
        }

        return $keys;
    }

    public function mount(): void
    {
        $stageKeys = collect(HeroStage::TABS)->keys()->flatMap(fn (string $key) => ['hero_stage_'.$key.'_tab', 'hero_stage_'.$key.'_car', 'hero_stage_'.$key.'_image']);
        $this->form->fill(collect(self::quizKeys())->merge($stageKeys)->mapWithKeys(fn ($k) => [$k => Setting::get($k)])->all() + [
            'brand_name' => Setting::get('brand_name', 'Car on Time'),
            'hero_stage_enabled' => HeroStage::enabled(),
            'hero_stage_link' => Setting::get('hero_stage_link'),
            'logo_word_1' => Setting::get('logo_word_1'),
            'logo_word_2' => Setting::get('logo_word_2'),
            'logo_tagline' => Setting::get('logo_tagline'),
            'cookie_text' => Setting::get('cookie_text'),
            'welcome_title' => Setting::get('welcome_title'),
            'back_results_label' => Setting::get('back_results_label'),
            'hero_hints_label' => Setting::get('hero_hints_label'),
            'hero_hints' => Setting::get('hero_hints'),
            'home_trips_title' => Setting::get('home_trips_title'),
            'home_trips_lead' => Setting::get('home_trips_lead'),
            'home_hits_title' => Setting::get('home_hits_title'),
            'scenario_family_title' => Setting::get('scenario_family_title'),
            'scenario_family_text' => Setting::get('scenario_family_text'),
            'scenario_sea_title' => Setting::get('scenario_sea_title'),
            'scenario_sea_text' => Setting::get('scenario_sea_text'),
            'scenario_mountains_title' => Setting::get('scenario_mountains_title'),
            'scenario_mountains_text' => Setting::get('scenario_mountains_text'),
            'scenario_business_title' => Setting::get('scenario_business_title'),
            'scenario_business_text' => Setting::get('scenario_business_text'),
            'scenario_fun_title' => Setting::get('scenario_fun_title'),
            'scenario_fun_text' => Setting::get('scenario_fun_text'),
            'scenario_economy_title' => Setting::get('scenario_economy_title'),
            'scenario_economy_text' => Setting::get('scenario_economy_text'),
            'phone' => Setting::get('phone'),
            'phone_raw' => Setting::get('phone_raw'),
            'telegram' => Setting::get('telegram'),
            'whatsapp' => Setting::get('whatsapp'),
            'email' => Setting::get('email'),
            'address' => Setting::get('address'),
            'hours' => Setting::get('hours'),
            'disclaimer' => Setting::get('disclaimer'),
            'fab_label' => Setting::get('fab_label'),
            'fab_title' => Setting::get('fab_title'),
            'messenger_text' => Setting::get('messenger_text'),
            'help_title' => Setting::get('help_title'),
            'help_text' => Setting::get('help_text'),
            'help_catalog_title' => Setting::get('help_catalog_title'),
            'help_catalog_text' => Setting::get('help_catalog_text'),
            'max' => Setting::get('max'),
            'vk' => Setting::get('vk'),
            'legal_name' => Setting::get('legal_name'),
            'inn' => Setting::get('inn'),
            'ogrn' => Setting::get('ogrn'),
            'founding_year' => Setting::get('founding_year'),
            'footer_about' => Setting::get('footer_about'),
            'pickup_point' => Setting::get('pickup_point'),
            'postal_code' => Setting::get('postal_code'),
            'address_region' => Setting::get('address_region'),
            'address_locality' => Setting::get('address_locality'),
            'street_address' => Setting::get('street_address'),
            'hero_eyebrow' => Setting::get('hero_eyebrow'),
            'hero_title' => Setting::get('hero_title'),
            'hero_lead' => Setting::get('hero_lead'),
            'hero_stat_1_value' => Setting::get('hero_stat_1_value'),
            'hero_stat_1_label' => Setting::get('hero_stat_1_label'),
            'hero_stat_2_value' => Setting::get('hero_stat_2_value'),
            'hero_stat_2_label' => Setting::get('hero_stat_2_label'),
            'usp_1_title' => Setting::get('usp_1_title'),
            'usp_1_text' => Setting::get('usp_1_text'),
            'usp_2_title' => Setting::get('usp_2_title'),
            'usp_2_text' => Setting::get('usp_2_text'),
            'usp_3_title' => Setting::get('usp_3_title'),
            'usp_3_text' => Setting::get('usp_3_text'),
            'usp_4_title' => Setting::get('usp_4_title'),
            'usp_4_text' => Setting::get('usp_4_text'),
            'home_classes_lead' => Setting::get('home_classes_lead'),
            'home_hits_lead' => Setting::get('home_hits_lead'),
            'home_cities_lead' => Setting::get('home_cities_lead'),
            'home_faq_lead' => Setting::get('home_faq_lead'),
            'cta_title' => Setting::get('cta_title'),
            'cta_text' => Setting::get('cta_text'),
            'home_reviews_title' => Setting::get('home_reviews_title'),
            'reviews_form_title' => Setting::get('reviews_form_title'),
            'reviews_form_text' => Setting::get('reviews_form_text'),
            'reviews_placeholder' => Setting::get('reviews_placeholder'),
            'reviews_submit' => Setting::get('reviews_submit'),
            'reviews_thanks' => Setting::get('reviews_thanks'),
            'reviews_empty_title' => Setting::get('reviews_empty_title'),
            'reviews_empty_text' => Setting::get('reviews_empty_text'),
            'reviews_car_empty' => Setting::get('reviews_car_empty'),
            'reviews_reply_label' => Setting::get('reviews_reply_label'),
            'fuel_price_92' => Setting::get('fuel_price_92'),
            'fuel_price_95' => Setting::get('fuel_price_95'),
            'fuel_price_98' => Setting::get('fuel_price_98'),
            'fuel_price_dt' => Setting::get('fuel_price_dt'),
            'fuel_price_electric' => Setting::get('fuel_price_electric'),
            'trip_title' => Setting::get('trip_title'),
            'trip_routes' => Setting::get('trip_routes'),
            'alt_block_title' => Setting::get('alt_block_title'),
            'alt_block_text' => Setting::get('alt_block_text'),
            'alt_title_cheaper' => Setting::get('alt_title_cheaper'),
            'alt_title_stronger' => Setting::get('alt_title_stronger'),
            'alt_title_economy' => Setting::get('alt_title_economy'),
            'alt_title_automatic' => Setting::get('alt_title_automatic'),
            'alt_title_awd' => Setting::get('alt_title_awd'),
            'alt_title_bigger' => Setting::get('alt_title_bigger'),
            'specs_typical_note' => Setting::get('specs_typical_note'),
            'badge_deal' => Setting::get('badge_deal'),
            'badge_family' => Setting::get('badge_family'),
            'badge_mountains' => Setting::get('badge_mountains'),
            'badge_economy' => Setting::get('badge_economy'),
            'badge_deal_hint' => Setting::get('badge_deal_hint'),
            'badge_family_hint' => Setting::get('badge_family_hint'),
            'badge_mountains_hint' => Setting::get('badge_mountains_hint'),
            'badge_economy_hint' => Setting::get('badge_economy_hint'),
            'recent_title' => Setting::get('recent_title'),
            'similar_title' => Setting::get('similar_title'),
            'recent_clear' => Setting::get('recent_clear'),
            'recent_clear_hint' => Setting::get('recent_clear_hint'),
            'compare_clear' => Setting::get('compare_clear'),
            'compare_clear_hint' => Setting::get('compare_clear_hint'),
            'favorites_clear' => Setting::get('favorites_clear'),
            'favorites_clear_hint' => Setting::get('favorites_clear_hint'),
            'compare_need_more' => Setting::get('compare_need_more'),
            'compare_need_more_hint' => Setting::get('compare_need_more_hint'),
            'compare_page_need_more' => Setting::get('compare_page_need_more'),
            'quiz_resume_text' => Setting::get('quiz_resume_text'),
            'extras_title' => Setting::get('extras_title'),
            'promo_link' => Setting::get('promo_link'),
            'promo_note' => Setting::get('promo_note'),
            'submit_note' => Setting::get('submit_note'),
            'phone_next_hint' => Setting::get('phone_next_hint'),
            'phone_ok_text' => Setting::get('phone_ok_text'),
            'consent_required_text' => Setting::get('consent_required_text'),
            'form_expired_text' => Setting::get('form_expired_text'),
            'deposit_explain' => Setting::get('deposit_explain'),
            'stage_received_title' => Setting::get('stage_received_title'),
            'stage_received_text' => Setting::get('stage_received_text'),
            'stage_checking_title' => Setting::get('stage_checking_title'),
            'stage_checking_text' => Setting::get('stage_checking_text'),
            'stage_confirmed_title' => Setting::get('stage_confirmed_title'),
            'stage_confirmed_text' => Setting::get('stage_confirmed_text'),
            'stage_active_title' => Setting::get('stage_active_title'),
            'stage_active_text' => Setting::get('stage_active_text'),
            'stage_done_title' => Setting::get('stage_done_title'),
            'stage_done_text' => Setting::get('stage_done_text'),
            'stage_declined_title' => Setting::get('stage_declined_title'),
            'stage_declined_text' => Setting::get('stage_declined_text'),
            'booking_details_title' => Setting::get('booking_details_title'),
            'booking_details_optional' => Setting::get('booking_details_optional'),
            'booking_details_text' => Setting::get('booking_details_text'),
            'booking_details_submit' => Setting::get('booking_details_submit'),
            'booking_details_saved' => Setting::get('booking_details_saved'),
            'prepay_title' => Setting::get('prepay_title'),
            'prepay_text' => Setting::get('prepay_text'),
            'prepay_button' => Setting::get('prepay_button'),
            'prepaid_title' => Setting::get('prepaid_title'),
            'prepaid_text' => Setting::get('prepaid_text'),
            'documents_title' => Setting::get('documents_title'),
            'documents_text' => Setting::get('documents_text'),
            'documents_saved_text' => Setting::get('documents_saved_text'),
            'documents_consent_text' => Setting::get('documents_consent_text'),
            'documents_submit' => Setting::get('documents_submit'),
            'act_title' => Setting::get('act_title'),
            'act_text' => Setting::get('act_text'),
            'cabinet_title' => Setting::get('cabinet_title'),
            'cabinet_lead' => Setting::get('cabinet_lead'),
            'cabinet_no_sms_text' => Setting::get('cabinet_no_sms_text'),
            'deposit_waiver_hint' => Setting::get('deposit_waiver_hint'),
            'availability_free_text' => Setting::get('availability_free_text'),
            'availability_busy_text' => Setting::get('availability_busy_text'),
            'availability_busy_card' => Setting::get('availability_busy_card'),
            'thanks_title' => Setting::get('thanks_title'),
            'thanks_steps_title' => Setting::get('thanks_steps_title'),
            'thanks_steps' => Setting::get('thanks_steps'),
            'trust_steps_title' => Setting::get('trust_steps_title'),
            'trust_steps' => Setting::get('trust_steps'),
            'included_title' => Setting::get('included_title'),
            'included_list' => Setting::get('included_list'),
            'not_included_title' => Setting::get('not_included_title'),
            'not_included_list' => Setting::get('not_included_list'),
            'payment_title' => Setting::get('payment_title'),
            'payment_methods' => Setting::get('payment_methods'),
            'map_contacts' => Setting::get('map_contacts'),
            'map_title' => Setting::get('map_title'),
            'map_button' => Setting::get('map_button'),
            'favorites_title' => Setting::get('favorites_title'),
            'favorites_intro' => Setting::get('favorites_intro'),
            'hero_tab_dates' => Setting::get('hero_tab_dates'),
            'hero_tab_name' => Setting::get('hero_tab_name'),
            'hero_place_label' => Setting::get('hero_place_label'),
            'hero_other_return' => Setting::get('hero_other_return'),
            'hero_book_submit' => Setting::get('hero_book_submit'),
            'promotions_title' => Setting::get('promotions_title'),
            'promotions_intro' => Setting::get('promotions_intro'),
            'promotions_description' => Setting::get('promotions_description'),
            'callback_label' => Setting::get('callback_label'),
            'corporate_form_title' => Setting::get('corporate_form_title'),
            'corporate_form_text' => Setting::get('corporate_form_text'),
            'lead_thanks' => Setting::get('lead_thanks'),
            'owner_form_title' => Setting::get('owner_form_title'),
            'owner_form_text' => Setting::get('owner_form_text'),
            'owner_thanks' => Setting::get('owner_thanks'),
            'faq_intro' => Setting::get('faq_intro'),
            'faq_search_placeholder' => Setting::get('faq_search_placeholder'),
            'faq_more_title' => Setting::get('faq_more_title'),
            'faq_more_text' => Setting::get('faq_more_text'),
            'faq_group_rules' => Setting::get('faq_group_rules'),
            'faq_group_booking' => Setting::get('faq_group_booking'),
            'faq_group_deposit' => Setting::get('faq_group_deposit'),
            'faq_group_pickup' => Setting::get('faq_group_pickup'),
            'faq_group_trip' => Setting::get('faq_group_trip'),
            'faq_group_incidents' => Setting::get('faq_group_incidents'),
            'faq_group_business' => Setting::get('faq_group_business'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('brand_name')->label('Бренд'),
                Section::make('Логотип')
                    ->description('Надпись рядом со знаком в шапке, меню и подвале. Вторая часть выделяется цветом. Перевод на русском — мелкой строкой под названием.')
                    ->columns(3)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        TextInput::make('logo_word_1')->label('Название — первая часть')->placeholder('Car On'),
                        TextInput::make('logo_word_2')->label('Название — выделенная часть')->placeholder('Time'),
                        TextInput::make('logo_tagline')->label('Перевод на русском')->placeholder('машина вовремя'),
                    ]),
                Textarea::make('cookie_text')->label('Текст баннера cookie')->rows(2)->columnSpanFull()
                    ->helperText('Яндекс.Метрика включается только после «Принять все». Юридические тексты — в «Контент → Страницы».'),
                TextInput::make('phone')->label('Телефон'),
                TextInput::make('phone_raw')->label('Телефон для tel:'),
                TextInput::make('telegram'),
                TextInput::make('whatsapp'),
                TextInput::make('email'),
                TextInput::make('address')->label('Адрес'),
                TextInput::make('hours')->label('Режим (заявки)'),
                Textarea::make('disclaimer')->label('Дисклеймер')->columnSpanFull(),
                TextInput::make('max')->label('MAX (ссылка)'),
                TextInput::make('vk')->label('ВКонтакте (ссылка)')->url(),
                Section::make('Кнопка «Написать» и мессенджеры')
                    ->description('Плавающая кнопка в правом нижнем углу на всех страницах. Ссылки на WhatsApp, Telegram и MAX — выше.')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('fab_label')->label('Плавающая кнопка — надпись'),
                        TextInput::make('fab_title')->label('Плавающая кнопка — заголовок меню'),
                        TextInput::make('messenger_text')->label('Текст сообщения в WhatsApp по умолчанию'),
                    ]),
                Section::make('Боковая колонка «Нужна помощь?»')
                    ->description('Показывается справа на страницах «Подбор», «Вопросы и ответы», «Условия» и в статьях. Пустое поле — текст по умолчанию.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('help_title')->label('Боковая колонка — заголовок'),
                        Textarea::make('help_text')->label('Боковая колонка — текст')->rows(2)->placeholder(null),
                        TextInput::make('help_catalog_title')->label('Боковая колонка — ссылка на каталог'),
                        TextInput::make('help_catalog_text')->label('Боковая колонка — подпись ссылки'),
                    ]),
                Section::make('Компания и реквизиты')
                    ->description('Выводятся в подвале, на странице «Контакты» и в разметке для Яндекса и Google.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('legal_name')->label('Юрлицо')->placeholder('ООО «…»'),
                        TextInput::make('inn')->label('ИНН'),
                        TextInput::make('ogrn')->label('ОГРН'),
                        TextInput::make('founding_year')->label('Год основания')->numeric()->minValue(1990)->maxValue(2100),
                        Textarea::make('footer_about')->label('О компании (подвал)')->helperText(':year — год основания.')->rows(2)->columnSpanFull(),
                        TextInput::make('pickup_point')->label('Точка выдачи')->columnSpanFull(),
                        TextInput::make('postal_code')->label('Индекс'),
                        TextInput::make('address_region')->label('Регион'),
                        TextInput::make('address_locality')->label('Населённый пункт'),
                        TextInput::make('street_address')->label('Улица, дом'),
                    ]),
                Section::make('Главная страница')
                    ->description('Пустое поле — показывается текст по умолчанию.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('hero_eyebrow')->label('Метка над заголовком'),
                        TextInput::make('hero_title')->label('Заголовок H1'),
                        Textarea::make('hero_lead')->label('Подзаголовок (:count — число авто)')->rows(2)->columnSpanFull(),
                        TextInput::make('hero_stat_1_value')->label('Показатель 1 — значение'),
                        TextInput::make('hero_stat_1_label')->label('Показатель 1 — подпись'),
                        TextInput::make('hero_stat_2_value')->label('Показатель 2 — значение'),
                        TextInput::make('hero_stat_2_label')->label('Показатель 2 — подпись'),
                        TextInput::make('usp_1_title')->label('Преимущество 1'),
                        TextInput::make('usp_1_text')->label('Преимущество 1 — подпись'),
                        TextInput::make('usp_2_title')->label('Преимущество 2'),
                        TextInput::make('usp_2_text')->label('Преимущество 2 — подпись'),
                        TextInput::make('usp_3_title')->label('Преимущество 3'),
                        TextInput::make('usp_3_text')->label('Преимущество 3 — подпись'),
                        TextInput::make('usp_4_title')->label('Преимущество 4'),
                        TextInput::make('usp_4_text')->label('Преимущество 4 — подпись'),
                        Textarea::make('home_classes_lead')->label('Подзаголовок «Подберите класс»')->rows(2)->columnSpanFull(),
                        Textarea::make('home_hits_lead')->label('Подзаголовок «Часто берут»')->rows(2)->columnSpanFull(),
                        Textarea::make('home_cities_lead')->label('Подзаголовок «Города»')->rows(2)->columnSpanFull(),
                        Textarea::make('home_faq_lead')->label('Подзаголовок «Частые вопросы»')->rows(2)->columnSpanFull(),
                        TextInput::make('cta_title')->label('Финальный блок — заголовок'),
                        Textarea::make('cta_text')->label('Финальный блок — текст')->rows(2)->columnSpanFull(),
                    ]),
                Section::make('Отзывы')
                    ->description('Страница /otzyvy, блок на главной и в карточке машины. Заголовок и SEO страницы — в «Настройки SEO → Шаблоны → Отзывы». Пустое поле — текст по умолчанию.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        TextInput::make('home_reviews_title')->label('Заголовок блока на главной')->placeholder('Отзывы клиентов'),
                        TextInput::make('reviews_form_title')->label('Форма — заголовок')->placeholder('Оставить отзыв'),
                        Textarea::make('reviews_form_text')->label('Форма — пояснение')->rows(2)->columnSpanFull(),
                        TextInput::make('reviews_placeholder')->label('Форма — подсказка в поле отзыва')->columnSpanFull(),
                        TextInput::make('reviews_submit')->label('Форма — кнопка')->placeholder('Отправить отзыв'),
                        TextInput::make('reviews_reply_label')->label('Подпись над ответом компании')->placeholder('Ответ Car on Time'),
                        Textarea::make('reviews_thanks')->label('Сообщение после отправки')->rows(2)->columnSpanFull(),
                        TextInput::make('reviews_empty_title')->label('Нет отзывов — заголовок'),
                        TextInput::make('reviews_empty_text')->label('Нет отзывов — текст'),
                        Textarea::make('reviews_car_empty')->label('Карточка машины — нет отзывов')->rows(2)->columnSpanFull(),
                    ]),
                Section::make('Поездка: цены топлива и маршруты')
                    ->description('Для строки «Бензин на поездку» в карточке машины и колонки «100 км ≈ ₽» в каталоге. Пусто — цена по умолчанию (АИ-92 62 ₽, АИ-95 68 ₽, АИ-98 85 ₽, ДТ 74 ₽, зарядка 25 ₽/кВт·ч).')
                    ->columns(5)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        TextInput::make('fuel_price_92')->label('АИ-92, ₽/л')->numeric()->step(0.1),
                        TextInput::make('fuel_price_95')->label('АИ-95, ₽/л')->numeric()->step(0.1),
                        TextInput::make('fuel_price_98')->label('АИ-98, ₽/л')->numeric()->step(0.1),
                        TextInput::make('fuel_price_dt')->label('ДТ, ₽/л')->numeric()->step(0.1),
                        TextInput::make('fuel_price_electric')->label('Зарядка, ₽/кВт·ч')->numeric()->step(0.1),
                        TextInput::make('trip_title')->label('Заголовок строки')->placeholder('Бензин на поездку')->columnSpanFull(),
                        Repeater::make('trip_routes')->label('Маршруты')
                            ->helperText('Пусто — стандартный список. «В день» — километры умножаются на число суток аренды.')
                            ->schema([
                                TextInput::make('name')->label('Название')->required()->columnSpan(3),
                                TextInput::make('km')->label('Км')->numeric()->required(),
                                Toggle::make('per_day')->label('В день')->inline(false),
                            ])
                            ->columns(5)->reorderable()->collapsible()->defaultItems(0)->columnSpanFull(),
                    ]),
                Section::make('Подбор: «Сравните с похожими»')
                    ->description('Блок в карточке машины. Машины подбираются автоматически по кузову, классу, цене и характеристикам. Пусто — текст по умолчанию.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        TextInput::make('alt_block_title')->label('Заголовок блока')->placeholder('Сравните с похожими'),
                        TextInput::make('alt_block_text')->label('Подпись'),
                        TextInput::make('alt_title_cheaper')->label('Сценарий «дешевле»')->placeholder('Такая же, но дешевле'),
                        TextInput::make('alt_title_stronger')->label('Сценарий «мощнее»')->placeholder('Мощнее'),
                        TextInput::make('alt_title_economy')->label('Сценарий «экономичнее»')->placeholder('Экономичнее'),
                        TextInput::make('alt_title_automatic')->label('Сценарий «на автомате»')->placeholder('На автомате'),
                        TextInput::make('alt_title_awd')->label('Сценарий «полный привод»')->placeholder('С полным приводом'),
                        TextInput::make('alt_title_bigger')->label('Сценарий «просторнее»')->placeholder('Просторнее'),
                        Textarea::make('specs_typical_note')->label('Пояснение к «≈ типично для модели»')->rows(2)->columnSpanFull(),
                        TextInput::make('badge_deal')->label('Значок «выгодно»')->placeholder('Выгодно'),
                        TextInput::make('badge_family')->label('Значок «7+ мест»')->placeholder('Для семьи'),
                        TextInput::make('badge_mountains')->label('Значок «4WD и клиренс»')->placeholder('Для гор'),
                        TextInput::make('badge_economy')->label('Значок «низкий расход»')->placeholder('Экономичная'),
                        TextInput::make('badge_deal_hint')->label('Подсказка к значку «Выгодно»')->placeholder('Самая низкая цена среди похожих машин — дешевле обычной на 10% и больше'),
                        TextInput::make('badge_family_hint')->label('Подсказка к значку «Для семьи»')->placeholder('7 мест и больше — для большой семьи или компании'),
                        TextInput::make('badge_mountains_hint')->label('Подсказка к значку «Для гор»')->placeholder('Полный привод и клиренс от 20 см — для горных дорог'),
                        TextInput::make('badge_economy_hint')->label('Подсказка к значку «Экономичная»')->placeholder('Одна из самых экономичных по расходу топлива в своём классе'),
                        TextInput::make('recent_title')->label('Блок «Вы смотрели»')->placeholder('Вы смотрели'),
                        TextInput::make('similar_title')->label('Блок «Похоже на то, что вы смотрели»')->placeholder('Похоже на то, что вы смотрели'),
                        TextInput::make('recent_clear')->label('«Вы смотрели» — кнопка очистки')->placeholder('Очистить историю'),
                        TextInput::make('recent_clear_hint')->label('«Вы смотрели» — подсказка к кнопке')->placeholder('Убрать все машины из «Вы смотрели»'),
                        TextInput::make('compare_clear')->label('Сравнение — кнопка очистки')->placeholder('Очистить сравнение'),
                        TextInput::make('compare_clear_hint')->label('Сравнение — подсказка к кнопке')->placeholder('Убрать все машины из сравнения'),
                        TextInput::make('favorites_clear')->label('Избранное — кнопка очистки')->placeholder('Очистить избранное'),
                        TextInput::make('favorites_clear_hint')->label('Избранное — подсказка к кнопке')->placeholder('Убрать все машины из избранного'),
                        TextInput::make('compare_need_more')->label('Избранное: одна машина — вместо «Сравнить»')->placeholder('Добавьте ещё машину для сравнения'),
                        TextInput::make('compare_need_more_hint')->label('Избранное: одна машина — подсказка')->placeholder('Сравнивать можно от двух машин — отметьте ещё одну сердечком или кнопкой «Сравнить»'),
                        TextInput::make('compare_page_need_more')->label('Страница сравнения: одна машина')->placeholder('Добавьте ещё хотя бы одну машину — сравнивать можно от двух.'),
                        TextInput::make('quiz_resume_text')->label('Плашка «Продолжить подбор»')->placeholder('Продолжить подбор'),
                    ]),
                Section::make('Страница статуса заявки')
                    ->description('Клиент открывает её по ссылке из SMS: трекер «принята → проверяем → подтверждена → машина у вас → завершена». Пусто — текст по умолчанию.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema(collect(BookingStages::DEFAULTS)->flatMap(fn (array $d, string $k) => [
                        TextInput::make('stage_'.$k.'_title')->label('«'.$d[0].'» — заголовок')->placeholder($d[0]),
                        TextInput::make('stage_'.$k.'_text')->label('— пояснение')->placeholder($d[1]),
                    ])->values()->merge([
                        TextInput::make('documents_title')->label('Документы заранее — заголовок')->placeholder('Документы заранее'),
                        TextInput::make('documents_submit')->label('Документы — кнопка')->placeholder('Отправить документы'),
                        TextInput::make('documents_text')->label('Документы — пояснение')->columnSpanFull()
                            ->placeholder('Пришлите фото паспорта и прав — подготовим договор заранее, выдача займёт 5 минут. Файлы видит только менеджер, после аренды удаляем.'),
                        TextInput::make('documents_consent_text')->label('Документы — текст согласия')->columnSpanFull()
                            ->placeholder('Согласен на обработку копий паспорта и водительского удостоверения для заключения договора аренды'),
                        TextInput::make('documents_saved_text')->label('Документы — после отправки')->columnSpanFull()
                            ->placeholder('Получили. Договор подготовим заранее — на выдаче останется только подписать.'),
                        TextInput::make('act_title')->label('Акт осмотра — заголовок')->placeholder('Акт осмотра'),
                        TextInput::make('act_text')->label('Акт осмотра — пояснение')->placeholder('Фото машины при выдаче и возврате — одинаковые для вас и для нас. Так залог возвращается без споров.'),
                        TextInput::make('cabinet_title')->label('Личный кабинет — заголовок')->placeholder('Мои брони'),
                        TextInput::make('cabinet_lead')->label('Личный кабинет — подзаголовок')->placeholder('Все ваши заявки и брони в одном месте: статус, даты, продление аренды.'),
                        TextInput::make('cabinet_no_sms_text')->label('Личный кабинет — вход без SMS')->columnSpanFull()
                            ->placeholder('Чтобы открыть свои брони, перейдите по ссылке из сообщения о заявке и нажмите «Все мои брони».'),
                        TextInput::make('booking_details_title')->label('«Уточните детали» — заголовок')->placeholder('Уточните детали'),
                        TextInput::make('booking_details_optional')->label('— пометка')->placeholder('необязательно'),
                        TextInput::make('booking_details_text')->label('— пояснение')->columnSpanFull()
                            ->placeholder('Чтобы менеджеру было проще: как к вам обращаться, где удобнее связаться и что добавить к аренде.'),
                        TextInput::make('booking_details_submit')->label('— кнопка')->placeholder('Сохранить'),
                        TextInput::make('booking_details_saved')->label('— после сохранения')->placeholder('Спасибо, передали менеджеру.'),
                        TextInput::make('prepay_title')->label('Предоплата — заголовок')->placeholder('Закрепите машину за собой'),
                        TextInput::make('prepay_button')->label('Предоплата — кнопка')->placeholder('Внести предоплату'),
                        TextInput::make('prepay_text')->label('Предоплата — пояснение')->columnSpanFull()
                            ->placeholder('Внесите предоплату онлайн — картой МИР, через СБП или другим удобным способом. Остаток — при получении. Можно и без предоплаты: бронь всё равно действует.'),
                        TextInput::make('prepaid_title')->label('Оплачено — заголовок')->placeholder('Предоплата получена'),
                        TextInput::make('prepaid_text')->label('Оплачено — пояснение')->placeholder('Машина закреплена за вами. Остаток — при получении.'),
                    ])->all()),
                Section::make('Заявка и страница «Спасибо»')
                    ->description('Форма заявки в карточке машины и страница после отправки. Доп. услуги — в «Справочники → Доп. услуги». Пусто — текст по умолчанию.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        TextInput::make('extras_title')->label('Заголовок доп. услуг')->placeholder('Добавить к аренде'),
                        TextInput::make('submit_note')->label('Подпись под кнопкой')->placeholder('Без предоплаты · перезвоним за 15 минут'),
                        TextInput::make('phone_next_hint')->label('Подсказка у телефона после расчёта цены')->placeholder('Остался один шаг — телефон. Перезвоним и подтвердим наличие'),
                        TextInput::make('phone_ok_text')->label('Номер набран полностью')->placeholder('Перезвоним на этот номер'),
                        TextInput::make('deposit_explain')->label('Подсказка к строке «доставка · залог» под ценой')->placeholder('Доставка уже в сумме. Залог вносится при получении машины и полностью возвращается после сдачи'),
                        TextInput::make('consent_required_text')->label('Не отмечено согласие при отправке')->placeholder('Отметьте согласие — без него мы не можем принять заявку'),
                        TextInput::make('form_expired_text')->label('Страница устарела (форма открыта слишком долго)')->placeholder(ExpiredForm::DEFAULT_TEXT)
                            ->helperText('Показывается, если посетитель долго не отправлял форму. Введённые данные сохраняются.'),
                        TextInput::make('availability_free_text')->label('Машина свободна на выбранные даты')->placeholder('Свободна на ваши даты'),
                        TextInput::make('availability_busy_text')->label('Машина занята на выбранные даты')->placeholder('На эти даты машина занята. Оставьте заявку — предложим такую же или похожую по той же цене.'),
                        TextInput::make('deposit_waiver_hint')->label('Подсказка «можно без залога» в расчёте')->placeholder('можно без залога: +{price} ₽/сут')
                            ->helperText('Показывается, если в «Доп. услугах» включена платная услуга со снятием залога. {price} — её цена в сутки.'),
                        TextInput::make('availability_busy_card')->label('Каталог: метка «занята»')->placeholder('Занята на эти даты'),
                        TextInput::make('promo_link')->label('Ссылка промокода')->placeholder('Есть промокод?'),
                        TextInput::make('promo_note')->label('Пояснение к промокоду')->placeholder('Скидка сразу появится в расчёте.'),
                        TextInput::make('thanks_title')->label('«Спасибо» — заголовок')->placeholder('Заявка принята'),
                        TextInput::make('thanks_steps_title')->label('«Спасибо» — заголовок шагов')->placeholder('Что дальше'),
                        Repeater::make('thanks_steps')->label('Шаги «Что дальше»')
                            ->helperText('Пусто — 4 стандартных шага: звонок, документы, осмотр, оплата при получении.')
                            ->schema([
                                TextInput::make('title')->label('Шаг')->required(),
                                Textarea::make('text')->label('Пояснение')->rows(2),
                            ])
                            ->reorderable()->collapsible()->defaultItems(0)->maxItems(6)->columnSpanFull(),
                    ]),
                Section::make('Доверие: «Как это работает», «Что входит», оплата')
                    ->description('Блоки на главной и в карточке машины. Пусто — текст по умолчанию из условий сайта. Способы оплаты не заполнены — блок не показывается.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        TextInput::make('trust_steps_title')->label('Заголовок шагов')->placeholder('Как это работает'),
                        Repeater::make('trust_steps')->label('Шаги')
                            ->schema([
                                Select::make('icon')->label('Иконка')->options(['search' => 'Поиск', 'chat' => 'Сообщение', 'clock' => 'Часы', 'car' => 'Машина', 'check' => 'Галочка', 'wallet' => 'Кошелёк', 'shield' => 'Щит', 'phone' => 'Телефон', 'pin' => 'Место', 'calendar' => 'Календарь'])->default('check'),
                                TextInput::make('title')->label('Шаг')->required(),
                                TextInput::make('text')->label('Пояснение'),
                            ])
                            ->columns(3)->reorderable()->collapsible()->defaultItems(0)->maxItems(6)->columnSpanFull(),
                        TextInput::make('included_title')->label('Заголовок «Что входит»')->placeholder('Что входит в аренду'),
                        TextInput::make('not_included_title')->label('Заголовок «Отдельно»')->placeholder('Оплачивается отдельно'),
                        Textarea::make('included_list')->label('Что входит — по строке на пункт')->rows(5),
                        Textarea::make('not_included_list')->label('Оплачивается отдельно — по строке на пункт')->rows(5),
                        TextInput::make('payment_title')->label('Подпись способов оплаты')->placeholder('Оплата при получении:'),
                        Textarea::make('payment_methods')->label('Способы оплаты — по строке (наличные, карта, СБП…)')->rows(3),
                        TextInput::make('map_contacts')->label('Карта офиса: ссылка виджета Яндекс.Карт')->url()->helperText('Пусто — карта ищет по адресу компании.'),
                        TextInput::make('map_title')->label('Заголовок карты')->placeholder('На карте'),
                        TextInput::make('map_button')->label('Кнопка карты')->placeholder('Показать карту'),
                        TextInput::make('favorites_title')->label('Страница «Избранное» — заголовок')->placeholder('Избранное'),
                        Textarea::make('favorites_intro')->label('Страница «Избранное» — текст')->rows(2),
                        TextInput::make('hero_tab_dates')->label('Главная: вкладка поиска по датам')->placeholder('По датам и месту'),
                        TextInput::make('hero_tab_name')->label('Главная: вкладка поиска по названию')->placeholder('По названию'),
                        TextInput::make('hero_place_label')->label('Главная: подпись поля места')->placeholder('Где забрать'),
                        TextInput::make('hero_other_return')->label('Главная: «вернуть в другом месте»')->placeholder('Вернуть в другом месте'),
                        TextInput::make('hero_book_submit')->label('Главная: кнопка поиска по датам')->placeholder('Показать машины'),
                        TextInput::make('promotions_title')->label('Акции: заголовок раздела')->placeholder('Акции'),
                        TextInput::make('promotions_intro')->label('Акции: текст под заголовком'),
                        TextInput::make('promotions_description')->label('Акции: description для поисковиков')->columnSpanFull(),
                        TextInput::make('callback_label')->label('Плавающая кнопка: «перезвоним вам»')->placeholder('Или перезвоним вам'),
                        TextInput::make('lead_thanks')->label('Ответ после заявки юрлица / звонка'),
                        TextInput::make('corporate_form_title')->label('Юрлицам: заголовок формы')->placeholder('Заявка для компании'),
                        TextInput::make('corporate_form_text')->label('Юрлицам: пояснение формы'),
                        TextInput::make('owner_form_title')->label('Сдать авто: заголовок формы')->placeholder('Заявка владельца'),
                        TextInput::make('owner_form_text')->label('Сдать авто: пояснение формы'),
                        TextInput::make('owner_thanks')->label('Сдать авто: ответ после заявки')->columnSpanFull(),
                    ]),
                Section::make('Вопросы и ответы: страница')
                    ->description('Сами вопросы — в «Контент → Вопросы и ответы». Здесь — тексты страницы /faq и названия разделов.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        Textarea::make('faq_intro')->label('Текст под заголовком')->rows(2)->columnSpanFull(),
                        TextInput::make('faq_search_placeholder')->label('Подсказка в поиске'),
                        TextInput::make('faq_more_title')->label('Блок «Не нашли ответ?» — заголовок'),
                        TextInput::make('faq_more_text')->label('Блок «Не нашли ответ?» — текст')->columnSpanFull(),
                        TextInput::make('faq_group_rules')->label('Раздел «Требования и документы»')->placeholder('Требования и документы'),
                        TextInput::make('faq_group_booking')->label('Раздел «Бронирование и оплата»')->placeholder('Бронирование и оплата'),
                        TextInput::make('faq_group_deposit')->label('Раздел «Залог и страховка»')->placeholder('Залог и страховка'),
                        TextInput::make('faq_group_pickup')->label('Раздел «Получение, доставка и возврат»')->placeholder('Получение, доставка и возврат'),
                        TextInput::make('faq_group_trip')->label('Раздел «В поездке»')->placeholder('В поездке'),
                        TextInput::make('faq_group_incidents')->label('Раздел «ДТП, поломки и штрафы»')->placeholder('ДТП, поломки и штрафы'),
                        TextInput::make('faq_group_business')->label('Раздел «Юрлицам»')->placeholder('Юрлицам'),
                    ]),
                Section::make('Подбор: квиз (/podbor)')
                    ->description('Тексты вопросов и вариантов. Пусто — текст по умолчанию (показан серым). Логика подбора не меняется: она опирается на характеристики машин.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema(array_merge([
                        TextInput::make('quiz_submit')->label('Кнопка «Показать мои варианты»')->placeholder('Показать мои варианты'),
                        TextInput::make('quiz_need_hint')->label('Подсказка, если не ответили на вопрос')->placeholder('Выберите ответ на вопрос «{question}» — и покажем подходящие машины')
                            ->helperText('{question} — текст вопроса, например «Бюджет в сутки?».'),
                        TextInput::make('quiz_result_title')->label('Заголовок результата')->placeholder('Подобрали под ваши ответы'),
                        TextInput::make('quiz_label_best')->label('Метка «Лучшее совпадение»')->placeholder('Лучшее совпадение'),
                        TextInput::make('quiz_label_cheaper')->label('Метка «Дешевле»')->placeholder('Дешевле'),
                        TextInput::make('quiz_label_comfort')->label('Метка «Комфортнее»')->placeholder('Комфортнее'),
                    ], collect(Quiz::steps())->flatMap(fn ($step) => array_merge(
                        [TextInput::make('quiz_q_'.$step['name'])->label('Вопрос: '.$step['title'])->placeholder($step['title'])->columnSpanFull()],
                        array_map(fn ($o) => TextInput::make('quiz_o_'.$step['name'].'_'.$o['value'])->label('— вариант')->placeholder($o['label']), $step['options']),
                    ))->all())),
                Section::make('Главная: сцена первого экрана')
                    ->description('Справа от заголовка — машина под выбранную задачу. Машина не выбрана — берётся первая подходящая из сценария. Своё фото на прозрачном фоне (PNG или WebP) показывается как есть; обычное фото на белом фоне ставится на «студийную» подложку.')
                    ->columns(3)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        Toggle::make('hero_stage_enabled')->label('Показывать сцену на главной')->columnSpan(2),
                        TextInput::make('hero_stage_link')->label('Кнопка под сценой')->placeholder('Смотреть'),
                        ...collect(HeroStage::TABS)->flatMap(fn (string $tab, string $key) => [
                            TextInput::make('hero_stage_'.$key.'_tab')->label('«'.(HomeScenarios::SCENARIOS[$key][0] ?? $tab).'» — вкладка')->placeholder($tab),
                            Select::make('hero_stage_'.$key.'_car')->label('— машина')->placeholder('Автоматически')
                                ->options(fn () => Car::query()->published()->with('brand')->orderBy('sort')->get()->mapWithKeys(fn (Car $car) => [$car->id => $car->displayName()])->all())
                                ->searchable(),
                            FileUpload::make('hero_stage_'.$key.'_image')->label('— своё фото на прозрачном фоне')
                                ->image()->disk('public')->directory('hero')->acceptedFileTypes(['image/png', 'image/webp'])->maxSize(4096),
                        ])->all(),
                    ]),
                Section::make('Главная: сценарии, подсказки, приветствие')
                    ->description('Блоки «Для какой поездки?» и «Популярные машины», подсказки «Часто ищут» под формой, приветствие вернувшегося клиента. Машины, их число и цены считаются автоматически. Пусто — текст по умолчанию.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        TextInput::make('home_trips_title')->label('Заголовок «Для какой поездки?»'),
                        TextInput::make('home_trips_lead')->label('Подзаголовок сценариев'),
                        TextInput::make('scenario_family_title')->label('Сценарий «Для семьи» — название')->placeholder('Для семьи'),
                        TextInput::make('scenario_family_text')->label('— подпись'),
                        TextInput::make('scenario_sea_title')->label('Сценарий «Город и море» — название')->placeholder('Город и море'),
                        TextInput::make('scenario_sea_text')->label('— подпись'),
                        TextInput::make('scenario_mountains_title')->label('Сценарий «В горы» — название')->placeholder('В горы'),
                        TextInput::make('scenario_mountains_text')->label('— подпись'),
                        TextInput::make('scenario_business_title')->label('Сценарий «Деловая поездка» — название')->placeholder('Деловая поездка'),
                        TextInput::make('scenario_business_text')->label('— подпись'),
                        TextInput::make('scenario_fun_title')->label('Сценарий «Кабриолеты и купе» — название')->placeholder('Кабриолеты и купе'),
                        TextInput::make('scenario_fun_text')->label('— подпись'),
                        TextInput::make('scenario_economy_title')->label('Сценарий «Эконом» — название')->placeholder('Эконом'),
                        TextInput::make('scenario_economy_text')->label('— подпись'),
                        TextInput::make('home_hits_title')->label('Заголовок «Популярные машины»'),
                        TextInput::make('welcome_title')->label('Приветствие вернувшегося')->placeholder('С возвращением!'),
                        TextInput::make('back_results_label')->label('Ссылка в карточке машины')->placeholder('Назад к результатам'),
                        TextInput::make('hero_hints_label')->label('Подпись подсказок')->placeholder('Часто ищут:'),
                        Repeater::make('hero_hints')->label('Подсказки «Часто ищут» под формой')
                            ->helperText('Пусто — стандартный набор. Параметры — фильтры каталога, например: kp=at&price_max=3000 · seats=7 · awd=1 · class[]=biznes · body[]=kabriolet')
                            ->schema([
                                TextInput::make('label')->label('Текст')->required(),
                                TextInput::make('query')->label('Параметры каталога')->placeholder('kp=at&price_max=3000'),
                            ])
                            ->columns(2)->reorderable()->collapsible()->defaultItems(0)->maxItems(8)->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::put($key, $value);
        }

        Notification::make()->title('Сохранено')->success()->send();
    }

    protected static function accessArea(): string
    {
        return 'content';
    }
}
