<?php

use App\Support\Phone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Телефон в одном виде (79781234567): по нему личный кабинет находит все брони клиента
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('phone_digits', 20)->nullable()->after('phone')->index();
        });

        DB::table('bookings')->orderBy('id')->each(function ($booking) {
            DB::table('bookings')->where('id', $booking->id)->update(['phone_digits' => ltrim(Phone::e164((string) $booking->phone), '+')]);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['phone_digits']);
            $table->dropColumn('phone_digits');
        });
    }
};
