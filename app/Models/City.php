<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'slug', 'name', 'h1', 'seo_title', 'seo_description', 'intro', 'seo_text', 'map_url',
    'card_subtitle', 'show_on_home', 'show_in_footer', 'is_published', 'sort',
])]
class City extends Model
{
    protected function casts(): array
    {
        return [
            'show_on_home' => 'boolean',
            'show_in_footer' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->orderBy('sort');
    }
}
