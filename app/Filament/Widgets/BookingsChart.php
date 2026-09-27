<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Support\Kpi;
use Filament\Widgets\ChartWidget;

class BookingsChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Заявки и подтверждения по дням';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canUseArea('bookings');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $daily = Kpi::lastDays(30)->daily();

        return [
            'labels' => $daily['labels'],
            'datasets' => [
                ['label' => 'Заявки', 'data' => $daily['received'], 'tension' => 0.3],
                ['label' => 'Подтверждены', 'data' => $daily['confirmed'], 'tension' => 0.3, 'borderColor' => '#2A9D8F', 'backgroundColor' => 'rgba(42,157,143,.15)'],
            ],
        ];
    }
}
