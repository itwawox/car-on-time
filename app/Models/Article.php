<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'slug', 'title', 'h1', 'category', 'excerpt', 'content', 'cover', 'cover_alt', 'faq', 'cars_query',
    'author_name', 'author_role', 'seo_title', 'seo_description', 'is_published', 'published_at',
])]
class Article extends Model
{
    public const CATEGORIES = ['Маршруты', 'Советы', 'Документы и правила', 'Аэропорт и доставка', 'Сезоны и цены'];

    protected function casts(): array
    {
        return [
            'faq' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** Опубликованные, с датой публикации не в будущем. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    public function url(): string
    {
        return route('article', $this->slug);
    }

    public function coverUrl(?string $size = null): ?string
    {
        if (! $this->cover) {
            return null;
        }

        $disk = Storage::disk('public');
        if ($size) {
            $variant = preg_replace('/\.\w+$/', "-{$size}.webp", $this->cover);
            if ($disk->exists($variant)) {
                return $disk->url($variant);
            }
        }

        return $disk->url($this->cover);
    }

    public function readingMinutes(): int
    {
        return max(1, (int) ceil(mb_strlen(strip_tags((string) $this->content)) / 1300));
    }

    /**
     * Контент с якорями у подзаголовков H2 и оглавление по ним.
     *
     * @return array{html: string, toc: list<array{id: string, title: string}>}
     */
    public function contentWithToc(): array
    {
        $toc = [];
        $html = preg_replace_callback('/<h2([^>]*)>(.*?)<\/h2>/su', function ($m) use (&$toc) {
            $title = trim(strip_tags($m[2]));
            $id = 'h-'.(count($toc) + 1);
            $toc[] = ['id' => $id, 'title' => $title];

            return '<h2'.preg_replace('/\sid="[^"]*"/', '', $m[1]).' id="'.$id.'">'.$m[2].'</h2>';
        }, (string) $this->content);

        return ['html' => (string) $html, 'toc' => $toc];
    }
}
