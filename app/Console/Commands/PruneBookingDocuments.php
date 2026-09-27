<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Setting;
use Illuminate\Console\Command;

class PruneBookingDocuments extends Command
{
    protected $signature = 'documents:prune';

    protected $description = 'Удалить документы клиентов (паспорт, права) после окончания аренды — по сроку из настроек (152-ФЗ)';

    public function handle(): int
    {
        $days = max(1, (int) Setting::get('documents_retention_days', 30));

        $bookings = Booking::query()
            ->whereNotNull('documents_uploaded_at')->whereNull('documents_deleted_at')
            ->where(fn ($q) => $q->where('ends_at', '<', now()->subDays($days))
                ->orWhere(fn ($q) => $q->where('status', 'declined')->where('updated_at', '<', now()->subDays(7))))
            ->get();

        foreach ($bookings as $booking) {
            $booking->clearMediaCollection('documents');
            $booking->forceFill(['documents_deleted_at' => now()])->saveQuietly();
            $booking->events()->create(['type' => 'note', 'comment' => 'Документы клиента удалены по сроку хранения']);
        }

        $this->info('Удалены документы по заявкам: '.$bookings->count());

        return self::SUCCESS;
    }
}
