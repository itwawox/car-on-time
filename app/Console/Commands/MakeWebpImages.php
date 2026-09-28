<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Support\WebpVariants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class MakeWebpImages extends Command
{
    protected $signature = 'images:webp {--force : Пересоздать уже готовые копии}';

    protected $description = 'Создаёт webp-копии обложек статей 600 и 1200 px (фото машин получают webp-копии в медиатеке сами)';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $made = 0;

        foreach (Article::query()->whereNotNull('cover')->get() as $article) {
            $made += count(WebpVariants::make(Storage::disk('public')->path($article->cover), [600, 1200], 80, $force));
        }

        Cache::forget('sitemap');
        $this->info("Готово: создано webp-файлов — {$made}.");

        return self::SUCCESS;
    }
}
