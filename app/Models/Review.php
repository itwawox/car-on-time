<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

#[Fillable(['consent_at', 'author', 'phone', 'city', 'rating', 'body', 'reply', 'source', 'ip', 'car_id', 'is_published', 'reviewed_at'])]
class Review extends Model
{
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'reviewed_at' => 'datetime',
            'rating' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (Review $review) => $review->is_published && ! $review->reviewed_at ? $review->reviewed_at = now() : null);
        static::saved(function () {
            Cache::forget('reviews:summary');
            Cache::forget('sitemap');
        });
        static::deleted(fn () => Cache::forget('reviews:summary'));
    }

    /** @return BelongsTo<Car, $this> */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->orderByDesc('reviewed_at')->orderByDesc('id');
    }

    /**
     * Средняя оценка и число опубликованных отзывов — только настоящие, из базы.
     *
     * @return array{count: int, avg: float}
     */
    public static function summary(): array
    {
        return Cache::rememberForever('reviews:summary', function () {
            $row = static::query()->where('is_published', true)->selectRaw('count(*) as c, avg(rating) as a')->first();

            return ['count' => (int) $row->c, 'avg' => round((float) $row->a, 1)];
        });
    }
}
