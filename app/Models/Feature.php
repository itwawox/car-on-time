<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'icon', 'sort'])]
class Feature extends Model
{
    /** @return BelongsToMany<Car, $this> */
    public function cars(): BelongsToMany
    {
        return $this->belongsToMany(Car::class);
    }
}
