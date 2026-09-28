<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Журнал резервного копирования базы: каждый запуск backup:database — с источником, результатом и файлом. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20);
            $table->string('status', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file')->nullable()->unique();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
