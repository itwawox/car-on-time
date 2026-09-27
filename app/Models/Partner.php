<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'phone', 'email', 'notes', 'commission_percent', 'reliability', 'is_active'])]
class Partner extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'commission_percent' => 'decimal:2',
        ];
    }

    /** @return HasMany<Car, $this> */
    public function cars(): HasMany
    {
        return $this->hasMany(Car::class);
    }
}
