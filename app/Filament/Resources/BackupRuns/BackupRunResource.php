<?php

namespace App\Filament\Resources\BackupRuns;

use App\Filament\Concerns\RestrictedToArea;
use App\Filament\Resources\BackupRuns\Pages\ListBackupRuns;
use App\Models\BackupRun;
use App\Support\Backups;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Журнал резервного копирования базы: каждый запуск, его источник и результат, скачивание сохранившихся копий. */
class BackupRunResource extends Resource
{
    use RestrictedToArea;

    protected static ?string $model = BackupRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|\UnitEnum|null $navigationGroup = 'Сайт';

    protected static ?string $navigationLabel = 'Резервные копии';

    protected static ?string $modelLabel = 'копия базы';

    protected static ?string $pluralModelLabel = 'Резервные копии';

    protected static ?string $slug = 'backups';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $last = BackupRun::query()->latest('started_at')->first();

        return $last?->status === 'failed' ? '!' : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextEntry::make('started_at')->label('Когда')->dateTime('d.m.Y H:i:s'),
                TextEntry::make('source')->label('Источник')->state(fn (BackupRun $run) => self::sourceLabel($run)),
                TextEntry::make('status')->label('Результат')->badge()
                    ->formatStateUsing(fn ($state) => BackupRun::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => $state === 'success' ? 'success' : 'danger'),
                TextEntry::make('file')->label('Файл')->placeholder('—')->copyable(),
                TextEntry::make('size')->label('Размер')->placeholder('—')
                    ->formatStateUsing(fn (?int $state) => $state ? Backups::humanSize($state) : null),
                TextEntry::make('duration_ms')->label('Длительность')->placeholder('—')
                    ->formatStateUsing(fn (?int $state) => $state === null ? null : self::duration($state)),
                TextEntry::make('stored')->label('Хранится')->state(fn (BackupRun $run) => self::storedLabel($run)),
                TextEntry::make('error')->label('Ошибка')->columnSpanFull()->visible(fn (BackupRun $run) => filled($run->error)),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('started_at')->label('Когда')->dateTime('d.m.Y H:i')->sortable()
                    ->description(fn (BackupRun $run) => $run->started_at->diffForHumans()),
                TextColumn::make('source')->label('Источник')->state(fn (BackupRun $run) => self::sourceLabel($run)),
                TextColumn::make('status')->label('Результат')->badge()
                    ->formatStateUsing(fn ($state) => BackupRun::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => $state === 'success' ? 'success' : 'danger'),
                TextColumn::make('size')->label('Размер')->placeholder('—')->sortable()
                    ->formatStateUsing(fn (?int $state) => $state ? Backups::humanSize($state) : null),
                TextColumn::make('duration_ms')->label('Длительность')->placeholder('—')
                    ->formatStateUsing(fn (?int $state) => $state === null ? null : self::duration($state)),
                TextColumn::make('stored')->label('Файл')->state(fn (BackupRun $run) => self::storedLabel($run))
                    ->description(fn (BackupRun $run) => $run->file),
                TextColumn::make('error')->label('Ошибка')->limit(80)->wrap()->placeholder('—')->toggleable(),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Результат')->options(BackupRun::STATUSES),
                SelectFilter::make('source')->label('Источник')->options(BackupRun::SOURCES),
            ])
            ->recordActions([
                Action::make('download')->label('Скачать')->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (BackupRun $run) => Backups::path($run->file) !== null)
                    ->action(fn (BackupRun $run): ?BinaryFileResponse => ($path = Backups::path($run->file)) ? response()->download($path) : null),
                ViewAction::make()->label('')->tooltip('Подробности'),
            ])
            ->emptyStateHeading('Копий пока не было')
            ->emptyStateDescription('Ночная копия делается в '.Backups::NIGHTLY_AT.', ещё одна — перед каждой выкладкой.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBackupRuns::route('/'),
        ];
    }

    protected static function accessArea(): string
    {
        return 'admin';
    }

    private static function sourceLabel(BackupRun $run): string
    {
        $label = BackupRun::SOURCES[$run->source] ?? $run->source;

        return $run->source === 'manual' && $run->user ? $label.': '.$run->user->name : $label;
    }

    private static function storedLabel(BackupRun $run): string
    {
        return match (true) {
            $run->file === null => 'не создан',
            Backups::path($run->file) !== null => 'на сервере',
            default => 'удалена: хранятся последние '.Backups::KEEP,
        };
    }

    private static function duration(int $ms): string
    {
        return $ms < 1000 ? $ms.' мс' : number_format($ms / 1000, 1, ',', ' ').' с';
    }
}
