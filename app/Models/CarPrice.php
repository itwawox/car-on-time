<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['car_id', 'season_id', 'days_from', 'days_to', 'price'])]
class CarPrice extends Model
{
    /** @return BelongsTo<Car, $this> */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    /** @return BelongsTo<Season, $this> */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function matchesDays(int $days): bool
    {
        if ($days < $this->days_from) {
            return false;
        }

        if ($this->days_to === null) {
            return true;
        }

        return $days <= $this->days_to;
    }
}
