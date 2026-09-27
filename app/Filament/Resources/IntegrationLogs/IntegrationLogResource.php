<?php

namespace App\Filament\Resources\IntegrationLogs;

use App\Filament\Concerns\RestrictedToArea;
use App\Filament\Resources\IntegrationLogs\Pages\ListIntegrationLogs;
use App\Jobs\PushToBitrix24;
use App\Models\Booking;
use App\Models\IntegrationLog;
use App\Models\Lead;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Журнал обмена с внешними системами: только чтение и повтор отправки. */
class IntegrationLogResource extends Resource
{
    use RestrictedToArea;

    protected static ?string $model = IntegrationLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|\UnitEnum|null $navigationGroup = 'Интеграции';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Журнал обмена';

    protected static ?string $modelLabel = 'запись журнала';

    protected static ?string $pluralModelLabel = 'Журнал обмена';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $failed = IntegrationLog::query()->where('status', 'failed')->where('created_at', '>', now()->subDay())->count();

        return $failed ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextEntry::make('created_at')->label('Когда')->dateTime('d.m.Y H:i:s'),
                TextEntry::make('integration')->label('Система')->formatStateUsing(fn ($state) => IntegrationLog::INTEGRATIONS[$state] ?? $state),
                TextEntry::make('event')->label('Событие'),
                TextEntry::make('status')->label('Результат')->badge()->formatStateUsing(fn ($state) => IntegrationLog::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['success' => 'success', 'failed' => 'danger'][$state] ?? 'gray'),
                TextEntry::make('duration_ms')->label('Время ответа')->suffix(' мс'),
                TextEntry::make('subject')->label('Запись')->state(fn (IntegrationLog $log) => self::subjectLabel($log)),
                TextEntry::make('error')->label('Ошибка')->columnSpanFull()->visible(fn (IntegrationLog $log) => filled($log->error)),
                CodeEntry::make('request')->label('Запрос')->columnSpanFull()
                    ->state(fn (IntegrationLog $log) => json_encode($log->request, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                CodeEntry::make('response')->label('Ответ')->columnSpanFull()
                    ->state(fn (IntegrationLog $log) => json_encode($log->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Когда')->dateTime('d.m H:i:s')->sortable(),
                TextColumn::make('integration')->label('Система')->formatStateUsing(fn ($state) => IntegrationLog::INTEGRATIONS[$state] ?? $state),
                TextColumn::make('event')->label('Событие'),
                TextColumn::make('subject_id')->label('Запись')->state(fn (IntegrationLog $log) => self::subjectLabel($log)),
                TextColumn::make('status')->label('Результат')->badge()->formatStateUsing(fn ($state) => IntegrationLog::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => ['success' => 'success', 'failed' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('error')->label('Ошибка')->limit(80)->wrap()->toggleable(),
                TextColumn::make('duration_ms')->label('мс')->numeric()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Результат')->options(IntegrationLog::STATUSES),
                SelectFilter::make('integration')->label('Система')->options(IntegrationLog::INTEGRATIONS),
            ])
            ->recordActions([
                ViewAction::make()->label('')->tooltip('Подробности'),
                Action::make('retry')->label('Повторить')->icon(Heroicon::OutlinedArrowPath)->color('warning')
                    ->visible(fn (IntegrationLog $log) => $log->status === 'failed' && $log->integration === 'bitrix24'
                        && ($log->subject instanceof Booking || $log->subject instanceof Lead))
                    ->action(function (IntegrationLog $log) {
                        PushToBitrix24::dispatch($log->subject);
                        Notification::make()->title('Отправка поставлена в очередь')->success()->send();
                    }),
            ]);
    }

    private static function subjectLabel(IntegrationLog $log): ?string
    {
        return match ($log->subject_type) {
            Booking::class => 'Бронь №'.$log->subject_id,
            Lead::class => 'Обращение №'.$log->subject_id,
            default => $log->subject_id ? class_basename((string) $log->subject_type).' #'.$log->subject_id : null,
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIntegrationLogs::route('/'),
        ];
    }

    protected static function accessArea(): string
    {
        return 'admin';
    }
}
