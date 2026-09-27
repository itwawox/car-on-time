<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Car;
use App\Models\CarBlock;
use App\Models\Partner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Показатели для дашборда руководителя. Периоды — по дате поступления заявки. */
class Kpi
{
    public function __construct(public Carbon $from, public Carbon $to) {}

    public static function lastDays(int $days): self
    {
        return new self(now()->subDays($days - 1)->startOfDay(), now()->endOfDay());
    }

    /** @return Collection<int, Booking> */
    private function bookings(): Collection
    {
        return once(fn () => Booking::query()->whereBetween('created_at', [$this->from, $this->to])
            ->get(['id', 'status', 'total', 'partner_id', 'car_id', 'created_at', 'first_response_at', 'starts_at']));
    }

    public function received(): int
    {
        return $this->bookings()->count();
    }

    public function confirmed(): int
    {
        return $this->bookings()->where('status', 'confirmed')->count();
    }

    /** Доля заявок, ставших подтверждённой арендой, %. */
    public function conversion(): ?float
    {
        $decided = $this->bookings()->count();

        return $decided ? round($this->confirmed() / $decided * 100, 1) : null;
    }

    /** Медиана времени до первого ответа менеджера, мин. */
    public function medianResponseMinutes(): ?int
    {
        $minutes = $this->bookings()->filter(fn (Booking $b) => $b->first_response_at)
            ->map(fn (Booking $b) => (int) $b->created_at->diffInMinutes($b->first_response_at))
            ->sort()->values();

        if ($minutes->isEmpty()) {
            return null;
        }
        $middle = intdiv($minutes->count(), 2);

        return $minutes->count() % 2 ? $minutes[$middle] : (int) round(($minutes[$middle - 1] + $minutes[$middle]) / 2);
    }

    /** Доля заявок, на которые ответили в срок, %. */
    public function slaHitRate(): ?float
    {
        $answered = $this->bookings()->filter(fn (Booking $b) => $b->first_response_at);
        if ($answered->isEmpty()) {
            return null;
        }
        $inTime = $answered->filter(fn (Booking $b) => $b->created_at->diffInMinutes($b->first_response_at) <= Booking::slaMinutes());

        return round($inTime->count() / $answered->count() * 100, 1);
    }

    /** Сумма подтверждённых аренд, ₽. */
    public function revenue(): int
    {
        return (int) $this->bookings()->where('status', 'confirmed')->sum('total');
    }

    /**
     * Выручка и комиссия по партнёрам (подтверждённые заявки периода).
     *
     * @return Collection<int, array{partner: string, bookings: int, revenue: int, commission: int}>
     */
    public function partners(): Collection
    {
        $names = Partner::query()->get(['id', 'name', 'commission_percent'])->keyBy('id');

        return $this->bookings()->where('status', 'confirmed')->groupBy('partner_id')
            ->map(function (Collection $rows, $partnerId) use ($names) {
                $partner = $names[$partnerId] ?? null;
                $revenue = (int) $rows->sum('total');

                return [
                    'partner' => $partner?->name ?? 'Без партнёра',
                    'bookings' => $rows->count(),
                    'revenue' => $revenue,
                    'commission' => (int) round($revenue * (float) ($partner?->commission_percent ?? 0) / 100),
                ];
            })
            ->sortByDesc('revenue')->values();
    }

    /**
     * Заявки и подтверждения по дням.
     *
     * @return array{labels: list<string>, received: list<int>, confirmed: list<int>}
     */
    public function daily(): array
    {
        $byDay = $this->bookings()->groupBy(fn (Booking $b) => $b->created_at->toDateString());
        $out = ['labels' => [], 'received' => [], 'confirmed' => []];
        for ($day = $this->from->copy(); $day <= $this->to; $day->addDay()) {
            $rows = $byDay[$day->toDateString()] ?? collect();
            $out['labels'][] = $day->format('d.m');
            $out['received'][] = $rows->count();
            $out['confirmed'][] = $rows->where('status', 'confirmed')->count();
        }

        return $out;
    }

    /** Загрузка парка на ближайшие дни: занятые машино-сутки к доступным, %. */
    public static function utilizationAhead(int $days = 7): ?float
    {
        $cars = Car::query()->published()->count();
        if (! $cars) {
            return null;
        }
        $start = now()->startOfDay();
        $end = $start->copy()->addDays($days);

        $busyHours = CarBlock::query()->overlapping($start, $end)->get()
            ->sum(fn (CarBlock $b) => max($b->starts_at, $start)->diffInHours(min($b->ends_at, $end)));

        return round(min(100, $busyHours / ($cars * $days * 24) * 100), 1);
    }
}
