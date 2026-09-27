<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Support\Kpi;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/** Выручка и комиссия по партнёрам за текущий месяц — только владельцу. */
class PartnerRevenue extends Widget
{
    protected static ?int $sort = 3;

    protected string $view = 'filament.widgets.partner-revenue';

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canUseArea('admin');
    }

    /** @return Collection<int, array{partner: string, bookings: int, revenue: int, commission: int}> */
    public function rows(): Collection
    {
        return (new Kpi(now()->startOfMonth(), now()->endOfDay()))->partners();
    }
}
