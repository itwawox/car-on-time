<?php

namespace App\Console\Commands;

use App\Models\Car;
use App\Support\CarDescription;
use Illuminate\Console\Command;

class DescribeCars extends Command
{
    protected $signature = 'cars:describe
        {--force : Перезаписать и описания, отредактированные вручную}
        {--car=* : Только машины с этими id}';

    protected $description = 'Собирает описания машин из справочника моделей и характеристик';

    public function handle(): int
    {
        $query = Car::query()->with(['carModel', 'bodyType', 'classes', 'prices']);
        if ($ids = $this->option('car')) {
            $query->whereIn('id', $ids);
        }

        $written = 0;
        $skipped = [];
        foreach ($query->get() as $car) {
            $manual = ! CarDescription::isGeneric($car->description) && $car->description_generated_at === null;
            if ($manual && ! $this->option('force')) {
                $skipped[] = $car->name;

                continue;
            }

            $car->forceFill([
                'description' => CarDescription::build($car),
                'description_generated_at' => now(),
            ])->save();
            $written++;
        }

        $this->info("Описаний собрано: {$written}.");
        if ($skipped) {
            $this->warn('Пропущены (текст написан вручную, используйте --force): '.implode('; ', $skipped));
        }

        $unverified = Car::query()->where('specs_verified', false)->orderBy('name')->pluck('name');
        if ($unverified->isNotEmpty()) {
            $this->newLine();
            $this->warn('Сверьте характеристики с машинами партнёров (привод зависит от комплектации):');
            $unverified->each(fn ($name) => $this->line('  — '.$name));
        }

        return self::SUCCESS;
    }
}
