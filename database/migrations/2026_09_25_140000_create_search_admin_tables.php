<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['car_classes', 'body_types'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->text('search_aliases')->nullable()->after('slug');
            });
        }

        Schema::create('search_synonyms', function (Blueprint $table) {
            $table->id();
            $table->string('term')->unique();
            $table->json('synonyms');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('search_queries', function (Blueprint $table) {
            $table->id();
            $table->string('query', 100)->unique();
            $table->unsignedInteger('hits')->default(0);
            $table->unsignedInteger('last_results')->default(0);
            $table->unsignedInteger('zero_results_count')->default(0);
            $table->timestamp('last_searched_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_queries');
        Schema::dropIfExists('search_synonyms');

        foreach (['car_classes', 'body_types'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('search_aliases');
            });
        }
    }
};
