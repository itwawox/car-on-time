<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Поля от импорта старого HTML-шаблона: запасное фото машины и логотип марки.
 * Сайт их не показывает — фото берутся из медиатеки, логотипы марок нигде не выводятся.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn('legacy_image');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->string('legacy_image')->nullable();
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->string('logo_path')->nullable();
        });
    }
};
