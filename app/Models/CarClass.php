<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'search_aliases', 'title', 'description', 'seo_text', 'seo_title', 'seo_description', 'sort'])]
class CarClass extends Model
{
    /** @return BelongsToMany<Car, $this> */
    public function cars(): BelongsToMany
    {
        return $this->belongsToMany(Car::class);
    }
}
