<?php

use App\Models\Article;
use App\Models\Car;
use App\Models\Redirect;
use App\Support\WebpVariants;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Первые 10 статей раздела /stati. Тонкие черновики, перенесённые со старого сайта, удаляются,
 * их адреса ведут 301-редиректом на новые статьи на ту же тему.
 */
return new class extends Migration
{
    /** Старый черновик → новая статья. */
    private const REPLACES = [
        'aeroport-sip' => 'arenda-avto-po-priezde-v-simferopol',
        'bez-predoplaty' => 'arenda-avto-v-krymu-bez-predoplaty',
        'gory' => 'aj-petri-na-mashine',
        'kabriolet-yubk' => 'yuzhnyj-bereg-kryma-na-mashine',
        'semya' => 'kakuyu-mashinu-vzyat-v-krym',
    ];

    public function up(): void
    {
        $articles = require database_path('data/articles.php');
        $published = now()->subDays(count($articles));

        foreach ($articles as $i => $data) {
            $article = Article::query()->firstOrNew(['slug' => $data['slug']]);
            $article->fill([
                'title' => $data['title'],
                'category' => $data['category'],
                'excerpt' => $data['excerpt'],
                'content' => trim($data['content']),
                'faq' => $data['faq'],
                'cars_query' => $data['cars_query'],
                'is_published' => true,
                'published_at' => $article->published_at ?? $published->copy()->addDays($i)->setTime(10, 0),
            ]);

            if (! $article->cover && ($cover = $this->cover($data['cover_car'], $data['slug']))) {
                $article->cover = $cover;
                $article->cover_alt = $data['title'];
            }
            $article->save();
        }

        foreach (self::REPLACES as $old => $new) {
            Article::query()->where('slug', $old)->where('is_published', false)->delete();
            Redirect::query()->updateOrCreate(['from_path' => '/stati/'.$old], ['to_path' => '/stati/'.$new, 'status' => 301]);
        }

        Cache::forget('articles:has-published');
        Cache::forget('sitemap');
    }

    public function down(): void
    {
        $slugs = array_column(require database_path('data/articles.php'), 'slug');
        Article::query()->whereIn('slug', $slugs)->update(['is_published' => false]);
        Cache::forget('articles:has-published');
        Cache::forget('sitemap');
    }

    /** Обложка — фото машины из нашего парка, копия в articles/ + webp 600/1200. */
    private function cover(string $carSlug, string $slug): ?string
    {
        $car = Car::query()->where('slug', $carSlug)->first();
        $media = $car?->getFirstMedia('gallery');
        $source = $media?->getPath();
        if (! $source || ! is_file($source)) {
            return null;
        }

        $disk = Storage::disk('public');
        $path = 'articles/'.$slug.'.'.strtolower(pathinfo($source, PATHINFO_EXTENSION) ?: 'jpg');
        $disk->put($path, file_get_contents($source));
        WebpVariants::make($disk->path($path), [600, 1200]);

        return $path;
    }
};
