<?php

namespace App\Filament\Resources\BackupRuns\Widgets;

use App\Models\BackupRun;
use App\Support\Backups;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Копии на диске сервера: сколько, сколько места, когда следующая, как прошли последние 30 дней. */
class BackupStorageOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Хранилище копий';

    protected function getStats(): array
    {
        $files = Backups::files();
        $free = Backups::freeSpace();
        $next = Backups::nextNightly();
        $month = BackupRun::query()->where('started_at', '>=', now()->subDays(30));
        $failed = (clone $month)->where('status', 'failed')->count();

        return [
            Stat::make('Копий на сервере', $files->count().' из '.Backups::KEEP)
                ->description($files->isEmpty() ? 'Пока ни одной' : 'Самая старая — '.$files->last()['at']->format('d.m.Y H:i')),
            Stat::make('Занимают', Backups::humanSize(Backups::totalSize()))
                ->description($free === null ? 'Свободное место на хостинге неизвестно' : 'Свободно на хостинге: '.Backups::humanSize($free)),
            Stat::make('Следующая ночная копия', $next->format('d.m H:i'))
                ->description($next->diffForHumans()),
            Stat::make('За 30 дней', (clone $month)->where('status', 'success')->count().' успешно')
                ->description($failed ? 'Ошибок: '.$failed.' — смотрите журнал ниже' : 'Без ошибок')
                ->color($failed ? 'danger' : 'success'),
        ];
    }
}
