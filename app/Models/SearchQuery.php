<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['query', 'hits', 'last_results', 'zero_results_count', 'last_searched_at'])]
class SearchQuery extends Model
{
    protected function casts(): array
    {
        return [
            'last_searched_at' => 'datetime',
        ];
    }
}
