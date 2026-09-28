<?php

namespace App\Filament\Resources\BackupRuns\Pages;

use App\Filament\Resources\BackupRuns\BackupRunResource;
use App\Filament\Resources\BackupRuns\Widgets\BackupStorageOverview;
use App\Filament\Widgets\SystemHealthOverview;
use App\Models\BackupRun;
use App\Support\Backups;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;

class ListBackupRuns extends ListRecords
{
    protected static string $resource = BackupRunResource::class;

    public function mount(): void
    {
        Backups::registerUntracked();

        parent::mount();
    }

    protected function getHeaderWidgets(): array
    {
        return [SystemHealthOverview::class, BackupStorageOverview::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backupNow')
                ->label('Сделать копию сейчас')
                ->icon(Heroicon::OutlinedCircleStack)
                ->requiresConfirmation()
                ->modalHeading('Сделать копию базы сейчас?')
                ->modalDescription('Копия займёт несколько секунд, сайт продолжит работать. Хранятся последние '.Backups::KEEP.' копий — самая старая удалится.')
                ->modalSubmitActionLabel('Сделать копию')
                ->action(function (): void {
                    $code = Artisan::call('backup:database', ['--source' => 'manual', '--user' => auth()->id()]);
                    $run = BackupRun::query()->latest('id')->first();

                    if ($code === 0 && $run?->status === 'success') {
                        Notification::make()->title('Копия готова')->body($run->file.' · '.Backups::humanSize((int) $run->size))->success()->send();

                        return;
                    }

                    Notification::make()->title('Копию сделать не удалось')
                        ->body($run->error ?? 'Подробности — в журнале ниже')
                        ->danger()->persistent()->send();
                }),
        ];
    }
}
