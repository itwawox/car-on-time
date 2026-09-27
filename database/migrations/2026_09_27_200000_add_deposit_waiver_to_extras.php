<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extras', function (Blueprint $table) {
            $table->string('description')->nullable()->after('name');
            $table->boolean('waives_deposit')->default(false)->after('is_free');
            $table->boolean('is_active')->default(true)->after('waives_deposit');
        });

        // Опция «Без залога» заводится выключенной: цену и включение решает владелец в «Справочники → Доп. услуги»
        if (! DB::table('extras')->where('slug', 'bez-zaloga')->exists()) {
            DB::table('extras')->insert([
                'name' => 'Без залога',
                'slug' => 'bez-zaloga',
                'description' => 'Залог не нужен — за небольшую доплату в сутки',
                'price_per_day' => 0,
                'is_free' => false,
                'waives_deposit' => true,
                'is_active' => false,
                'sort' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('extras')->where('slug', 'bez-zaloga')->delete();

        Schema::table('extras', function (Blueprint $table) {
            $table->dropColumn(['description', 'waives_deposit', 'is_active']);
        });
    }
};
