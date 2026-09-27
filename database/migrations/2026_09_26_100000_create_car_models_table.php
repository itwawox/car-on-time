<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Справочник моделей: характеристики и текст о модели пишутся один раз,
 * описания конкретных машин собираются из него (App\Support\CarDescription).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('body_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('drivetrain')->nullable();
            $table->string('drivetrain_note')->nullable();
            $table->string('fuel')->nullable();
            $table->string('fuel_note')->nullable();
            $table->unsignedTinyInteger('seats')->nullable();
            $table->unsignedSmallInteger('trunk_l')->nullable();
            $table->unsignedSmallInteger('clearance_mm')->nullable();
            $table->text('overview')->nullable();
            $table->json('strengths')->nullable();
            $table->boolean('is_verified')->default(true);
            $table->timestamps();
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->foreignId('car_model_id')->nullable()->after('brand_id')->constrained('car_models')->nullOnDelete();
            $table->boolean('specs_verified')->default(false)->after('drivetrain');
            $table->timestamp('description_generated_at')->nullable()->after('description');
        });

        $now = now();
        $bodies = [
            ['slug' => 'hetchbek', 'name' => 'Хэтчбек', 'title' => 'Аренда хэтчбека в Крыму', 'search_aliases' => 'хэтчбек, хетчбек, hatchback', 'sort' => 6],
            ['slug' => 'liftbek', 'name' => 'Лифтбек', 'title' => 'Аренда лифтбека в Крыму', 'search_aliases' => 'лифтбек, liftback', 'sort' => 7],
            ['slug' => 'universal', 'name' => 'Универсал', 'title' => 'Аренда универсала в Крыму', 'search_aliases' => 'универсал, wagon, багажник большой', 'sort' => 8],
            ['slug' => 'kupe', 'name' => 'Купе', 'title' => 'Аренда купе в Крыму', 'search_aliases' => 'купе, coupe, спорткар', 'sort' => 9],
            ['slug' => 'vnedorozhnik', 'name' => 'Внедорожник', 'title' => 'Аренда внедорожника в Крыму', 'search_aliases' => 'внедорожник, джип, jeep, рамный, offroad', 'sort' => 10],
        ];
        foreach ($bodies as $body) {
            DB::table('body_types')->insertOrIgnore($body + ['created_at' => $now, 'updated_at' => $now]);
        }

        // «внедорожник» теперь отдельный кузов — у кроссовера этот синоним убираем
        $krossover = DB::table('body_types')->where('slug', 'krossover')->value('search_aliases');
        if ($krossover) {
            $aliases = array_filter(array_map('trim', explode(',', $krossover)), fn ($a) => $a !== 'внедорожник');
            DB::table('body_types')->where('slug', 'krossover')->update(['search_aliases' => implode(', ', $aliases)]);
        }
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropConstrainedForeignId('car_model_id');
            $table->dropColumn(['specs_verified', 'description_generated_at']);
        });
        Schema::dropIfExists('car_models');
        DB::table('body_types')->whereIn('slug', ['hetchbek', 'liftbek', 'universal', 'kupe', 'vnedorozhnik'])->delete();
    }
};
