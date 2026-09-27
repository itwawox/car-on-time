<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Support\WebpVariants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\Finder;

class MakeWebpImages extends Command
{
    protected $signature = 'images:webp {--force : Пересоздать уже готовые копии}';

    protected $description = 'Создаёт webp-копии фото машин и обложек статей (PageSpeed: «современные форматы изображений»)';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $made = 0;

        $dir = public_path('legacy-image/auto');
        if (is_dir($dir)) {
            $files = Finder::create()->files()->in($dir)->name('/\.(jpe?g|png)$/i');
            $bar = $this->output->createProgressBar(iterator_count($files));
            foreach ($files as $file) {
                $made += count(WebpVariants::make($file->getPathname(), [0], 82, $force));
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
        }

        foreach (Article::query()->whereNotNull('cover')->get() as $article) {
            $made += count(WebpVariants::make(Storage::disk('public')->path($article->cover), [600, 1200], 80, $force));
        }

        Cache::forget('sitemap');
        $this->info("Готово: создано webp-файлов — {$made}.");

        return self::SUCCESS;
    }
}
