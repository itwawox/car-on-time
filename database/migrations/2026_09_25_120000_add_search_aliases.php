<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->text('search_aliases')->nullable()->after('slug');
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->text('search_aliases')->nullable()->after('seo_description');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('search_aliases');
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn('search_aliases');
        });
    }
};
