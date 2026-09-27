<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->json('utm')->nullable()->after('source');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->json('utm')->nullable()->after('page');
        });
    }

    public function down(): void
    {
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn('utm'));
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('utm'));
    }
};
