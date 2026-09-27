<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['name', 'starts_month', 'starts_day', 'ends_month', 'ends_day', 'sort'])]
class Season extends Model
{
    /** @return HasMany<CarPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(CarPrice::class);
    }

    public function contains(Carbon $date): bool
    {
        $year = $date->year;
        $start = Carbon::create($year, $this->starts_month, $this->starts_day)->startOfDay();
        $end = Carbon::create($year, $this->ends_month, $this->ends_day)->endOfDay();

        if ($start->lte($end)) {
            return $date->betweenIncluded($start, $end);
        }

        $endNext = $end->copy()->addYear();
        $startPrev = $start->copy()->subYear();

        return $date->gte($start) || $date->lte($end) || $date->betweenIncluded($startPrev, $end);
    }
}
