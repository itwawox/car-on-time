<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('public_token', 16)->nullable()->unique()->after('id');
            $table->timestamp('reminded_at')->nullable()->after('escalated_at');
            $table->timestamp('review_requested_at')->nullable()->after('reminded_at');
        });

        // Короткая ссылка на статус для уже принятых заявок
        DB::table('bookings')->whereNull('public_token')->orderBy('id')->each(function ($booking) {
            DB::table('bookings')->where('id', $booking->id)->update(['public_token' => Str::lower(Str::random(12))]);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn(['public_token', 'reminded_at', 'review_requested_at']);
        });
    }
};
