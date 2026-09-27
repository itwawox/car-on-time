<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('first_response_at')->nullable()->after('status');
            $table->timestamp('confirmed_at')->nullable()->after('first_response_at');
            $table->timestamp('escalated_at')->nullable()->after('confirmed_at');
            $table->index(['status', 'created_at']);
        });

        Schema::table('car_blocks', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->after('car_id')->constrained()->cascadeOnDelete();
            $table->index(['car_id', 'starts_at', 'ends_at']);
        });

        Schema::create('booking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('from', 20)->nullable();
            $table->string('to', 20)->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['booking_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_events');

        Schema::table('car_blocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_id');
            $table->dropIndex(['car_id', 'starts_at', 'ends_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn(['first_response_at', 'confirmed_at', 'escalated_at']);
        });
    }
};
