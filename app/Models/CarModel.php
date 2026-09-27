<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'brand_id', 'name', 'slug', 'body_type_id', 'drivetrain', 'drivetrain_note', 'fuel', 'fuel_note',
    'seats', 'trunk_l', 'clearance_mm', 'overview', 'strengths', 'is_verified',
    'power_hp', 'power_hp_max', 'engine_l', 'consumption_city', 'consumption_highway', 'consumption_mixed',
    'tank_l', 'fuel_grade', 'diesel_power_hp', 'diesel_engine_l', 'diesel_consumption',
])]
class CarModel extends Model
{
    protected function casts(): array
    {
        return [
            'strengths' => 'array',
            'is_verified' => 'boolean',
        ];
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<BodyType, $this> */
    public function bodyType(): BelongsTo
    {
        return $this->belongsTo(BodyType::class);
    }

    /** @return HasMany<Car, $this> */
    public function cars(): HasMany
    {
        return $this->hasMany(Car::class);
    }

    public function fullName(): string
    {
        return trim(($this->brand?->name ?? '').' '.$this->name);
    }
}
