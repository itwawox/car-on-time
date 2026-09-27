<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'description', 'slug', 'price_per_day', 'is_free', 'waives_deposit', 'is_active', 'sort'])]
class Extra extends Model
{
    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'waives_deposit' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort');
    }
}
