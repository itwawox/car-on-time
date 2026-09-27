<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Документы клиента (отдельное согласие по 152-ФЗ) и цифровой акт осмотра при выдаче и возврате
            $table->timestamp('documents_consent_at')->nullable()->after('prepaid_at');
            $table->timestamp('documents_uploaded_at')->nullable()->after('documents_consent_at');
            $table->timestamp('documents_deleted_at')->nullable()->after('documents_uploaded_at');
            $table->unsignedInteger('pickup_mileage')->nullable()->after('documents_deleted_at');
            $table->unsignedTinyInteger('pickup_fuel')->nullable()->after('pickup_mileage');
            $table->unsignedInteger('return_mileage')->nullable()->after('pickup_fuel');
            $table->unsignedTinyInteger('return_fuel')->nullable()->after('return_mileage');
            $table->text('inspection_notes')->nullable()->after('return_fuel');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['documents_consent_at', 'documents_uploaded_at', 'documents_deleted_at', 'pickup_mileage', 'pickup_fuel', 'return_mileage', 'return_fuel', 'inspection_notes']);
        });
    }
};
