<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name', 'slug', 'short_name', 'type', 'hours_from', 'hours_to',
    'price_1_day', 'price_2_days', 'price_3plus', 'night_price',
    'is_default_pickup', 'seo_enabled', 'seo_title', 'seo_description',
    'intro', 'sort', 'is_active',
])]
class Location extends Model
{
    protected function casts(): array
    {
        return [
            'is_default_pickup' => 'boolean',
            'seo_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function deliveryPrice(int $days, bool $night = false): int
    {
        if ($night && $this->night_price) {
            return (int) $this->night_price;
        }

        if ($days <= 1) {
            return (int) ($this->price_1_day ?? $this->price_3plus ?? 0);
        }

        if ($days === 2) {
            return (int) ($this->price_2_days ?? $this->price_3plus ?? 0);
        }

        return (int) ($this->price_3plus ?? 0);
    }
}
