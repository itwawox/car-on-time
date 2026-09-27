<?php

namespace App\Console\Commands;

use App\Models\Car;
use App\Support\CarPhotoMatcher;
use App\Support\Search\SmartEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Spatie\Image\Enums\Constraint;
use Spatie\Image\Image;
use Throwable;

class ImportCarPhotos extends Command
{
    protected $signature = 'cars:import-photos
        {dir=image-cars : Папка с old_image/ и new_image/ (путь от корня проекта или абсолютный)}
        {--dry-run : Только показать, что с чем сопоставится}
        {--force : Заменить уже загруженные фото}';

    protected $description = 'Сопоставляет новые фото машин со старыми, переименовывает и загружает на сайт';

    /** Максимальная ширина оригинала, который храним на сайте. */
    private const MAX_WIDTH = 1600;

    public function handle(): int
    {
        $dir = str_starts_with($this->argument('dir'), '/') ? $this->argument('dir') : base_path($this->argument('dir'));
        if (! is_dir($dir.'/old_image') || ! is_dir($dir.'/new_image')) {
            $this->error("В {$dir} нужны папки old_image/ и new_image/.");

            return self::FAILURE;
        }

        $this->info('Сопоставляю фото…');
        ['matched' => $matched, 'orphans' => $orphans] = CarPhotoMatcher::match($dir);

        $this->table(['№', 'Машина', 'Новое имя файла', 'Статус'], array_map(fn ($m) => [
            $m['n'],
            mb_strimwidth($m['car']->name, 0, 48, '…'),
            $m['car']->slug.'.jpg',
            $this->alreadyImported($m['car'], basename($m['old'])) ? 'уже загружено' : 'будет загружено',
        ], $matched));

        if ($orphans) {
            $this->warn('Без машины в каталоге:');
            foreach ($orphans as $o) {
                $this->line("  №{$o['n']} ".basename($o['old'])." — {$o['reason']}");
            }
        }

        $this->hints($matched);

        if ($this->option('dry-run')) {
            $this->info('Пробный запуск: ничего не изменено. Сопоставлено: '.count($matched).', без машины: '.count($orphans).'.');

            return self::SUCCESS;
        }

        $renamed = $dir.'/renamed';
        File::ensureDirectoryExists($renamed.'/без-машины');
        $imported = 0;
        $skipped = 0;

        $bar = $this->output->createProgressBar(count($matched));
        foreach ($matched as $m) {
            /** @var Car $car */
            $car = $m['car'];
            $source = basename($m['old']);
            $prepared = $renamed.'/'.$car->slug.'.jpg';

            try {
                $this->prepare($m['new'], $prepared);

                if ($this->alreadyImported($car, $source) && ! $this->option('force')) {
                    $skipped++;
                } else {
                    $car->clearMediaCollection('gallery');
                    $car->addMedia($prepared)
                        ->preservingOriginal()
                        ->usingName($car->name)
                        ->usingFileName($car->slug.'.jpg')
                        ->withCustomProperties(['alt' => 'Аренда '.$car->name.' в Крыму', 'source' => $source])
                        ->toMediaCollection('gallery');
                    $car->touch(); // сброс кэшей, карта сайта, IndexNow
                    $imported++;
                }
            } catch (Throwable $e) {
                $this->newLine();
                $this->error("№{$m['n']} {$car->name}: ".$e->getMessage());
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        foreach ($orphans as $o) {
            if ($o['new']) {
                $name = preg_replace('/^\d+_(arenda-prokat-avto-)?/', '', basename($o['old']));
                $this->prepare($o['new'], $renamed.'/без-машины/'.preg_replace('/\.\w+$/', '.jpg', $name));
            }
        }

        Cache::forget('sitemap');
        SmartEngine::forget('cars');

        $this->info("Загружено: {$imported}, уже были: {$skipped}, без машины: ".count($orphans).'.');
        $this->line("Переименованные копии: {$renamed}");

        return self::SUCCESS;
    }

    /** Уменьшаем до 1600 px, jpg q85, без метаданных. */
    private function prepare(string $from, string $to): void
    {
        Image::load($from)
            ->width(self::MAX_WIDTH, [Constraint::PreserveAspectRatio, Constraint::DoNotUpsize])
            ->quality(85)
            ->format('jpg')
            ->save($to);
    }

    private function alreadyImported(Car $car, string $source): bool
    {
        return $car->getMedia('gallery')->contains(fn ($media) => $media->getCustomProperty('source') === $source);
    }

    /**
     * Старые имена файлов иногда подсказывают комплектацию («x1-f48-4wd», «520d-2016»).
     *
     * @param  list<array{old: string, car: Car}>  $matched
     */
    private function hints(array $matched): void
    {
        $hints = [];
        foreach ($matched as $m) {
            if ($m['car']->specs_verified) {
                continue;
            }
            $name = mb_strtolower(basename($m['old']));
            $drive = match (true) {
                (bool) preg_match('/4wd|xdrive|4matic|quattro|awd|all4/u', $name) => 'полный привод',
                (bool) preg_match('/sdrive|2wd|fwd/u', $name) => 'не полный привод',
                default => null,
            };
            if ($drive) {
                $hints[] = "  {$m['car']->name}: в старом имени фото «".basename($m['old'])."» — похоже на {$drive}";
            }
        }

        if ($hints) {
            $this->warn('Подсказки для сверки привода (машины с флагом «не сверено»):');
            foreach ($hints as $hint) {
                $this->line($hint);
            }
        }
    }
}
