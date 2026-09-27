<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('integration', 40);
            $table->string('event', 60);
            $table->nullableMorphs('subject');
            $table->string('status', 20);
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['integration', 'status', 'created_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('crm_entity', 20)->nullable()->after('notes');
            $table->string('crm_id', 40)->nullable()->after('crm_entity');
            $table->index('crm_id');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('crm_entity', 20)->nullable()->after('notes');
            $table->string('crm_id', 40)->nullable()->after('crm_entity');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['crm_entity', 'crm_id']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['crm_id']);
            $table->dropColumn(['crm_entity', 'crm_id']);
        });

        Schema::dropIfExists('integration_logs');
    }
};
