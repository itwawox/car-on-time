<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->after('author');
            $table->string('city', 80)->nullable()->after('phone');
            $table->text('reply')->nullable()->after('body');
            $table->string('source', 20)->default('site')->after('reply');
            $table->string('ip', 64)->nullable()->after('source');
            $table->index(['is_published', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['is_published', 'reviewed_at']);
            $table->dropColumn(['phone', 'city', 'reply', 'source', 'ip']);
        });
    }
};
