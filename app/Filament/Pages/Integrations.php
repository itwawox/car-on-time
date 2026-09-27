<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RestrictedToArea;
use App\Filament\Resources\IntegrationLogs\IntegrationLogResource;
use App\Models\Booking;
use App\Models\Setting;
use App\Services\Bitrix24\Bitrix24Client;
use App\Services\Bitrix24\Bitrix24Exception;
use App\Services\Bitrix24\Bitrix24Settings;
use App\Services\Payments\PaymentSettings;
use App\Services\Payments\YooKassaClient;
use App\Services\Sms\SmsException;
use App\Services\Sms\SmsSender;
use App\Services\Sms\SmsSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/** Подключение внешних систем: CRM, уведомления. Секреты хранятся зашифрованными. */
class Integrations extends Page
{
    use RestrictedToArea;

    protected static ?string $navigationLabel = 'Интеграции';

    protected static string|\UnitEnum|null $navigationGroup = 'Интеграции';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Интеграции';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected string $view = 'filament.pages.site-settings';

    public ?array $data = [];

    /** Обычные настройки: хранятся как есть. */
    private const PLAIN_KEYS = [
        'bitrix_enabled', 'bitrix_booking_entity', 'bitrix_lead_entity', 'bitrix_category_id',
        'bitrix_assigned_by_id', 'bitrix_source_id', 'bitrix_stage_map',
        'bitrix_booking_field_map', 'bitrix_lead_field_map', 'bitrix_inbound_enabled',
        'telegram_enabled', 'telegram_bot_token', 'telegram_chat_id', 'booking_sla_minutes', 'availability_buffer_hours', 'documents_retention_days',
        'payments_enabled', 'yookassa_shop_id', 'prepay_mode', 'prepay_value', 'yookassa_receipt', 'yookassa_vat_code',
        'sms_enabled', 'sms_provider', 'sms_login', 'sms_sender', 'sms_events',
        'sms_tpl_login_code', 'sms_tpl_created', 'sms_tpl_confirmed', 'sms_tpl_declined', 'sms_tpl_reminder', 'sms_tpl_review',
    ];

    /** Секреты: в форму не возвращаются, пустое поле при сохранении — оставить прежнее значение. */
    private const SECRET_KEYS = ['bitrix_webhook_url', 'bitrix_inbound_token', 'sms_api_key', 'sms_password', 'yookassa_secret_key'];

    public function mount(): void
    {
        $this->form->fill([
            ...collect(self::PLAIN_KEYS)->mapWithKeys(fn ($key) => [$key => Setting::get($key)])->all(),
            'bitrix_booking_entity' => Bitrix24Settings::bookingEntity(),
            'bitrix_lead_entity' => Bitrix24Settings::leadEntity(),
            'bitrix_category_id' => Bitrix24Settings::categoryId(),
            'telegram_enabled' => (bool) Setting::get('telegram_enabled', true),
            'sms_provider' => SmsSettings::provider(),
            'prepay_mode' => PaymentSettings::mode(),
            'prepay_value' => Setting::get('prepay_value') ?? 15,
            'yookassa_vat_code' => PaymentSettings::vatCode(),
            'sms_events' => Setting::get('sms_events') ?? array_keys(SmsSettings::TEMPLATES),
        ]);
    }

    /** Справочники портала, загруженные кнопкой «Проверить подключение». */
    private static function directory(string $key): array
    {
        return (array) (Setting::get('bitrix_directory')[$key] ?? []);
    }

    /**
     * Варианты списка плюс уже сохранённое значение: пока справочник портала не загружен
     * (или значение из него пропало), настройку всё равно можно сохранить, не теряя выбора.
     *
     * @param  array<int|string, string>  $options
     * @return array<int|string, string>
     */
    private static function withCurrent(array $options, mixed $state): array
    {
        if (filled($state) && ! array_key_exists($state, $options)) {
            $options[$state] = $state.' — нет в справочнике, нажмите «Проверить подключение»';
        }

        return $options;
    }

    public function form(Schema $schema): Schema
    {
        $secretHint = fn (string $key) => Setting::secret($key) ? 'Сохранён. Оставьте пустым, чтобы не менять.' : 'Не задан.';

        return $schema
            ->components([
                Section::make('Битрикс24')
                    ->description('Заявки на бронь и обращения попадают в CRM как сделки или лиды. Отправка идёт через очередь с повторами; каждый обмен виден в «Журнале обмена».')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('bitrix_enabled')->label('Отправлять заявки в Битрикс24')->columnSpanFull(),
                        TextInput::make('bitrix_webhook_url')->label('URL входящего вебхука')
                            ->password()->revealable()->columnSpanFull()
                            ->placeholder('https://ваш-портал.bitrix24.ru/rest/1/abcd1234efgh5678/')
                            ->url()
                            ->helperText(fn () => 'Битрикс24 → Разработчикам → Другое → Входящий вебхук. Права: CRM, Пользователи. '.$secretHint('bitrix_webhook_url')),
                        TextEntry::make('bitrix_status')->label('Подключение')->columnSpanFull()
                            ->state(function () {
                                $dir = (array) Setting::get('bitrix_directory', []);

                                return isset($dir['loaded_at'])
                                    ? 'Проверено '.$dir['loaded_at'].' — вебхук от имени: '.($dir['portal_user'] ?? '—')
                                    : 'Ещё не проверено — нажмите «Проверить подключение» вверху страницы.';
                            }),
                        Select::make('bitrix_booking_entity')->label('Заявка на бронь создаёт')->options(Bitrix24Settings::ENTITIES)->required()->live(),
                        Select::make('bitrix_lead_entity')->label('Обращение (перезвонить, юрлицо, сдать авто) создаёт')->options(Bitrix24Settings::ENTITIES)->required(),
                        Select::make('bitrix_category_id')->label('Воронка сделок')
                            ->options(fn ($state) => self::withCurrent(self::directory('categories') ?: [0 => 'Общая'], $state))
                            ->default(0)->live()
                            ->visible(fn (Get $get) => $get('bitrix_booking_entity') !== 'lead' || $get('bitrix_lead_entity') === 'deal'),
                        Select::make('bitrix_assigned_by_id')->label('Ответственный')
                            ->options(fn ($state) => self::withCurrent(self::directory('users'), $state))->searchable()
                            ->helperText('Пусто — ответственным станет владелец вебхука.'),
                        Select::make('bitrix_source_id')->label('Источник')
                            ->options(fn ($state) => self::withCurrent(self::directory('sources'), $state))->searchable()
                            ->helperText('Например, «Веб-сайт». Список источников — из справочника CRM.'),
                        Fieldset::make('Статус брони на сайте → стадия в CRM')
                            ->columns(3)
                            ->columnSpanFull()
                            ->schema(collect(Booking::STATUSES)->map(fn (string $label, string $status) => Select::make('bitrix_stage_map.'.$status)
                                ->label($label)
                                ->options(fn (Get $get, $state) => self::withCurrent(self::stageOptions($get), $state))
                                ->placeholder('Не менять'))->values()->all()),
                        self::fieldMap('bitrix_booking_field_map', 'Поля заявки на бронь → пользовательские поля CRM', Bitrix24Settings::BOOKING_FIELDS, 'booking'),
                        self::fieldMap('bitrix_lead_field_map', 'Поля обращения → пользовательские поля CRM', Bitrix24Settings::LEAD_FIELDS, 'lead'),
                        Section::make('Обратная синхронизация')
                            ->description('Менеджер двигает сделку по стадиям в Битрикс24 — статус брони на сайте меняется сам. Битрикс24 → Разработчикам → Исходящий вебхук, события «Обновление сделки» и «Обновление лида».')
                            ->columns(2)
                            ->columnSpanFull()
                            ->collapsed()
                            ->schema([
                                Toggle::make('bitrix_inbound_enabled')->label('Принимать изменения из CRM')->columnSpanFull(),
                                TextEntry::make('bitrix_inbound_url')->label('URL обработчика для исходящего вебхука')
                                    ->state(fn () => route('integrations.bitrix24.webhook'))->copyable()->columnSpanFull(),
                                TextInput::make('bitrix_inbound_token')->label('Токен приложения (application_token)')
                                    ->password()->revealable()->columnSpanFull()
                                    ->helperText(fn () => 'Битрикс24 показывает его при создании исходящего вебхука. '.$secretHint('bitrix_inbound_token')),
                            ]),
                    ]),
                Section::make('Онлайн-предоплата (ЮKassa)')
                    ->description('Когда менеджер подтвердил наличие (статус «Предложена клиенту» или «Подтверждена»), на странице заявки появляется кнопка «Внести предоплату». Без предоплаты заявка тоже действует.')
                    ->icon(Heroicon::OutlinedCreditCard)
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        Toggle::make('payments_enabled')->label('Принимать предоплату онлайн')->columnSpanFull(),
                        TextInput::make('yookassa_shop_id')->label('shopId магазина'),
                        TextInput::make('yookassa_secret_key')->label('Секретный ключ')->password()->revealable()
                            ->helperText(fn () => 'ЮKassa → Интеграция → Ключи API. '.$secretHint('yookassa_secret_key')),
                        Select::make('prepay_mode')->label('Размер предоплаты')->options(PaymentSettings::MODES)->required()->live(),
                        TextInput::make('prepay_value')->numeric()->minValue(0)
                            ->label(fn (Get $get) => $get('prepay_mode') === 'fixed' ? 'Сумма, ₽' : 'Процент')
                            ->visible(fn (Get $get) => $get('prepay_mode') !== 'first_day'),
                        Toggle::make('yookassa_receipt')->label('Отправлять чек по 54-ФЗ через ЮKassa')->inline(false)
                            ->helperText('Включайте, если в ЮKassa подключены чеки (онлайн-касса или «Чеки от ЮKassa»).'),
                        Select::make('yookassa_vat_code')->label('Ставка НДС в чеке')->options(PaymentSettings::VAT_CODES),
                        TextEntry::make('yookassa_webhook')->label('URL для HTTP-уведомлений (ЮKassa → Интеграция → HTTP-уведомления, события payment.succeeded и payment.canceled)')
                            ->state(fn () => route('integrations.yookassa.webhook'))->copyable()->columnSpanFull(),
                    ]),
                Section::make('SMS клиентам')
                    ->description('Подтверждение заявки со ссылкой на статус, подтверждение брони, напоминание за сутки, просьба об отзыве. Каждое SMS — в «Журнале обмена».')
                    ->icon(Heroicon::OutlinedDevicePhoneMobile)
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        Toggle::make('sms_enabled')->label('Отправлять SMS клиентам')->columnSpanFull(),
                        Select::make('sms_provider')->label('Провайдер')->options(SmsSettings::PROVIDERS)->required()->live(),
                        TextInput::make('sms_sender')->label('Имя отправителя')->placeholder('CarOnTime')
                            ->helperText('Должно быть зарегистрировано у провайдера. Пусто — отправитель по умолчанию.'),
                        TextInput::make('sms_api_key')->label('API-ключ (api_id)')->password()->revealable()
                            ->visible(fn (Get $get) => $get('sms_provider') !== 'smsc')
                            ->helperText(fn () => $secretHint('sms_api_key')),
                        TextInput::make('sms_login')->label('Логин SMSC')
                            ->visible(fn (Get $get) => $get('sms_provider') === 'smsc'),
                        TextInput::make('sms_password')->label('Пароль или API-ключ SMSC')->password()->revealable()
                            ->visible(fn (Get $get) => $get('sms_provider') === 'smsc')
                            ->helperText(fn () => $secretHint('sms_password')),
                        CheckboxList::make('sms_events')->label('Когда отправлять')
                            ->options(collect(SmsSettings::TEMPLATES)->map(fn ($t) => $t['label'])->all())
                            ->columns(2)->columnSpanFull(),
                        Textarea::make('sms_tpl_login_code')->label('Текст: код для входа в личный кабинет')->rows(2)->columnSpanFull()
                            ->placeholder('{brand}: код для входа в личный кабинет {code}. Никому его не сообщайте.')
                            ->helperText('Подстановки: {brand} {code}. Отправляется всегда, когда включены SMS, — иначе вход только по ссылке со страницы заявки.'),
                        ...collect(SmsSettings::TEMPLATES)->map(fn (array $t, string $key) => Textarea::make('sms_tpl_'.$key)
                            ->label('Текст: '.$t['label'])->rows(2)->placeholder($t['default'])->columnSpanFull()
                            ->helperText($key === 'created' ? 'Подстановки: '.SmsSettings::PLACEHOLDERS.'. Пусто — текст по умолчанию (виден серым).' : null))->values()->all(),
                    ]),
                Section::make('Заявки: сроки и занятость')
                    ->description('Контроль времени ответа и правила календаря занятости.')
                    ->icon(Heroicon::OutlinedClock)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('booking_sla_minutes')->label('Срок первого ответа на заявку, мин')->numeric()->minValue(1)->placeholder('15')
                            ->helperText('Дольше — строка краснеет, в рабочий Telegram-чат уходит напоминание.'),
                        TextInput::make('documents_retention_days')->label('Хранить документы клиентов после аренды, дней')->numeric()->minValue(1)->placeholder('30')
                            ->helperText('Паспорт и права, загруженные клиентом, удаляются автоматически по истечении срока (152-ФЗ).'),
                        TextInput::make('availability_buffer_hours')->label('Буфер между арендами, ч')->numeric()->minValue(0)->placeholder('2')
                            ->helperText('Время на мойку и подготовку: машина считается занятой ещё столько часов после возврата.'),
                    ]),
                Section::make('Telegram для менеджеров')
                    ->description('Уведомления о новых заявках, обращениях и отзывах в рабочий чат.')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        Toggle::make('telegram_enabled')->label('Отправлять уведомления в Telegram')->columnSpanFull(),
                        TextInput::make('telegram_bot_token')->label('Токен бота')->password()->revealable(),
                        TextInput::make('telegram_chat_id')->label('ID чата'),
                    ]),
            ])
            ->statePath('data');
    }

    private static function fieldMap(string $name, string $label, array $siteFields, string $kind): Repeater
    {
        return Repeater::make($name)->label($label)
            ->helperText('Необязательно: основные данные уже есть в названии, сумме, датах и комментарии. Добавьте, если в CRM заведены свои поля.')
            ->schema([
                Select::make('site')->label('На сайте')->options($siteFields)->required(),
                Select::make('crm')->label('Поле в CRM')->required()->searchable()
                    ->options(fn (Get $get, $state) => self::withCurrent(self::directory(($get('../../bitrix_'.($kind === 'lead' ? 'lead' : 'booking').'_entity') === 'lead' ? 'lead' : 'deal').'_fields'), $state))
                    ->helperText('Список полей загружается кнопкой «Проверить подключение».'),
            ])
            ->columns(2)->defaultItems(0)->collapsible()->columnSpanFull();
    }

    /** @return array<string, string> */
    private static function stageOptions(Get $get): array
    {
        if ($get('bitrix_booking_entity') === 'lead') {
            return self::directory('lead_statuses');
        }

        return (array) (self::directory('deal_stages')[(int) $get('bitrix_category_id')] ?? []);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkBitrix')
                ->label('Проверить подключение')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(fn () => $this->loadDirectory()),
            Action::make('checkYooKassa')
                ->label('Проверить ЮKassa')
                ->icon(Heroicon::OutlinedCreditCard)
                ->color('gray')
                ->action(function () {
                    $this->save(notify: false);
                    try {
                        $me = app(YooKassaClient::class)->me();
                        Notification::make()->title('ЮKassa подключена')->body('Магазин '.($me['account_id'] ?? '').($me['test'] ?? false ? ' (тестовый режим)' : ''))->success()->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('ЮKassa не ответила')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
            Action::make('testSms')
                ->label('Тестовое SMS')
                ->icon(Heroicon::OutlinedDevicePhoneMobile)
                ->color('gray')
                ->schema([TextInput::make('phone')->label('Номер')->tel()->required()->placeholder('+7 978 000-00-00')])
                ->action(function (array $data) {
                    $this->save(notify: false);
                    try {
                        app(SmsSender::class)->send($data['phone'], (string) Setting::get('brand_name', 'Car on Time').': тестовое сообщение — SMS настроены.');
                        Notification::make()->title('SMS отправлено')->success()->send();
                    } catch (SmsException $e) {
                        Notification::make()->title('Не отправлено')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
            Action::make('log')
                ->label('Журнал обмена')
                ->icon(Heroicon::OutlinedQueueList)
                ->color('gray')
                ->url(IntegrationLogResource::getUrl()),
        ];
    }

    /** Проверка вебхука и загрузка справочников портала для выпадающих списков. */
    public function loadDirectory(): void
    {
        $this->save(notify: false);

        $client = Bitrix24Client::fromSettings();
        if (! $client) {
            Notification::make()->title('Укажите URL входящего вебхука')->warning()->send();

            return;
        }

        try {
            $profile = $client->call('profile');
            $categories = collect($client->call('crm.category.list', ['entityTypeId' => 2])['categories'] ?? [])
                ->mapWithKeys(fn ($c) => [(int) $c['id'] => (string) $c['name']])->all() ?: [0 => 'Общая'];

            $stages = [];
            foreach (array_keys($categories) as $categoryId) {
                $stages[$categoryId] = $this->statuses($client, $categoryId ? 'DEAL_STAGE_'.$categoryId : 'DEAL_STAGE');
            }

            $directory = [
                'portal_user' => trim(($profile['NAME'] ?? '').' '.($profile['LAST_NAME'] ?? '')) ?: ('ID '.($profile['ID'] ?? '?')),
                'categories' => $categories,
                'deal_stages' => $stages,
                'lead_statuses' => $this->statuses($client, 'STATUS'),
                'sources' => $this->statuses($client, 'SOURCE'),
                'users' => collect($client->call('user.get', ['FILTER' => ['ACTIVE' => true]]))
                    ->mapWithKeys(fn ($u) => [(int) $u['ID'] => trim(($u['NAME'] ?? '').' '.($u['LAST_NAME'] ?? '')) ?: ($u['EMAIL'] ?? 'ID '.$u['ID'])])->all(),
                'deal_fields' => $this->customFields($client->call('crm.deal.fields')),
                'lead_fields' => $this->customFields($client->call('crm.lead.fields')),
                'loaded_at' => now()->format('d.m.Y H:i'),
            ];
        } catch (Bitrix24Exception $e) {
            Notification::make()->title('Битрикс24 не ответил')->body(Str::limit($e->getMessage(), 300))->danger()->persistent()->send();

            return;
        }

        Setting::put('bitrix_directory', $directory, 'integrations');
        Notification::make()->title('Подключено')->body('Вебхук работает от имени: '.$directory['portal_user'].'. Справочники обновлены.')->success()->send();
        $this->mount();
    }

    /** @return array<string, string> */
    private function statuses(Bitrix24Client $client, string $entityId): array
    {
        return collect($client->call('crm.status.list', ['order' => ['SORT' => 'ASC'], 'filter' => ['ENTITY_ID' => $entityId]]))
            ->mapWithKeys(fn ($s) => [(string) $s['STATUS_ID'] => (string) $s['NAME']])->all();
    }

    /** @return array<string, string> */
    private function customFields(mixed $fields): array
    {
        return collect((array) $fields)
            ->filter(fn ($meta, $code) => str_starts_with((string) $code, 'UF_CRM_'))
            ->map(fn ($meta, $code) => ($meta['formLabel'] ?? $meta['listLabel'] ?? $meta['title'] ?? $code).' ('.$code.')')
            ->all();
    }

    public function save(bool $notify = true): void
    {
        $state = $this->form->getState();

        foreach (self::PLAIN_KEYS as $key) {
            Setting::put($key, $state[$key] ?? null, 'integrations');
        }
        foreach (self::SECRET_KEYS as $key) {
            if (filled($state[$key] ?? null)) {
                Setting::putSecret($key, $state[$key]);
            }
        }

        $this->form->fill([...$state, ...array_fill_keys(self::SECRET_KEYS, null)]);

        if ($notify) {
            Notification::make()->title('Сохранено')->success()->send();
        }
    }

    protected static function accessArea(): string
    {
        return 'admin';
    }
}
