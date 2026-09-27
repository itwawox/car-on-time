<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Машина занята: подтверждённая бронь (booking_id) или ручная блокировка (ремонт, у владельца, аренда вне сайта). */
#[Fillable(['car_id', 'booking_id', 'starts_at', 'ends_at', 'reason'])]
class CarBlock extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Car, $this> */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** Пересекается с периодом [start, end) с учётом времени на подготовку машины между арендами. */
    public function scopeOverlapping(Builder $query, CarbonInterface $start, CarbonInterface $end, int $bufferHours = 0): Builder
    {
        return $query
            ->where('starts_at', '<', $end->copy()->addHours($bufferHours))
            ->where('ends_at', '>', $start->copy()->subHours($bufferHours));
    }
}
