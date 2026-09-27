<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\User;
use App\Support\Kpi;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Главные цифры за 30 дней: поток заявок, конверсия, скорость ответа, выручка, загрузка. */
class KpiOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'За 30 дней';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canUseArea('bookings');
    }

    protected function getStats(): array
    {
        $kpi = Kpi::lastDays(30);
        $daily = $kpi->daily();
        $waiting = Booking::query()->where('status', 'new')->count();
        $overdue = Booking::query()->where('status', 'new')->where('created_at', '<', now()->subMinutes(Booking::slaMinutes()))->count();
        $median = $kpi->medianResponseMinutes();
        $conversion = $kpi->conversion();
        $sla = $kpi->slaHitRate();
        $utilization = Kpi::utilizationAhead(7);

        return [
            Stat::make('Ждут ответа сейчас', $waiting)
                ->description($overdue ? 'Просрочено: '.$overdue : 'Все в срок')
                ->descriptionIcon($overdue ? Heroicon::ExclamationTriangle : Heroicon::CheckCircle)
                ->color($overdue ? 'danger' : 'success')
                ->url(BookingResource::getUrl()),
            Stat::make('Заявки', $kpi->received())
                ->description('Подтверждено: '.$kpi->confirmed())
                ->chart(array_slice($daily['received'], -14))
                ->color('primary'),
            Stat::make('Конверсия в аренду', $conversion === null ? '—' : $conversion.'%')
                ->description('Доля заявок, ставших подтверждённой арендой'),
            Stat::make('Первый ответ, медиана', $median === null ? '—' : $median.' мин')
                ->description($sla === null ? 'Пока нет ответов' : 'В срок '.Booking::slaMinutes().' мин: '.$sla.'%')
                ->color($median !== null && $median > Booking::slaMinutes() ? 'danger' : 'success'),
            Stat::make('Выручка подтверждённых', number_format($kpi->revenue(), 0, ',', ' ').' ₽')
                ->description('Сумма аренд по подтверждённым заявкам'),
            Stat::make('Загрузка на 7 дней', $utilization === null ? '—' : $utilization.'%')
                ->description('Занятые машино-сутки из доступных'),
        ];
    }
}
