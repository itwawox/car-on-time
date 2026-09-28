<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\BackupRuns\BackupRunResource;
use App\Models\User;
use App\Support\Backups;
use App\Support\SystemHealth;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Фоновая работа сайта: делаются ли копии базы и срабатывает ли CRON. Только владельцу. */
class SystemHealthOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Сайт';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canUseArea('admin');
    }

    protected function getStats(): array
    {
        $url = BackupRunResource::getUrl();

        return [$this->backupStat()->url($url), $this->schedulerStat()->url($url)];
    }

    private function backupStat(): Stat
    {
        $backup = SystemHealth::lastBackup();
        if ($backup === null) {
            return Stat::make('Последняя копия базы', 'Копий нет')
                ->description('Проверьте CRON и журнал ошибок: копия делается каждую ночь в 03:30 и перед выкладкой')
                ->descriptionIcon(Heroicon::ExclamationTriangle)
                ->color('danger');
        }

        $fresh = SystemHealth::backupIsFresh();

        return Stat::make('Последняя копия базы', $backup['at']->diffForHumans())
            ->description($fresh
                ? $backup['at']->format('d.m H:i').' · '.Backups::humanSize($backup['size']).' · хранятся последние 14'
                : 'Копия устарела: проверьте CRON и журнал ошибок')
            ->descriptionIcon($fresh ? Heroicon::CheckCircle : Heroicon::ExclamationTriangle)
            ->color($fresh ? 'success' : 'danger');
    }

    private function schedulerStat(): Stat
    {
        $lastRun = SystemHealth::schedulerLastRun();
        $alive = SystemHealth::schedulerIsAlive();

        return Stat::make('Планировщик (CRON)', $lastRun?->diffForHumans() ?? 'ни разу')
            ->description($alive
                ? 'Срабатывает каждую минуту: очередь, напоминания, ночные копии'
                : 'CRON не запускается: в панели хостинга нужно задание «…/artisan schedule:run» каждую минуту')
            ->descriptionIcon($alive ? Heroicon::CheckCircle : Heroicon::ExclamationTriangle)
            ->color($alive ? 'success' : 'danger');
    }
}
