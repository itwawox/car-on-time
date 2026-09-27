<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'slug', 'title', 'badge', 'excerpt', 'body', 'cover', 'promo_code', 'seo_title', 'seo_description',
    'starts_at', 'ends_at', 'is_published', 'sort',
])]
class Promotion extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    /** Опубликованные и действующие сейчас: начались (или без даты) и не закончились. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('sort')->orderByDesc('id');
    }

    public function url(): string
    {
        return route('promotion', $this->slug);
    }

    public function coverUrl(?string $size = null): ?string
    {
        if (! $this->cover) {
            return null;
        }
        $disk = Storage::disk('public');
        if ($size && $disk->exists($variant = preg_replace('/\.\w+$/', "-{$size}.webp", $this->cover))) {
            return $disk->url($variant);
        }

        return $disk->url($this->cover);
    }

    public function periodLabel(): ?string
    {
        return match (true) {
            $this->starts_at && $this->ends_at => 'с '.$this->starts_at->translatedFormat('j F').' по '.$this->ends_at->translatedFormat('j F Y'),
            (bool) $this->ends_at => 'до '.$this->ends_at->translatedFormat('j F Y'),
            default => null,
        };
    }
}
