<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'search_aliases', 'title', 'description', 'seo_text', 'seo_title', 'seo_description', 'sort'])]
class BodyType extends Model
{
    /** @return HasMany<Car, $this> */
    public function cars(): HasMany
    {
        return $this->hasMany(Car::class);
    }

    /**
     * Только разделы, где есть опубликованные машины: пустые («Электро» без машин) в меню и фильтрах не показываем.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeWithCars(Builder $query): Builder
    {
        return $query->whereHas('cars', fn (Builder $q) => $q->where('status', 'published'));
    }
}
