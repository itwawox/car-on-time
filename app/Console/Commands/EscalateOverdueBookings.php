<?php

namespace App\Console\Commands;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Services\TelegramNotifier;
use Illuminate\Console\Command;

class EscalateOverdueBookings extends Command
{
    protected $signature = 'bookings:escalate';

    protected $description = 'Напомнить в рабочий чат о заявках, на которые не ответили дольше срока';

    public function handle(TelegramNotifier $telegram): int
    {
        $overdue = Booking::query()
            ->where('status', 'new')
            ->whereNull('first_response_at')
            ->whereNull('escalated_at')
            ->where('created_at', '<=', now()->subMinutes(Booking::slaMinutes()))
            ->with('car')
            ->get();

        foreach ($overdue as $booking) {
            $minutes = $booking->waitingMinutes();
            $telegram->send(implode("\n", [
                '⏰ Заявка №'.$booking->id.' ждёт ответа '.$minutes.' мин',
                'Авто: '.($booking->car?->name ?? '—'),
                'Телефон: '.$booking->phone,
                BookingResource::getUrl('edit', ['record' => $booking], panel: 'admin'),
            ]), ['booking' => $booking->id]);

            $booking->forceFill(['escalated_at' => now()])->saveQuietly();
            $booking->events()->create(['type' => 'escalated', 'comment' => 'Нет ответа '.$minutes.' мин — напомнили в рабочий чат']);
        }

        $this->info('Эскалировано: '.$overdue->count());

        return self::SUCCESS;
    }
}
