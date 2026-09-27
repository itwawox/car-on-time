<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Car;
use App\Models\CarBlock;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Занятость машин: подтверждённые брони и ручные блокировки из календаря.
 * Между арендами закладывается время на мойку и подготовку (настройка «Буфер между арендами»).
 */
class Availability
{
    public static function bufferHours(): int
    {
        return max(0, (int) Setting::get('availability_buffer_hours', 2));
    }

    /**
     * Какие из машин заняты в период.
     *
     * @param  iterable<int>|null  $carIds  null — все машины
     * @return list<int>
     */
    public static function busyCarIds(CarbonInterface $start, CarbonInterface $end, ?iterable $carIds = null, ?int $exceptBookingId = null): array
    {
        return CarBlock::query()
            ->overlapping($start, $end, self::bufferHours())
            ->when($carIds !== null, fn ($q) => $q->whereIn('car_id', collect($carIds)->all()))
            ->when($exceptBookingId, fn ($q) => $q->where(fn ($q) => $q->whereNull('booking_id')->orWhere('booking_id', '!=', $exceptBookingId)))
            ->distinct()
            ->pluck('car_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function isFree(Car $car, CarbonInterface $start, CarbonInterface $end, ?int $exceptBookingId = null): bool
    {
        return self::conflicts($car, $start, $end, $exceptBookingId)->isEmpty();
    }

    /** @return Collection<int, CarBlock> */
    public static function conflicts(Car $car, CarbonInterface $start, CarbonInterface $end, ?int $exceptBookingId = null): Collection
    {
        return CarBlock::query()
            ->where('car_id', $car->id)
            ->overlapping($start, $end, self::bufferHours())
            ->when($exceptBookingId, fn ($q) => $q->where(fn ($q) => $q->whereNull('booking_id')->orWhere('booking_id', '!=', $exceptBookingId)))
            ->orderBy('starts_at')
            ->get();
    }

    /** Человеческое описание конфликтов для менеджера: «бронь №12, 03.10 10:00 — 06.10 10:00». */
    public static function describe(Collection $conflicts): string
    {
        return $conflicts->map(fn (CarBlock $b) => ($b->booking_id ? 'бронь №'.$b->booking_id : ($b->reason ?: 'блокировка'))
            .', '.$b->starts_at->format('d.m H:i').' — '.$b->ends_at->format('d.m H:i'))->implode('; ');
    }

    /** Подтверждённая бронь занимает машину; любая другая — освобождает. */
    public static function syncBlockFor(Booking $booking): void
    {
        if ($booking->status === 'confirmed' && $booking->car_id && $booking->starts_at && $booking->ends_at) {
            CarBlock::query()->updateOrCreate(
                ['booking_id' => $booking->id],
                ['car_id' => $booking->car_id, 'starts_at' => $booking->starts_at, 'ends_at' => $booking->ends_at, 'reason' => 'Бронь №'.$booking->id],
            );

            return;
        }

        CarBlock::query()->where('booking_id', $booking->id)->delete();
    }
}
