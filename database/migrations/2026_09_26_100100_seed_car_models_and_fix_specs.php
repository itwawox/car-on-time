<?php

use App\Support\CarSpecs;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Наполняет справочник моделей (database/data/car_models.php) и исправляет характеристики машин,
 * которые пришли из чужого шаблона неверными: привод, кузов, годы, двигатель, число мест.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $brands = DB::table('brands')->pluck('id', 'slug');
        $bodies = DB::table('body_types')->pluck('id', 'slug');
        $models = [];

        foreach (require database_path('data/car_models.php') as $m) {
            if (! isset($brands[$m['brand']])) {
                continue;
            }

            DB::table('car_models')->insertOrIgnore([
                'brand_id' => $brands[$m['brand']],
                'name' => $m['name'],
                'slug' => $m['slug'],
                'body_type_id' => $bodies[$m['body']] ?? null,
                'drivetrain' => $m['drive'],
                'drivetrain_note' => $m['drive_note'] ?? null,
                'fuel' => $m['fuel'] ?? null,
                'fuel_note' => $m['fuel_note'] ?? null,
                'overview' => $m['overview'],
                'strengths' => json_encode($m['strengths'], JSON_UNESCAPED_UNICODE),
                'is_verified' => $m['verified'] ?? true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $models[] = $m + ['id' => DB::table('car_models')->where('slug', $m['slug'])->value('id')];
        }

        foreach (DB::table('cars')->get() as $car) {
            $name = mb_strtolower((string) $car->name);
            $brandSlug = $brands->search($car->brand_id);
            $model = collect($models)->first(fn ($m) => $m['brand'] === $brandSlug && preg_match($m['match'], $name));

            $update = ['car_model_id' => $model['id'] ?? null];

            // Привод: явное указание в названии важнее справочника
            $explicit4wd = CarSpecs::explicit4wd($name);
            $update['drivetrain'] = $explicit4wd ? '4wd' : ($model['drive'] ?? $car->drivetrain);
            $update['specs_verified'] = $model ? ($explicit4wd || ($model['verified'] ?? true)) : false;

            if ($model && isset($bodies[$model['body']])) {
                $update['body_type_id'] = $bodies[$model['body']];
            }
            if (! empty($model['fuel'])) {
                $update['fuel'] = $model['fuel'];
            }

            [$from, $to] = CarSpecs::years($name);
            if ($from) {
                $update['year_from'] = $from;
                $update['year_to'] = $to;
            }
            if ($engine = CarSpecs::engine($name)) {
                $update['engine'] = $engine;
            }
            if ($seats = CarSpecs::seats($name)) {
                $update['seats'] = $seats;
            }

            DB::table('cars')->where('id', $car->id)->update($update);
        }
    }

    public function down(): void
    {
        DB::table('cars')->update(['car_model_id' => null]);
        DB::table('car_models')->delete();
    }
};
