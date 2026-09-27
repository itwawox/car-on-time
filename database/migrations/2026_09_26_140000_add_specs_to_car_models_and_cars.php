<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Числовые характеристики для подбора и сравнения: мощность, расход, багажник, клиренс, топливо.
 * Модель хранит типичные паспортные значения, машина — точные (если сверены), они важнее.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_models', function (Blueprint $table) {
            $table->unsignedSmallInteger('power_hp')->nullable()->after('seats');
            $table->unsignedSmallInteger('power_hp_max')->nullable()->after('power_hp');
            $table->decimal('engine_l', 3, 1)->nullable()->after('power_hp_max');
            $table->decimal('consumption_city', 4, 1)->nullable()->after('engine_l');
            $table->decimal('consumption_highway', 4, 1)->nullable()->after('consumption_city');
            $table->decimal('consumption_mixed', 4, 1)->nullable()->after('consumption_highway');
            $table->unsignedSmallInteger('tank_l')->nullable()->after('clearance_mm');
            $table->string('fuel_grade', 10)->nullable()->after('tank_l');
            $table->unsignedSmallInteger('diesel_power_hp')->nullable()->after('fuel_grade');
            $table->decimal('diesel_engine_l', 3, 1)->nullable()->after('diesel_power_hp');
            $table->decimal('diesel_consumption', 4, 1)->nullable()->after('diesel_engine_l');
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->unsignedSmallInteger('power_hp')->nullable()->after('engine');
            $table->decimal('engine_l', 3, 1)->nullable()->after('power_hp');
            $table->decimal('consumption_mixed', 4, 1)->nullable()->after('consumption');
            $table->unsignedSmallInteger('trunk_l')->nullable()->after('consumption_mixed');
            $table->unsignedSmallInteger('clearance_mm')->nullable()->after('trunk_l');
        });

        $now = now();
        foreach (require database_path('data/car_model_specs.php') as $slug => $s) {
            DB::table('car_models')->where('slug', $slug)->update([
                'power_hp' => $s[0], 'power_hp_max' => $s[1], 'engine_l' => $s[2],
                'consumption_city' => $s[3], 'consumption_highway' => $s[4], 'consumption_mixed' => $s[5],
                'trunk_l' => $s[6], 'clearance_mm' => $s[7], 'tank_l' => $s[8], 'fuel_grade' => $s[9],
                'diesel_power_hp' => $s['diesel'][0] ?? null, 'diesel_engine_l' => $s['diesel'][1] ?? null,
                'diesel_consumption' => $s['diesel'][2] ?? null,
                'updated_at' => $now,
            ]);
        }

        // Мощность, которая уже указана в названии машины («1.6л. 123л.с.»), точнее справочника
        foreach (DB::table('cars')->whereNotNull('engine')->get(['id', 'engine']) as $car) {
            $update = [];
            if (preg_match('/(\d{2,3})\s*л\.с/u', $car->engine, $m)) {
                $update['power_hp'] = (int) $m[1];
            }
            if (preg_match('/(\d\.\d)/', $car->engine, $m)) {
                $update['engine_l'] = (float) $m[1];
            }
            if ($update) {
                DB::table('cars')->where('id', $car->id)->update($update);
            }
        }
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn(['power_hp', 'engine_l', 'consumption_mixed', 'trunk_l', 'clearance_mm']);
        });
        Schema::table('car_models', function (Blueprint $table) {
            $table->dropColumn(['power_hp', 'power_hp_max', 'engine_l', 'consumption_city', 'consumption_highway',
                'consumption_mixed', 'tank_l', 'fuel_grade', 'diesel_power_hp', 'diesel_engine_l', 'diesel_consumption']);
        });
    }
};
