<?php

namespace App\Filament\Resources\Bookings;

use App\Filament\Concerns\RestrictedToArea;
use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\RelationManagers\EventsRelationManager;
use App\Models\Booking;
use App\Models\Payment;
use App\Support\Attribution;
use App\Support\Availability;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Заявки на бронь как в CRM: статусы, контроль времени ответа, быстрые действия, история. */
class BookingResource extends Resource
{
    use RestrictedToArea;

    protected static ?string $model = Booking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Заявки';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Бронирования';

    protected static ?string $modelLabel = 'заявка';

    protected static ?string $pluralModelLabel = 'Бронирования';

    protected static bool $hasTitleCaseModelLabel = false;

    public const STATUS_COLORS = [
        'new' => 'warning',
        'checking' => 'info',
        'offered' => 'info',
        'alternative' => 'info',
        'confirmed' => 'success',
        'declined' => 'gray',
    ];

    public static function getNavigationBadge(): ?string
    {
        $count = Booking::query()->where('status', 'new')->count();

        return $count ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $overdue = Booking::query()->where('status', 'new')->where('created_at', '<', now()->subMinutes(Booking::slaMinutes()))->exists();

        return $overdue ? 'danger' : 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Клиент')->columns(3)->columnSpanFull()->schema([
                TextInput::make('phone')->label('Телефон')->tel()->required(),
                TextInput::make('customer_name')->label('Имя'),
                Select::make('status')->label('Статус')->options(Booking::STATUSES)->required()->default('new'),
                Toggle::make('telegram')->label('Удобно в Telegram'),
                Toggle::make('max')->label('Удобно в MAX'),
                Toggle::make('whatsapp')->label('Удобно в WhatsApp'),
            ]),
            Section::make('Аренда')->columns(2)->columnSpanFull()->schema([
                Select::make('car_id')->label('Машина')->relationship('car', 'name')->searchable()->preload()
                    ->helperText(fn (?Booking $record) => self::conflictHint($record)),
                Select::make('partner_id')->label('Партнёр')->relationship('partner', 'name')->searchable()->preload(),
                DateTimePicker::make('starts_at')->label('Выдача')->seconds(false)->required(),
                DateTimePicker::make('ends_at')->label('Возврат')->seconds(false)->required()->after('starts_at'),
                Select::make('pickup_location_id')->label('Место выдачи')->relationship('pickupLocation', 'name')->searchable()->preload(),
                Select::make('return_location_id')->label('Место возврата')->relationship('returnLocation', 'name')->searchable()->preload(),
                TextInput::make('total')->label('Сумма, ₽')->numeric()->required()->default(0),
                TextInput::make('promo_code')->label('Промокод'),
            ]),
            Section::make('Расчёт на сайте')->columnSpanFull()->collapsed()
                ->visible(fn (?Booking $record) => filled($record?->quote_snapshot['human'] ?? null))
                ->schema([
                    TextEntry::make('quote_human')->hiddenLabel()
                        ->state(fn (?Booking $record) => $record?->quote_snapshot['human'] ?? null)
                        ->formatStateUsing(fn (?string $state) => nl2br(e($state)))->html(),
                    TextEntry::make('extras_list')->label('Доп. услуги')
                        ->state(fn (?Booking $record) => $record?->extras ? implode(', ', array_column($record->extras, 'name')) : '—'),
                    TextEntry::make('source')->label('Откуда заявка'),
                    TextEntry::make('traffic')->label('Источник трафика')
                        ->state(fn (?Booking $record) => Attribution::label($record?->utm) ?? 'Прямой заход или неизвестно'),
                    TextEntry::make('quiz_answers')->label('Ответы в подборе')->visible(fn (?Booking $record) => filled($record?->quiz_answers)),
                ]),
            Section::make('Онлайн-оплата')->columnSpanFull()->collapsed()
                ->visible(fn (?Booking $record) => $record?->payments()->exists())
                ->schema([
                    TextEntry::make('payments_list')->hiddenLabel()->listWithLineBreaks()
                        ->state(fn (?Booking $record) => $record?->payments()->latest()->get()->map(fn ($p) => $p->created_at->format('d.m H:i').' — '
                            .number_format($p->amount, 0, ',', ' ').' ₽ — '.(Payment::STATUSES[$p->status] ?? $p->status)
                            .($p->paid_at ? ', оплачено '.$p->paid_at->format('d.m H:i') : ''))->all()),
                ]),
            Section::make('Документы клиента')->columnSpanFull()->collapsed()
                ->description(fn (?Booking $record) => $record?->documents_deleted_at
                    ? 'Удалены по сроку хранения '.$record->documents_deleted_at->format('d.m.Y').'.'
                    : 'Паспорт и права, которые клиент загрузил на странице заявки. Хранятся закрыто и удаляются автоматически после аренды.')
                ->schema([
                    SpatieMediaLibraryFileUpload::make('documents')->hiddenLabel()->collection('documents')
                        ->disk('local')->visibility('private')->multiple()->downloadable()->openable()
                        ->acceptedFileTypes(['image/*', 'application/pdf'])->maxSize(10240),
                ]),
            Section::make('Акт осмотра')->columns(3)->columnSpanFull()->collapsed()
                ->description('Фото и показания при выдаче и возврате. Клиент видит их на странице заявки.')
                ->schema([
                    TextInput::make('pickup_mileage')->label('Пробег при выдаче, км')->numeric()->minValue(0),
                    TextInput::make('pickup_fuel')->label('Топливо при выдаче, %')->numeric()->minValue(0)->maxValue(100),
                    SpatieMediaLibraryFileUpload::make('act_pickup')->label('Фото при выдаче')->collection('act_pickup')
                        ->disk('local')->visibility('private')->image()->multiple()->reorderable()->openable()->maxSize(15360),
                    TextInput::make('return_mileage')->label('Пробег при возврате, км')->numeric()->minValue(0),
                    TextInput::make('return_fuel')->label('Топливо при возврате, %')->numeric()->minValue(0)->maxValue(100),
                    SpatieMediaLibraryFileUpload::make('act_return')->label('Фото при возврате')->collection('act_return')
                        ->disk('local')->visibility('private')->image()->multiple()->reorderable()->openable()->maxSize(15360),
                    Textarea::make('inspection_notes')->label('Замечания (царапины, сколы)')->rows(2)->columnSpanFull(),
                ]),
            Textarea::make('notes')->label('Заметки менеджера')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('№')->sortable()->searchable(),
                TextColumn::make('created_at')->label('Получена')->since()->sortable()
                    ->description(fn (Booking $b) => $b->created_at?->format('d.m H:i'))
                    ->color(fn (Booking $b) => self::slaColor($b))
                    ->tooltip(fn (Booking $b) => ($m = $b->waitingMinutes()) !== null ? 'Ждёт ответа '.$m.' мин' : null),
                TextColumn::make('phone')->label('Клиент')->searchable(['phone', 'customer_name'])->copyable()
                    ->description(fn (Booking $b) => $b->customer_name),
                TextColumn::make('car.name')->label('Машина')->searchable()->wrap()
                    ->description(fn (Booking $b) => $b->partner?->name),
                TextColumn::make('starts_at')->label('Даты')->sortable()
                    ->formatStateUsing(fn (Booking $b) => $b->starts_at?->format('d.m H:i').' — '.$b->ends_at?->format('d.m H:i'))
                    ->description(fn (Booking $b) => $b->pickupLocation?->name),
                TextColumn::make('total')->label('Сумма')->money('RUB', decimalPlaces: 0)->sortable()
                    ->description(fn (Booking $record) => $record->prepaid_amount ? 'оплачено '.number_format($record->prepaid_amount, 0, ',', ' ').' ₽' : null),
                TextColumn::make('documents_uploaded_at')->label('Док.')->toggleable()
                    ->state(fn (Booking $record) => $record->documents_uploaded_at && ! $record->documents_deleted_at ? '📄' : null)
                    ->tooltip('Клиент загрузил документы'),
                TextColumn::make('status')->label('Статус')->badge()
                    ->formatStateUsing(fn ($state) => Booking::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => self::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('source')->label('Откуда')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('utm')->label('Трафик')->toggleable(isToggledHiddenByDefault: true)
                    ->state(fn (Booking $record) => Attribution::label($record->utm)),
                TextColumn::make('crm_id')->label('CRM')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['car', 'partner', 'pickupLocation']))
            ->filters([
                SelectFilter::make('status')->label('Статус')->options(Booking::STATUSES)->multiple(),
                SelectFilter::make('partner_id')->label('Партнёр')->relationship('partner', 'name'),
                SelectFilter::make('car_id')->label('Машина')->relationship('car', 'name')->searchable(),
                Filter::make('starts')->label('Выдача')
                    ->schema([
                        DatePicker::make('from')->label('Выдача с'),
                        DatePicker::make('until')->label('по'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('starts_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('starts_at', '<=', $d))),
            ])
            ->recordActions([
                Action::make('call')->label('')->tooltip('Позвонить')->icon(Heroicon::OutlinedPhone)
                    ->url(fn (Booking $b) => 'tel:'.preg_replace('/[^\d+]/', '', $b->phone)),
                Action::make('whatsapp')->label('')->tooltip('Написать в WhatsApp')->icon(Heroicon::OutlinedChatBubbleOvalLeft)
                    ->url(fn (Booking $b) => 'https://wa.me/'.preg_replace('/\D/', '', $b->phone), shouldOpenInNewTab: true),
                ActionGroup::make([
                    self::statusAction('checking', 'Уточняем у партнёра', Heroicon::OutlinedMagnifyingGlass),
                    self::statusAction('offered', 'Предложили клиенту', Heroicon::OutlinedPaperAirplane),
                    self::statusAction('confirmed', 'Подтвердить', Heroicon::OutlinedCheckCircle)->color('success'),
                    self::statusAction('alternative', 'Предложили альтернативу', Heroicon::OutlinedArrowsRightLeft),
                    self::statusAction('declined', 'Отказ', Heroicon::OutlinedXCircle)->color('danger')
                        ->schema([Textarea::make('comment')->label('Причина')->required()->rows(2)]),
                ])->label('Статус')->icon(Heroicon::OutlinedArrowPath)->button()->size('sm'),
                EditAction::make()->label('')->tooltip('Открыть'),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    /** Быстрая смена статуса из списка: подтверждение проверяет, не занята ли машина. */
    private static function statusAction(string $status, string $label, Heroicon $icon): Action
    {
        return Action::make('status_'.$status)->label($label)->icon($icon)
            ->visible(fn (Booking $b) => $b->status !== $status)
            ->action(function (Booking $b, array $data) use ($status) {
                if (! self::changeStatus($b, $status, $data['comment'] ?? null)) {
                    return;
                }
                Notification::make()->title('Заявка №'.$b->id.': '.mb_strtolower(Booking::STATUSES[$status]))->success()->send();
            });
    }

    /**
     * Смена статуса с проверкой занятости при подтверждении. false — машина занята, статус не изменён.
     */
    public static function changeStatus(Booking $booking, string $status, ?string $comment = null): bool
    {
        if ($status === 'confirmed' && ($conflict = self::conflicts($booking))) {
            Notification::make()->title('Машина занята на эти даты')->body($conflict.'. Выберите другую машину или даты.')->danger()->persistent()->send();

            return false;
        }

        $booking->update(['status' => $status]);
        if (filled($comment)) {
            $booking->events()->create(['type' => 'note', 'comment' => $comment, 'user_id' => auth()->id()]);
        }

        return true;
    }

    /** Описание пересечений с другими бронями и блокировками или null, если машина свободна. */
    public static function conflicts(Booking $booking): ?string
    {
        if (! $booking->car || ! $booking->starts_at || ! $booking->ends_at) {
            return null;
        }
        $conflicts = Availability::conflicts($booking->car, $booking->starts_at, $booking->ends_at, $booking->id);

        return $conflicts->isEmpty() ? null : 'Пересекается: '.Availability::describe($conflicts);
    }

    private static function conflictHint(?Booking $record): ?string
    {
        if (! $record) {
            return null;
        }

        return self::conflicts($record) ?? ($record->car_id ? 'Свободна на эти даты' : null);
    }

    private static function slaColor(Booking $b): ?string
    {
        $minutes = $b->waitingMinutes();
        if ($minutes === null) {
            return null;
        }

        return $minutes >= Booking::slaMinutes() ? 'danger' : 'warning';
    }

    public static function getRelations(): array
    {
        return [EventsRelationManager::class];
    }

    protected static function accessArea(): string
    {
        return 'bookings';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookings::route('/'),
            'create' => CreateBooking::route('/create'),
            'edit' => EditBooking::route('/{record}/edit'),
        ];
    }
}
