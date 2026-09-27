<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20)->default('yookassa');
            $table->string('provider_id', 64)->nullable()->unique();
            $table->unsignedInteger('amount');
            $table->string('status', 20)->default('pending');
            $table->string('confirmation_url', 500)->nullable();
            $table->string('method', 40)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['booking_id', 'status']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedInteger('prepaid_amount')->default(0)->after('discount');
            $table->timestamp('prepaid_at')->nullable()->after('prepaid_amount');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['prepaid_amount', 'prepaid_at']);
        });

        Schema::dropIfExists('payments');
    }
};
